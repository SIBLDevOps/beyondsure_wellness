<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $code): Member
    {
        return Member::create([
            'member_code' => $code, 'name' => $code, 'phone' => '9'.random_int(100000000, 999999999),
            'status' => 'inactive',
        ]);
    }

    private function staff(array $perms): User
    {
        return User::create([
            'name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123',
            'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true,
        ]);
    }

    public function test_a_brand_new_member_is_one_step_in_and_pointed_at_buying_a_plan(): void
    {
        $p = app(OnboardingService::class)->progress($this->member('NEW1'));

        $this->assertSame(1, $p['done']);
        $this->assertSame('purchase', $p['next']['key']);
        $this->assertFalse($p['complete']);
    }

    public function test_steps_complete_as_the_member_acts(): void
    {
        $m = $this->member('ACT1');
        $plan = Plan::create(['name' => 'P', 'slug' => 'p', 'price' => 1000, 'bv' => 400, 'is_active' => true]);
        Order::create(['member_id' => $m->id, 'plan_id' => $plan->id, 'order_code' => 'ORD-1', 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => 'paid']);
        $m->update(['bank_account_name' => 'A', 'bank_account_number' => '123', 'bank_ifsc' => 'HDFC0001']);

        $p = app(OnboardingService::class)->progress($m->fresh());

        $this->assertSame(3, $p['done']);
        $this->assertSame('invite', $p['next']['key']);
    }

    public function test_member_dashboard_shows_the_checklist(): void
    {
        $m = $this->member('DASH1');

        $this->actingAs($m, 'member')->get(route('member.dashboard'))
            ->assertOk()->assertSee('Getting started')->assertSee('Choose and buy a plan');
    }

    public function test_sub_admin_with_members_access_sees_progress_and_can_filter(): void
    {
        $stuck = $this->member('STUCK1');
        $staff = $this->staff(['members']);

        $this->actingAs($staff)->get(route('admin.members.index', ['onboarding' => 'no_purchase']))
            ->assertOk()->assertSee('STUCK1')->assertSee('next: Choose and buy a plan');

        $this->actingAs($staff)->get(route('admin.members.show', $stuck))
            ->assertOk()->assertSee('Getting-started progress')->assertSee('Has not bought a plan');
    }

    public function test_filters_exclude_members_who_already_did_the_step(): void
    {
        $bought = $this->member('BOUGHT1');
        $plan = Plan::create(['name' => 'P', 'slug' => 'p2', 'price' => 1000, 'bv' => 400, 'is_active' => true]);
        Order::create(['member_id' => $bought->id, 'plan_id' => $plan->id, 'order_code' => 'ORD-2', 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => 'paid']);
        $this->member('NOBUY1');

        $this->actingAs($this->staff(['members']))
            ->get(route('admin.members.index', ['onboarding' => 'no_purchase']))
            ->assertOk()->assertSee('NOBUY1')->assertDontSee('BOUGHT1');
    }
}
