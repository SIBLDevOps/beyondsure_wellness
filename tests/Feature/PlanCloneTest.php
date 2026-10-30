<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Clone": open the new-plan form already filled from an existing plan, edit anything, then save as a new plan. */
class PlanCloneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Product $syrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $cat = Category::create(['name' => 'Wellness', 'slug' => 'w', 'is_free' => false]);
        $this->syrup = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Gold', 'slug' => 'gold', 'description' => 'The gold bundle', 'price' => 2500, 'bv' => 1000, 'is_active' => true, 'region_id' => Region::fallbackId()]);
        $this->plan->products()->attach($this->syrup->id, ['qty' => 3]);
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'S', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    public function test_every_plan_card_has_a_clone_button_that_opens_the_new_plan_form(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.index'))->assertOk()
            ->assertSee('Clone')->assertSee(route('admin.plans.create', ['clone' => $this->plan->id]), false);
    }

    public function test_the_clone_form_is_filled_from_the_original_and_marked_as_a_copy(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => $this->plan->id]))->assertOk()
            ->assertSee('Create New Plan Bundle')
            ->assertSee('Gold (copy)')                       // a new name to change
            ->assertSee('The gold bundle')
            ->assertSee('value="2500.00"', false)            // price
            ->assertSee('value="1000.00"', false)            // BV
            ->assertSee('name="qty['.$this->syrup->id.']"', false)
            ->assertSee('value="3"', false)                  // quantity copied
            ->assertSee('You are cloning');                  // so it is clear this is not the original
    }

    public function test_opening_the_clone_form_creates_nothing_and_leaves_the_original_alone(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => $this->plan->id]))->assertOk();

        $this->assertSame(1, Plan::count());
        $this->assertSame('Gold', $this->plan->fresh()->name);
    }

    public function test_saving_the_clone_with_changes_makes_a_new_plan_and_keeps_the_original(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.plans.store'), [
            'region_id' => $this->plan->region_id, 'name' => 'Platinum', 'description' => 'Changed', 'price' => 4000, 'bv' => 1600,
            'is_active' => 1, 'qty' => [$this->syrup->id => 5],
        ])->assertRedirect(route('admin.plans.index'));

        $this->assertSame(2, Plan::count());
        $new = Plan::where('name', 'Platinum')->firstOrFail();
        $this->assertSame(5, (int) $new->products()->first()->pivot->qty);
        $this->assertSame('Gold', $this->plan->fresh()->name);
        $this->assertSame(3, (int) $this->plan->fresh()->products()->first()->pivot->qty);
        $this->assertSame(2500.0, (float) $this->plan->fresh()->price);
    }

    public function test_an_unknown_plan_to_clone_is_a_404_and_a_plain_create_form_is_still_empty(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => 99999]))->assertNotFound();
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => 'abc']))->assertNotFound();

        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create'))->assertOk()
            ->assertDontSee('You are cloning')->assertDontSee('(copy)');
    }

    public function test_staff_who_can_only_view_the_catalog_get_no_clone_button_and_no_clone_form(): void
    {
        $viewer = $this->sub(['catalog']);

        $this->actingAs($viewer, 'web')->get(route('admin.plans.index'))->assertOk()->assertDontSee('Clone');
        $this->actingAs($viewer, 'web')->get(route('admin.plans.create', ['clone' => $this->plan->id]))->assertForbidden();
    }

    public function test_a_region_limited_sub_admin_cannot_clone_a_plan_from_a_region_they_do_not_have(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $sub = $this->sub(['catalog', 'catalog.manage', 'finance'], [$gj->id]);

        $this->actingAs($sub, 'web')->get(route('admin.plans.create', ['clone' => $this->plan->id]))->assertNotFound();
    }

    public function test_a_clone_can_be_moved_to_another_region_and_a_long_name_is_not_cut_off_badly(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);

        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => $this->plan->id]))->assertOk()
            ->assertSee('Gujarat');

        $this->plan->update(['name' => str_repeat('A', 250)]);
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => $this->plan->id]))->assertOk()
            ->assertDontSee(str_repeat('A', 250).' (copy)'); // would exceed the 255 character limit
    }
}
