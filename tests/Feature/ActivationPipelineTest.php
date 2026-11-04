<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\NetworkService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A: registration asks which plan and leads straight to payment.
 * Phase B: someone only counts as downline after a successful payment.
 * Phase C: a pipeline shows who still has to buy, and at which step.
 */
class ActivationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private Plan $plan;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $cat = Category::create(['name' => 'Wellness', 'slug' => 'w', 'is_free' => false]);
        $prod = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = $this->makePlan('Essential', 2000);
        $this->plan->products()->attach($prod->id, ['qty' => 1]);
    }

    private function makePlan(string $name, float $price, ?Region $r = null, bool $active = true): Plan
    {
        return Plan::create(['name' => $name, 'slug' => str($name)->slug().random_int(1, 9999), 'price' => $price, 'bv' => $price * 0.4, 'is_active' => $active, 'region_id' => ($r ?? $this->region)->id]);
    }

    private function member(string $code, ?Member $sponsor = null, string $status = 'inactive', ?Plan $interested = null, ?string $position = null): Member
    {
        return Member::create([
            'member_code' => $code, 'name' => 'Name '.$code, 'phone' => '9'.random_int(100000000, 999999999), 'status' => $status,
            'region_id' => $this->region->id, 'sponsor_id' => $sponsor?->id, 'placement_id' => $sponsor?->id,
            'position' => $sponsor ? ($position ?? 'left') : null, 'path' => $sponsor ? (($sponsor->path ?? '').$sponsor->id.'.') : null,
            'interested_plan_id' => $interested?->id,
        ]);
    }

    private function order(Member $m, string $status = 'pending_payment', ?Plan $plan = null): Order
    {
        $plan ??= $this->plan;

        return Order::create(['order_code' => 'O'.random_int(100000, 999999), 'member_id' => $m->id, 'plan_id' => $plan->id, 'amount' => 2240, 'taxable_amount' => 2000, 'gst_amount' => 240, 'bv' => 800, 'status' => $status]);
    }

    private function pay(Member $m): Order
    {
        $order = $this->order($m);
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'UTR'.random_int(1000, 99999), 'status' => 'pending']);
        app(PaymentService::class)->verify($payment, $this->admin);

        return $order->fresh();
    }

    private function register(array $fields): void
    {
        $this->app['auth']->guard('member')->logout();
        $this->flushSession();
        $this->post(route('register.send'), $fields + ['name' => 'New Person']);
        if (! session('errors')) {
            $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);
        }
    }

    // ------------------------------------------------------------------------------------- Phase A: registration

    public function test_the_register_page_asks_which_plan_and_lists_only_active_plans_of_that_region(): void
    {
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $this->makePlan('Hidden Plan', 500, null, false);
        $this->makePlan('Gujarat Plan', 700, $other);
        $this->member('SPON1', null, 'active');

        $this->get(route('register', ['sponsor' => 'SPON1']))->assertOk()
            ->assertSee('Which plan do you want to buy?')->assertSee('name="plan_id"', false)
            ->assertSee('Essential')->assertSee('₹2,240')
            ->assertDontSee('Hidden Plan')->assertDontSee('Gujarat Plan');
    }

    public function test_a_plan_is_optional_so_a_link_without_a_product_still_registers(): void
    {
        $this->startRegion();
        // (This used to be required. The sponsor may now send a link with no product; the person chooses afterwards.)
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100001', 'region' => 'MAIN'])->assertSessionHasNoErrors();
    }

    public function test_a_plan_from_another_region_or_an_inactive_plan_is_refused(): void
    {
        $this->startRegion();
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $foreign = $this->makePlan('Gujarat Plan', 700, $other);
        $off = $this->makePlan('Switched Off', 500, null, false);

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100002', 'region' => 'MAIN', 'plan_id' => $foreign->id])->assertSessionHasErrors('plan_id');
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100002', 'region' => 'MAIN', 'plan_id' => $off->id])->assertSessionHasErrors('plan_id');
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100002', 'region' => 'MAIN', 'plan_id' => 99999])->assertSessionHasErrors('plan_id');
        $this->assertDatabaseMissing('members', ['phone' => '9811100002']);
    }

    public function test_a_region_with_no_plans_still_lets_people_register_without_one(): void
    {
        $this->startRegion();
        Plan::query()->delete();

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100003', 'region' => 'MAIN'])->assertSessionHasNoErrors();
    }

    public function test_after_registering_the_member_lands_on_the_payment_page_for_the_chosen_plan(): void
    {
        $this->startRegion();
        $this->register(['phone' => '9811100004', 'region' => 'MAIN', 'plan_id' => $this->plan->id]);

        $member = Member::where('phone', '9811100004')->firstOrFail();
        $this->assertSame($this->plan->id, $member->interested_plan_id);
        $order = Order::where('member_id', $member->id)->firstOrFail();
        $this->assertSame($this->plan->id, $order->plan_id);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('inactive', $member->status, 'Not active until the payment is verified');
    }

    public function test_the_chosen_plan_must_belong_to_the_region_of_the_sponsor_link(): void
    {
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $foreign = $this->makePlan('Gujarat Plan', 700, $other);
        $this->member('SPON2', null, 'active');

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100005', 'sponsor_code' => 'SPON2', 'plan_id' => $foreign->id])->assertSessionHasErrors('plan_id');
    }

    public function test_a_sponsor_link_locks_the_sponsor_code_on_the_form(): void
    {
        $this->member('SPON3', null, 'active');

        $this->get(route('register', ['sponsor' => 'SPON3', 'side' => 'left']))->assertOk()
            ->assertSee('readonly', false)
            ->assertSee('Invited by')->assertSee('SPON3');
    }

    public function test_a_typed_register_page_without_a_link_keeps_the_sponsor_field_editable(): void
    {
        $this->member('SPON4', null, 'active');

        $this->get(route('register'))->assertOk()->assertDontSee('readonly', false);
    }

    public function test_a_changed_sponsor_code_is_ignored_when_the_person_came_from_a_sponsor_link(): void
    {
        $linked = $this->member('SPON5', null, 'active');
        $this->member('SPON6', null, 'active');

        $this->get(route('register', ['sponsor' => 'SPON5', 'side' => 'right']));
        // Someone edits the form and posts another sponsor code and leg.
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100006', 'region' => 'MAIN', 'plan_id' => $this->plan->id, 'sponsor_code' => 'SPON6', 'side' => 'left']);
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        $member = Member::where('phone', '9811100006')->firstOrFail();
        $this->assertSame($linked->id, $member->sponsor_id, 'The link\'s sponsor wins over an edited form');
        $this->assertSame('right', $member->position);
    }

    public function test_the_member_dashboard_lets_the_sponsor_pick_a_plan_for_the_invite_link(): void
    {
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $this->makePlan('Gujarat Plan', 700, $other);
        $this->makePlan('Switched Off', 500, null, false);
        $sponsor = $this->member('SPON8', null, 'active');

        $this->actingAs($sponsor, 'member')->get(route('member.dashboard'))->assertOk()
            ->assertSee('Plan for the person you invite')->assertSee('Essential')
            ->assertSee('plan='.$this->plan->id, false)
            ->assertDontSee('Gujarat Plan')->assertDontSee('Switched Off');
    }

    public function test_a_link_with_a_plan_shows_it_locked_on_the_register_form(): void
    {
        $this->member('SPON9', null, 'active');

        $this->get(route('register', ['sponsor' => 'SPON9', 'plan' => $this->plan->id]))->assertOk()
            ->assertSee('Chosen by your sponsor')->assertSee('Essential')->assertSee('₹2,240')
            ->assertSee('name="plan_id" value="'.$this->plan->id.'"', false)
            ->assertDontSee('<select id="plan_id"', false);
    }

    public function test_the_plan_chosen_by_the_sponsor_cannot_be_swapped_by_editing_the_form(): void
    {
        $this->member('SPON10', null, 'active');
        $cheaper = $this->makePlan('Cheaper', 500);

        $this->get(route('register', ['sponsor' => 'SPON10', 'plan' => $this->plan->id]));
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9811100008', 'region' => 'MAIN', 'sponsor_code' => 'SPON10', 'plan_id' => $cheaper->id]);
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        $member = Member::where('phone', '9811100008')->firstOrFail();
        $this->assertSame($this->plan->id, $member->interested_plan_id);
        $this->assertSame($this->plan->id, Order::where('member_id', $member->id)->value('plan_id'));
    }

    public function test_a_link_with_an_unusable_plan_falls_back_to_letting_the_person_choose(): void
    {
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $foreign = $this->makePlan('Gujarat Plan', 700, $other);
        $off = $this->makePlan('Switched Off', 500, null, false);
        $this->member('SPON11', null, 'active');

        foreach ([$foreign->id, $off->id, 99999, 'abc'] as $bad) {
            $this->get(route('register', ['sponsor' => 'SPON11', 'plan' => $bad]))->assertOk()
                ->assertDontSee('Chosen by your sponsor')->assertSee('<select id="plan_id"', false);
        }
    }

    public function test_after_registering_the_payment_page_welcomes_and_summarises_the_plan(): void
    {
        $sponsor = $this->member('SPON7', null, 'active');
        $this->plan->products()->first()->update(['description' => 'A daily wellness syrup']);

        $this->register(['phone' => '9811100007', 'region' => 'MAIN', 'plan_id' => $this->plan->id, 'sponsor_code' => 'SPON7']);
        $order = Order::where('member_id', Member::where('phone', '9811100007')->value('id'))->firstOrFail();

        $this->get(route('member.orders.pay', $order))->assertOk()
            ->assertSee('Welcome')->assertSee('Name SPON7')
            ->assertSee('Syrup')->assertSee('A daily wellness syrup')
            ->assertSee('₹2,240');

        // The welcome shows once, not on every visit.
        $this->get(route('member.orders.pay', $order))->assertDontSee('Welcome');
    }

    // ------------------------------------------------------------------------------------- stages

    public function test_the_activation_stage_follows_the_purchase(): void
    {
        $m = $this->member('STG1');
        $this->assertSame('registered', $m->activationStage());

        $order = $this->order($m);
        $this->assertSame('order_placed', $m->fresh()->activationStage());

        $payment = Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'U1', 'status' => 'pending']);
        $this->assertSame('payment_submitted', $m->fresh()->activationStage());

        $payment->update(['status' => 'rejected']);
        $this->assertSame('order_placed', $m->fresh()->activationStage(), 'A rejected payment sends them back a step');

        $payment->update(['status' => 'pending']);
        app(PaymentService::class)->verify($payment->fresh(), $this->admin);
        $this->assertSame('active', $m->fresh()->activationStage());
        $this->assertSame('active', $m->fresh()->status);
    }

    public function test_a_cancelled_order_puts_someone_back_to_registered(): void
    {
        $m = $this->member('STG2');
        $this->order($m, 'cancelled');

        $this->assertSame('registered', $m->fresh()->activationStage());
    }

    // ------------------------------------------------------------------------------------- Phase B: downline visibility

    public function test_an_unpaid_invitee_shows_in_the_tree_labelled_not_active_and_the_admin_can_hide_them(): void
    {
        // The rule changed: unpaid people now show in the downline (labelled), and hiding them is the admin's choice.
        $sponsor = $this->member('SP1', null, 'active');
        $guest = $this->member('GUEST1', $sponsor);

        $this->actingAs($sponsor, 'member')->get(route('member.downline.index'))->assertOk()->assertSee('Name GUEST1')->assertSee('Not active');

        \App\Models\Setting::set('show_inactive_in_downline', 0);
        $tree = app(NetworkService::class)->buildTree($sponsor, 4, activeOnly: true);
        $this->assertNull($tree['left'], 'With the switch off, an unpaid invitee is hidden');
        $this->actingAs($sponsor, 'member')->get(route('member.downline.index'))->assertOk()->assertDontSee('Name GUEST1');

        $this->pay($guest);

        $tree = app(NetworkService::class)->buildTree($sponsor->fresh(), 4, activeOnly: true);
        $this->assertSame($guest->id, $tree['left']['member']->id);
        $this->actingAs($sponsor, 'member')->get(route('member.downline.index'))->assertSee('Name GUEST1');
    }

    public function test_the_admin_tree_still_shows_everyone_including_pending_people(): void
    {
        $sponsor = $this->member('SP2', null, 'active');
        $this->member('GUEST2', $sponsor);

        $tree = app(NetworkService::class)->buildTree($sponsor, 4);   // default = everyone
        $this->assertNotNull($tree['left']);
        $this->actingAs($this->admin, 'web')->get(route('admin.members.network', $sponsor))->assertOk()->assertSee('Name GUEST2');
    }

    public function test_a_pending_person_stays_visible_when_a_paid_member_sits_below_them(): void
    {
        $sponsor = $this->member('SP3', null, 'active');
        $pending = $this->member('MID1', $sponsor);
        $below = $this->member('LEAF1', $pending, 'active');

        $tree = app(NetworkService::class)->buildTree($sponsor, 4, activeOnly: true);

        $this->assertSame($pending->id, $tree['left']['member']->id, 'Kept so the paid member below stays connected');
        $this->assertSame($below->id, $tree['left']['left']['member']->id);
    }

    public function test_the_team_page_lists_unpaid_directs_labelled_and_the_admin_can_limit_it_to_paid_people(): void
    {
        $sponsor = $this->member('SP4', null, 'active');
        $paid = $this->member('PAID1', $sponsor, 'inactive', null, 'left');
        $this->pay($paid);
        $this->member('WAIT1', $sponsor, 'inactive', null, 'right');

        // Default: both are listed, the unpaid one labelled, and the level count includes both (one of them paid).
        $this->actingAs($sponsor, 'member')->get(route('member.team.index'))->assertOk()->assertSee('Name PAID1')->assertSee('Name WAIT1')->assertSee('Not active');
        $levels = app(NetworkService::class)->levelBreakdown($sponsor->fresh(), 3);
        $this->assertSame([2, 1], [$levels[0]['count'], $levels[0]['paid_count']]);

        // Admin switches "show non-active people" off: only the paid person counts and is listed.
        \App\Models\Setting::set('show_inactive_in_downline', 0);
        $this->actingAs($sponsor, 'member')->get(route('member.team.index'))->assertOk()->assertSee('Name PAID1')->assertDontSee('Name WAIT1');
        $levels = app(NetworkService::class)->levelBreakdown($sponsor->fresh(), 3, activeOnly: true);
        $this->assertSame(1, $levels[0]['count']);
    }

    public function test_the_dashboard_counts_only_paid_directs_and_points_to_the_pending_ones(): void
    {
        $sponsor = $this->member('SP5', null, 'active');
        $paid = $this->member('PAID2', $sponsor, 'inactive', null, 'left');
        $this->pay($paid);
        $this->member('WAIT2', $sponsor, 'inactive', null, 'right');
        $this->member('WAIT3', $sponsor, 'inactive', null, 'left');

        $res = $this->actingAs($sponsor, 'member')->get(route('member.dashboard'))->assertOk();

        $this->assertSame(1, $res->viewData('directCount'));
        $this->assertSame(2, $res->viewData('pendingCount'));
        $res->assertSee('2 people you invited have not paid yet', false)->assertSee(route('member.pending.index'), false);
    }

    public function test_the_getting_started_invite_step_needs_a_paid_direct(): void
    {
        $sponsor = $this->member('SP6', null, 'active');
        $this->member('WAIT4', $sponsor);

        $steps = collect(app(\App\Services\OnboardingService::class)->steps($sponsor->fresh()))->keyBy('key');
        $this->assertFalse($steps['invite']['done'], 'Someone who has not paid is not a real direct yet');

        $this->pay(Member::where('member_code', 'WAIT4')->first());
        $steps = collect(app(\App\Services\OnboardingService::class)->steps($sponsor->fresh()))->keyBy('key');
        $this->assertTrue($steps['invite']['done']);
    }

    // ------------------------------------------------------------------------------------- Phase C: member's pending page

    public function test_the_member_pending_page_shows_each_invitee_with_their_step_plan_and_a_reminder_link(): void
    {
        $sponsor = $this->member('SP7', null, 'active');
        $a = $this->member('REG1', $sponsor, 'inactive', $this->plan, 'left');
        $b = $this->member('ORD1', $sponsor, 'inactive', $this->plan, 'right');
        $this->order($b);
        $c = $this->member('PAY1', $sponsor, 'inactive', $this->plan, 'left');
        Payment::create(['order_id' => $this->order($c)->id, 'method' => 'upi', 'reference' => 'U9', 'status' => 'pending']);

        $res = $this->actingAs($sponsor, 'member')->get(route('member.pending.index'))->assertOk()
            ->assertSee('Waiting to activate')
            ->assertSee('Name REG1')->assertSee('Registered, no order yet')
            ->assertSee('Name ORD1')->assertSee('Order placed, payment pending')
            ->assertSee('Name PAY1')->assertSee('Payment sent, waiting for approval')
            ->assertSee('Essential');

        $res->assertSee('https://wa.me/91'.substr($a->phone, -10), false);
    }

    public function test_paid_people_are_not_on_the_pending_page_and_other_sponsors_invitees_are_never_shown(): void
    {
        $sponsor = $this->member('SP8', null, 'active');
        $other = $this->member('SP9', null, 'active');
        $mine = $this->member('MINE1', $sponsor);
        $this->pay($this->member('DONE1', $sponsor, 'inactive', null, 'right'));
        $this->member('THEIRS1', $other);

        $this->actingAs($sponsor, 'member')->get(route('member.pending.index'))
            ->assertSee('Name MINE1')->assertDontSee('Name DONE1')->assertDontSee('Name THEIRS1');
        $this->assertNotNull($mine);
    }

    public function test_the_pending_page_is_friendly_when_there_is_nobody_waiting(): void
    {
        $sponsor = $this->member('SP10', null, 'active');

        $this->actingAs($sponsor, 'member')->get(route('member.pending.index'))->assertOk()->assertSee('Nobody is waiting');
        $this->get(route('logout'))->assertStatus(405);
    }

    public function test_the_pending_page_needs_a_member_login(): void
    {
        $this->app['auth']->guard('member')->logout();
        $this->get(route('member.pending.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------------------------- Phase C: admin pipeline

    public function test_the_admin_pipeline_groups_people_by_step_with_counts(): void
    {
        $sponsor = $this->member('SPX', null, 'active');
        $this->member('REG2', $sponsor, 'inactive', $this->plan, 'left');
        $b = $this->member('ORD2', $sponsor, 'inactive', $this->plan, 'right');
        $this->order($b);
        $c = $this->member('PAY2', $sponsor, 'inactive', $this->plan, 'left');
        Payment::create(['order_id' => $this->order($c)->id, 'method' => 'upi', 'reference' => 'U7', 'status' => 'pending']);

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.activation.index'))->assertOk()
            ->assertSee('Pending activation')
            ->assertSee('Registered')->assertSee('Order placed')->assertSee('Payment submitted')
            ->assertSee('Name REG2')->assertSee('Name ORD2')->assertSee('Name PAY2')
            ->assertSee('Name SPX')                      // the sponsor who invited them
            ->assertDontSee('Name SPX</a>', false);       // ...but the sponsor is not listed as pending (they are active)

        $counts = $res->viewData('counts');
        $this->assertSame(['registered' => 1, 'order_placed' => 1, 'payment_submitted' => 1], array_intersect_key($counts, array_flip(['registered', 'order_placed', 'payment_submitted'])));
        $res->assertSee(route('admin.payments.index'), false);   // "Verify payment" for the ones who paid
    }

    public function test_active_people_never_appear_in_the_pipeline(): void
    {
        $sponsor = $this->member('SPY', null, 'active');
        $this->pay($this->member('DONE2', $sponsor));

        $this->actingAs($this->admin, 'web')->get(route('admin.activation.index'))->assertOk()->assertDontSee('Name DONE2');
    }

    public function test_the_pipeline_is_scoped_to_a_region_limited_sub_admins_regions_and_needs_members_access(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $mine = Member::create(['member_code' => 'GJ1', 'name' => 'Name GJ1', 'phone' => '9811100010', 'status' => 'inactive', 'region_id' => $gj->id]);
        $this->member('MAIN1');

        $sub = fn (array $perms, ?array $regions = null) => User::create(['name' => 'S', 'email' => 's'.random_int(1, 9999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);

        $this->actingAs($sub(['orders']), 'web')->get(route('admin.activation.index'))->assertForbidden();
        $this->actingAs($sub(['members'], [$gj->id]), 'web')->get(route('admin.activation.index'))->assertOk()->assertSee('Name GJ1')->assertDontSee('Name MAIN1');
        $this->assertNotNull($mine);
    }

    public function test_the_admin_menu_has_the_pipeline_link_with_a_count(): void
    {
        $this->member('M1');
        $this->member('M2');

        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('Pending activation')->assertSee(route('admin.activation.index'), false);
    }

    public function test_a_paid_member_earns_and_counts_normally_so_the_visibility_rule_changes_no_money(): void
    {
        $sponsor = $this->member('SPZ', null, 'active');
        $guest = $this->member('GZ', $sponsor);
        $this->pay($guest);

        $this->assertSame(0, LedgerEntry::count(), 'Visibility alone never posts or removes income');
        $this->assertSame('active', $guest->fresh()->status);
    }
}
