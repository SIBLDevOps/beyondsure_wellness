<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionTest extends TestCase
{
    use RefreshDatabase;

    private Region $gj;

    private Region $mh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ', 'sort_order' => 1]);
        $this->mh = Region::create(['name' => 'Maharashtra', 'code' => 'MH', 'sort_order' => 2]);
    }

    private function member(string $code, Region $region, ?Member $sponsor = null, ?string $phone = null): Member
    {
        return Member::create([
            'member_code' => $code, 'name' => $code, 'phone' => $phone ?? '9'.random_int(100000000, 999999999),
            'region_id' => $region->id, 'sponsor_id' => $sponsor?->id, 'placement_id' => $sponsor?->id,
            'position' => $sponsor ? 'left' : null, 'status' => 'inactive',
        ]);
    }

    private function admin(string $role = 'admin', array $perms = []): User
    {
        return User::create(['name' => 'A', 'email' => 'a'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => $role, 'permissions' => $perms, 'is_active' => true]);
    }

    /** Runs the whole register -> OTP flow and returns the created member (or null if refused). */
    private function register(array $fields): ?Member
    {
        // Each registration is a fresh visitor (the previous one was logged in by the flow).
        $this->app['auth']->guard('member')->logout();
        $this->flushSession();

        $res = $this->post(route('register.send'), $fields + ['name' => 'New Person']);
        if (session('errors')) {
            return null;
        }
        $res->assertRedirect(route('register.verify'));
        $phone = session('pending_registration')['phone'];
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        return Member::where('phone', $phone)->first();
    }

    public function test_a_mobile_number_cannot_join_a_second_region_and_the_message_names_the_first(): void
    {
        $this->member('GJ0001', $this->gj, null, '9876543210');

        $res = $this->from(route('register'))->post(route('register.send'), [
            'name' => 'Dup', 'phone' => '9876543210', 'region' => 'MH',
        ]);

        $res->assertSessionHasErrors('phone');
        $this->assertStringContainsString('already registered in the Gujarat region', session('errors')->first('phone'));
        $this->assertSame(1, Member::where('phone', '9876543210')->count());
    }

    public function test_the_same_region_gets_the_plain_already_registered_message(): void
    {
        $this->member('GJ0001', $this->gj, null, '9876543210');

        $this->post(route('register.send'), ['name' => 'Dup', 'phone' => '9876543210', 'region' => 'GJ'])
            ->assertSessionHasErrors('phone');
        $this->assertStringContainsString('Please sign in', session('errors')->first('phone'));
    }

    public function test_the_first_member_of_a_region_is_its_root_and_the_next_joins_under_it(): void
    {
        $first = $this->register(['phone' => '9000000001', 'region' => 'GJ']);
        $this->assertNull($first->placement_id);
        $this->assertSame($this->gj->id, $first->region_id);

        $second = $this->register(['phone' => '9000000002', 'region' => 'GJ']);
        $this->assertSame($first->id, $second->placement_id);
    }

    public function test_each_region_has_its_own_tree_root(): void
    {
        $gjRoot = $this->register(['phone' => '9000000001', 'region' => 'GJ']);
        $mhRoot = $this->register(['phone' => '9000000002', 'region' => 'MH']);

        $this->assertNull($gjRoot->placement_id);
        $this->assertNull($mhRoot->placement_id, 'A new region starts its own tree; it must not hang under another region.');
        $this->assertNotSame($gjRoot->region_id, $mhRoot->region_id);
    }

    public function test_a_sponsor_from_another_region_is_refused(): void
    {
        $sponsor = $this->member('GJSPON1', $this->gj);

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9000000003', 'sponsor_code' => 'GJSPON1', 'region' => 'MH'])
            ->assertSessionHasErrors('sponsor_code');
        $this->assertStringContainsString('Gujarat', session('errors')->first('sponsor_code'));
        $this->assertDatabaseMissing('members', ['phone' => '9000000003']);
        $this->assertNotNull($sponsor);
    }

    public function test_a_sponsor_invite_link_decides_the_region(): void
    {
        $sponsor = $this->member('MHSPON1', $this->mh);

        $m = $this->register(['phone' => '9000000004', 'sponsor_code' => 'MHSPON1']);

        $this->assertSame($this->mh->id, $m->region_id);
        $this->assertSame($sponsor->id, $m->sponsor_id);
    }

    public function test_registration_needs_a_region_when_several_are_open_and_none_is_given(): void
    {
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9000000005'])->assertSessionHasErrors('region');
    }

    public function test_a_closed_region_refuses_new_members(): void
    {
        $this->mh->update(['is_active' => false]);

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9000000006', 'region' => 'MH'])->assertSessionHasErrors('region');
    }

    public function test_sponsor_required_setting_is_per_region(): void
    {
        Setting::set('require_sponsor', 1);
        $this->member('GJ0001', $this->gj); // Gujarat has people, Maharashtra is empty

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9000000007', 'region' => 'GJ'])->assertSessionHasErrors('sponsor_code');
        $this->post(route('register.send'), ['name' => 'Y', 'phone' => '9000000008', 'region' => 'MH'])->assertSessionHasNoErrors();
    }

    public function test_a_member_can_only_buy_plans_of_their_own_region(): void
    {
        $gjPlan = Plan::create(['name' => 'GJ Plan', 'slug' => 'gj', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $this->gj->id]);
        $mhPlan = Plan::create(['name' => 'MH Plan', 'slug' => 'mh', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $this->mh->id]);
        $m = $this->member('GJ0001', $this->gj);

        $this->assertInstanceOf(Order::class, app(OrderService::class)->place($m, $gjPlan));

        $this->expectException(\DomainException::class);
        app(OrderService::class)->place($m, $mhPlan);
    }

    public function test_the_member_plans_page_lists_only_their_region(): void
    {
        Plan::create(['name' => 'GJ Plan', 'slug' => 'gj', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $this->gj->id]);
        Plan::create(['name' => 'MH Plan', 'slug' => 'mh', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $this->mh->id]);

        $this->actingAs($this->member('GJ0001', $this->gj), 'member')->get(route('member.plans.index'))
            ->assertOk()->assertSee('GJ Plan')->assertDontSee('MH Plan');
    }

    public function test_buying_another_regions_plan_by_link_is_refused_politely(): void
    {
        $mhPlan = Plan::create(['name' => 'MH Plan', 'slug' => 'mh', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $this->mh->id]);

        $this->actingAs($this->member('GJ0001', $this->gj), 'member')->post(route('member.plans.buy', $mhPlan))
            ->assertRedirect(route('member.plans.index'))->assertSessionHasErrors('plan');
        $this->assertSame(0, Order::count());
    }

    public function test_admin_can_create_a_region_and_see_its_invite_link(): void
    {
        $this->actingAs($this->admin())->post(route('admin.regions.store'), ['name' => 'Delhi NCR', 'code' => 'delhi ncr'])
            ->assertRedirect(route('admin.regions.index'));

        $this->assertDatabaseHas('regions', ['name' => 'Delhi NCR', 'code' => 'DELHINCR']);
        $this->actingAs($this->admin())->get(route('admin.regions.index'))->assertOk()
            ->assertSee('Delhi NCR')->assertSee('register?region=DELHINCR', false);
    }

    public function test_region_code_is_locked_once_members_joined(): void
    {
        $this->member('GJ0001', $this->gj);

        $this->actingAs($this->admin())->put(route('admin.regions.update', $this->gj), ['name' => 'Gujarat State', 'code' => 'XX']);

        $this->assertSame('GJ', $this->gj->fresh()->code);
        $this->assertSame('Gujarat State', $this->gj->fresh()->name);
    }

    public function test_a_plan_with_orders_cannot_move_to_another_region(): void
    {
        $plan = Plan::create(['name' => 'GJ Plan', 'slug' => 'gj', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $this->gj->id]);
        app(OrderService::class)->place($this->member('GJ0001', $this->gj), $plan);

        $this->actingAs($this->admin())->put(route('admin.plans.update', $plan), [
            'region_id' => $this->mh->id, 'name' => 'GJ Plan', 'price' => 1000, 'bv' => 400,
        ])->assertSessionHasErrors('region_id');

        $this->assertSame($this->gj->id, $plan->fresh()->region_id);
    }

    public function test_sub_admin_needs_the_regions_permission(): void
    {
        $this->actingAs($this->admin('sub_admin', ['members']))->get(route('admin.regions.index'))->assertForbidden();
        $this->actingAs($this->admin('sub_admin', ['regions']))->get(route('admin.regions.index'))->assertOk();
    }

    public function test_members_list_can_be_filtered_by_region(): void
    {
        $this->member('GJONLY1', $this->gj);
        $this->member('MHONLY1', $this->mh);

        $this->actingAs($this->admin())->get(route('admin.members.index', ['region' => $this->mh->id]))
            ->assertOk()->assertSee('MHONLY1')->assertDontSee('GJONLY1');
    }
}
