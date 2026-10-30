<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansPageUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $syrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        Setting::set('split_cost_pct', 40);
        $cat = Category::create(['name' => 'Wellness', 'slug' => 'w', 'is_free' => false]);
        $this->syrup = Product::create(['category_id' => $cat->id, 'name' => 'Immunity Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 300, 'gst_pct' => 12, 'is_active' => true]);
    }

    private function plan(string $name, float $price, float $bv, int $qty = 1, bool $active = true, ?Region $r = null): Plan
    {
        $p = Plan::create(['name' => $name, 'slug' => str($name)->slug(), 'price' => $price, 'bv' => $bv, 'is_active' => $active, 'region_id' => $r?->id]);
        $p->products()->attach($this->syrup->id, ['qty' => $qty]);

        return $p;
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'S', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function page(?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->get(route('admin.plans.index'));
    }

    public function test_each_plan_is_a_card_with_price_gst_total_bv_and_what_is_inside(): void
    {
        $this->plan('Essential', 2000, 800, 4);

        $this->page()->assertOk()
            ->assertSee('Essential')->assertSee('Member pays')->assertSee('₹2,000')
            ->assertSee('GST')->assertSee('₹2,240')       // GST is added on top: 12% of ₹2,000 = ₹240
            ->assertSee('BV ₹800')->assertSee('4× Immunity Syrup');
    }

    public function test_a_summary_strip_counts_plans_and_those_needing_attention(): void
    {
        $this->plan('Healthy', 1000, 400, 1);          // cost 100 <= 40% of 1000
        $this->plan('Too costly', 1000, 400, 6);       // cost 600 > 400 limit
        $this->plan('Switched off', 1000, 400, 1, false);

        $res = $this->page()->assertOk()->assertSee('Active plans')->assertSee('Inactive')->assertSee('Needs attention');

        $stats = $res->viewData('stats');
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['inactive']);
        $this->assertSame(1, $stats['attention']);
    }

    public function test_cost_health_is_explained_in_plain_words(): void
    {
        $this->plan('Healthy', 1000, 400, 1);
        $this->plan('Too costly', 1000, 400, 6);

        $this->page()->assertSee('within its limit', false)->assertSee('over its', false)->assertSee('Review pricing');
    }

    public function test_search_filters_and_the_comparison_table_are_available(): void
    {
        $this->plan('Essential', 2000, 800);

        $this->page()->assertSee('id="planSearch"', false)->assertSee('data-filter="active"', false)->assertSee('data-filter="attention"', false)
            ->assertSee('Compare all plans')->assertSee('data-plan-name="essential"', false);
    }

    public function test_a_plan_with_products_that_have_no_gst_rate_says_so(): void
    {
        $noGst = Product::create(['category_id' => $this->syrup->category_id, 'name' => 'Untaxed Tea', 'sku' => 'T1', 'cost_price' => 10, 'sell_price' => 100, 'gst_pct' => null, 'is_active' => true]);
        $plan = $this->plan('Mixed', 1000, 400);
        $plan->products()->attach($noGst->id, ['qty' => 1]);
        $this->plan('Taxed', 1000, 400);

        $res = $this->page()->assertOk();

        $res->assertSee('GST rate missing on 1 product');
        $this->assertSame(1, substr_count($res->getContent(), 'GST rate missing'), 'Only the plan that has such a product is flagged');
    }

    public function test_an_empty_catalogue_invites_the_first_plan(): void
    {
        $this->page()->assertOk()->assertSee('Create your first plan');
    }

    public function test_the_region_filter_still_narrows_the_cards(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $mh = Region::create(['name' => 'Maharashtra', 'code' => 'MH']);
        $this->plan('GJ Plan', 1000, 400, 1, true, $gj);
        $this->plan('MH Plan', 1000, 400, 1, true, $mh);

        $this->actingAs($this->admin, 'web')->get(route('admin.plans.index', ['region' => $mh->id]))->assertSee('MH Plan')->assertDontSee('GJ Plan');
    }

    public function test_a_plan_can_be_switched_off_and_on_from_the_list(): void
    {
        $plan = $this->plan('Essential', 2000, 800);

        $this->actingAs($this->admin, 'web')->put(route('admin.plans.toggle', $plan))->assertRedirect()->assertSessionHas('status');
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($this->admin, 'web')->put(route('admin.plans.toggle', $plan));
        $this->assertTrue($plan->fresh()->is_active);
    }

    public function test_switching_a_plan_needs_catalog_edit_and_finance_access(): void
    {
        $plan = $this->plan('Essential', 2000, 800);

        $this->actingAs($this->sub(['finance']), 'web')->put(route('admin.plans.toggle', $plan))->assertForbidden();
        $this->actingAs($this->sub(['catalog.manage']), 'web')->put(route('admin.plans.toggle', $plan))->assertForbidden();
        $this->assertTrue($plan->fresh()->is_active);

        $this->actingAs($this->sub(['catalog.manage', 'finance']), 'web')->put(route('admin.plans.toggle', $plan))->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);
    }

    public function test_a_region_limited_person_cannot_switch_another_regions_plan(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $mh = Region::create(['name' => 'Maharashtra', 'code' => 'MH']);
        $other = $this->plan('MH Plan', 1000, 400, 1, true, $mh);

        $this->actingAs($this->sub(['catalog.manage', 'finance'], [$gj->id]), 'web')->put(route('admin.plans.toggle', $other))->assertNotFound();
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_the_switch_buttons_only_show_to_people_who_may_use_them(): void
    {
        $this->plan('Essential', 2000, 800);

        $this->page()->assertSee(route('admin.plans.toggle', Plan::first()), false);
        $this->page($this->sub(['catalog', 'finance']))->assertOk()->assertDontSee('/toggle', false)->assertDontSee('New plan');
    }

    public function test_people_without_finance_see_friendly_cards_with_no_cost_or_bv_figures(): void
    {
        $this->plan('Essential', 2000, 800, 4);

        $res = $this->page($this->sub(['catalog']))->assertOk()
            ->assertSee('Essential')->assertSee('4× Immunity Syrup')->assertSee('₹2,000')
            ->assertDontSee('Product cost')->assertDontSee('Review pricing');

        // "BV" as a visible word, not as random characters inside a security token or an asset file name.
        $visibleText = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $res->getContent()));
        $this->assertDoesNotMatchRegularExpression('/\bBV\b/', $visibleText);
    }
}
