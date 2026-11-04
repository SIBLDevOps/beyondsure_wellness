<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\CycleService;
use App\Services\NetworkService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * People who have not paid yet ("non-active"): shown in the downline with a clear label, never paid sponsor or matching
 * income unless the admin says so, and the pending list tells the sponsor which level and which leg each one sits in.
 */
class NonActiveRulesTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private Plan $plan;

    private User $admin;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $prod = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($prod->id, ['qty' => 1]);
        $this->configureCompensation(['levels' => [10, 10, 10], 'circuit_breaker_pct' => 100, 'match_cap_per_cycle' => 10000000, 'match_cap_per_week' => 10000000]);
    }

    /** A member placed under $parent on $position, sponsored by $sponsor (default: the parent). */
    private function member(string $name, string $status = 'inactive', ?Member $parent = null, string $position = 'left', ?Member $sponsor = null): Member
    {
        $this->n++;

        return Member::create([
            'member_code' => 'NA'.$this->n, 'name' => $name, 'phone' => '94'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => $status,
            'region_id' => $this->region->id, 'sponsor_id' => ($sponsor ?? $parent)?->id, 'placement_id' => $parent?->id,
            'position' => $parent ? $position : null, 'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
        ]);
    }

    private function buy(Member $m): Order
    {
        $order = app(OrderService::class)->place($m, $this->plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);

        return $order->fresh();
    }

    private function runCycle(): Cycle
    {
        $cycle = app(CycleService::class)->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        app(CycleService::class)->approve($cycle, $this->admin);

        return $cycle->fresh();
    }

    private function sum(string $type, Member $m): float
    {
        return (float) LedgerEntry::where('type', $type)->where('member_id', $m->id)->sum('amount');
    }

    /**
     * T (active, top) has R (not active, never paid) under it, and R has two active buyers L and X, one on each leg.
     * R sponsored both of them. So R is owed sponsor income on both purchases and matching income on the pair.
     *
     * @return array{T: Member, R: Member, L: Member, X: Member}
     */
    private function scenario(string $rStatus = 'inactive'): array
    {
        $t = $this->member('Top Tina', 'active');
        $r = $this->member('Middle Rao', $rStatus, $t, 'left');
        $l = $this->member('Left Leena', 'inactive', $r, 'left');
        $x = $this->member('Right Xavi', 'inactive', $r, 'right');
        $this->buy($l);
        $this->buy($x);

        return ['T' => $t, 'R' => $r, 'L' => $l, 'X' => $x];
    }

    // ------------------------------------------------------------------------------------------ income: the default

    public function test_by_default_a_non_active_sponsor_earns_no_sponsor_income(): void
    {
        $s = $this->scenario();
        $this->runCycle();

        $this->assertEqualsWithDelta(0.0, $this->sum('sponsor', $s['R']), 0.001, 'Rao never paid, so no sponsor income');
        $this->assertSame(0, LedgerEntry::where('type', 'sponsor')->where('member_id', $s['R']->id)->count());
    }

    public function test_by_default_a_non_active_member_earns_no_matching_income_but_active_upline_still_does(): void
    {
        $s = $this->scenario();
        $this->runCycle();

        $this->assertEqualsWithDelta(0.0, $this->sum('matching', $s['R']), 0.001);
        $this->assertGreaterThan(0, $this->sum('matching', $s['T']), 'Tina is active, so her level-2 matching is paid as normal');
        $this->assertEqualsWithDelta(80.0, $this->sum('matching', $s['T']), 0.01);   // 10% of the 800 BV matched
    }

    public function test_the_buyers_own_income_is_never_affected(): void
    {
        $s = $this->scenario();
        $this->runCycle();

        $this->assertEqualsWithDelta(80.0, $this->sum('self', $s['L']), 0.01);
        $this->assertEqualsWithDelta(80.0, $this->sum('self', $s['X']), 0.01);
    }

    public function test_the_cycle_total_matches_what_was_actually_paid(): void
    {
        $this->scenario();
        $cycle = $this->runCycle();

        $this->assertEqualsWithDelta((float) LedgerEntry::where('cycle_id', $cycle->id)->where('amount', '>', 0)->sum('amount'), (float) $cycle->total_paid, 0.01);
    }

    public function test_an_active_sponsor_gets_everything_exactly_as_before(): void
    {
        $s = $this->scenario('active');
        $this->runCycle();

        $this->assertEqualsWithDelta(320.0, $this->sum('sponsor', $s['R']), 0.01);   // 20% of 800, twice
        $this->assertEqualsWithDelta(80.0, $this->sum('matching', $s['R']), 0.01);
        $this->assertEqualsWithDelta(80.0, $this->sum('matching', $s['T']), 0.01);
    }

    // ------------------------------------------------------------------------------------------ income: the admin's choice

    public function test_the_admin_can_let_non_active_members_earn_sponsor_income(): void
    {
        Setting::set('inactive_earns_sponsor', 1);
        $s = $this->scenario();
        $this->runCycle();

        $this->assertEqualsWithDelta(320.0, $this->sum('sponsor', $s['R']), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->sum('matching', $s['R']), 0.001, 'only sponsor income was switched on');
    }

    public function test_the_admin_can_let_non_active_members_earn_matching_income(): void
    {
        Setting::set('inactive_earns_matching', 1);
        $s = $this->scenario();
        $this->runCycle();

        $this->assertEqualsWithDelta(80.0, $this->sum('matching', $s['R']), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->sum('sponsor', $s['R']), 0.001, 'only matching income was switched on');
    }

    public function test_with_both_on_a_non_active_member_earns_both(): void
    {
        Setting::set('inactive_earns_sponsor', 1);
        Setting::set('inactive_earns_matching', 1);
        $s = $this->scenario();
        $this->runCycle();

        $this->assertEqualsWithDelta(320.0, $this->sum('sponsor', $s['R']), 0.01);
        $this->assertEqualsWithDelta(80.0, $this->sum('matching', $s['R']), 0.01);
    }

    public function test_switching_a_choice_off_again_stops_it_on_the_next_cycle(): void
    {
        Setting::set('inactive_earns_sponsor', 1);
        $s = $this->scenario();
        $this->runCycle();
        $this->assertGreaterThan(0, $this->sum('sponsor', $s['R']));

        Setting::set('inactive_earns_sponsor', 0);
        $before = $this->sum('sponsor', $s['R']);
        $this->buy($this->member('More Mira', 'inactive', $s['L'], 'left', $s['R']));
        $this->runCycle();

        $this->assertEqualsWithDelta($before, $this->sum('sponsor', $s['R']), 0.001, 'No new sponsor income once switched off');
    }

    public function test_a_member_who_becomes_active_before_the_cycle_is_paid_as_active(): void
    {
        $s = $this->scenario();
        $this->buy($s['R']);                 // Rao pays before the cycle is approved
        $this->runCycle();

        $this->assertEqualsWithDelta(320.0, $this->sum('sponsor', $s['R']), 0.01);
        $this->assertEqualsWithDelta(80.0, $this->sum('matching', $s['R']), 0.01);
    }

    public function test_nobody_is_ever_paid_a_negative_amount_in_any_mix_of_settings(): void
    {
        foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$sp, $ma]) {
            LedgerEntry::query()->delete();
            Order::query()->delete();
            Member::query()->delete();
            Setting::set('inactive_earns_sponsor', $sp);
            Setting::set('inactive_earns_matching', $ma);
            $this->scenario();
            $this->runCycle();

            $this->assertSame(0, LedgerEntry::whereIn('type', ['self', 'sponsor', 'matching', 'rank'])->where('amount', '<', 0)->count());
        }
    }

    // ------------------------------------------------------------------------------------------ the admin's switches

    private function settingsPayload(array $extra = []): array
    {
        return $extra + [
            'split_cost_pct' => 40, 'split_pool_pct' => 40, 'split_company_pct' => 20, 'self_pct' => 10, 'sponsor_pct' => 20,
            'rank_pool_pct' => 3, 'match_cap_per_cycle' => 600000, 'match_cap_per_week' => 150000, 'circuit_breaker_pct' => 60, 'levels' => [10, 10, 10],
        ];
    }

    public function test_the_settings_page_has_a_clear_section_for_non_active_members(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Non-active members')
            ->assertSee('name="inactive_earns_sponsor"', false)->assertSee('name="inactive_earns_matching"', false)->assertSee('name="show_inactive_in_downline"', false);
    }

    public function test_nothing_saved_means_safe_defaults(): void
    {
        $this->assertSame('0', (string) Setting::get('inactive_earns_sponsor', 0));
        $this->assertTrue(\App\Support\CompensationSettings::showInactiveInDownline());
        $this->assertFalse(\App\Support\CompensationSettings::inactiveEarnsSponsor());
        $this->assertFalse(\App\Support\CompensationSettings::inactiveEarnsMatching());
    }

    public function test_the_switches_save_and_an_unticked_box_means_off(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['inactive_earns_sponsor' => '1', 'inactive_earns_matching' => '1', 'show_inactive_in_downline' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(\App\Support\CompensationSettings::inactiveEarnsSponsor());
        $this->assertTrue(\App\Support\CompensationSettings::inactiveEarnsMatching());

        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload())->assertSessionHasNoErrors();
        $this->assertFalse(\App\Support\CompensationSettings::inactiveEarnsSponsor());
        $this->assertFalse(\App\Support\CompensationSettings::inactiveEarnsMatching());
        $this->assertFalse(\App\Support\CompensationSettings::showInactiveInDownline(), 'An unticked "show" box means hide');
    }

    public function test_only_staff_with_finance_access_can_change_them(): void
    {
        $ops = User::create(['name' => 'Ops', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members'], 'is_active' => true]);

        $this->actingAs($ops, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['inactive_earns_sponsor' => '1']))->assertForbidden();
        $this->assertFalse(\App\Support\CompensationSettings::inactiveEarnsSponsor());
    }

    public function test_the_guide_page_explains_the_rule(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.guide.index'))->assertOk()->assertSee('Non-active');
    }

    // ------------------------------------------------------------------------------------------ the downline shows them

    public function test_a_sponsor_sees_non_active_people_in_the_team_and_tree_with_a_clear_label(): void
    {
        $v = $this->member('Viewer Vik', 'active');
        $this->member('Unpaid Uma', 'inactive', $v, 'left');
        $this->member('Paid Pete', 'active', $v, 'right');

        $this->actingAs($v, 'member')->get(route('member.team.index'))->assertOk()->assertSee('Unpaid Uma')->assertSee('Paid Pete')->assertSee('Not active');
        $this->actingAs($v, 'member')->get(route('member.downline.index'))->assertOk()->assertSee('Unpaid Uma')->assertSee('Not active');
    }

    public function test_the_level_counts_include_them_and_say_how_many_are_active(): void
    {
        $v = $this->member('Viewer Vik', 'active');
        $this->member('Unpaid Uma', 'inactive', $v, 'left');
        $this->member('Paid Pete', 'active', $v, 'right');

        $levels = $this->actingAs($v, 'member')->get(route('member.levels.index'))->assertOk()->viewData('levels');

        $this->assertSame(2, $levels[0]['count']);
        $this->assertSame(1, $levels[0]['paid_count'], 'One of the two has paid');
    }

    public function test_a_non_active_person_with_paid_people_below_stays_connected(): void
    {
        $v = $this->member('Viewer Vik', 'active');
        $mid = $this->member('Unpaid Uma', 'inactive', $v, 'left');
        $this->member('Deep Dev', 'active', $mid, 'left');

        $levels = app(NetworkService::class)->levelBreakdown($v, 7);

        $this->assertSame([1, 1], array_column($levels, 'count'));
    }

    public function test_the_admin_can_hide_them_again_and_the_pending_list_is_unaffected(): void
    {
        Setting::set('show_inactive_in_downline', 0);
        $v = $this->member('Viewer Vik', 'active');
        $this->member('Unpaid Uma', 'inactive', $v, 'left');

        $this->actingAs($v, 'member')->get(route('member.team.index'))->assertOk()->assertDontSee('Unpaid Uma');
        $this->actingAs($v, 'member')->get(route('member.downline.index'))->assertOk()->assertDontSee('Unpaid Uma');
        $this->actingAs($v, 'member')->get(route('member.pending.index'))->assertOk()->assertSee('Unpaid Uma');
    }

    public function test_one_member_never_sees_another_members_non_active_people(): void
    {
        $a = $this->member('Viewer A', 'active');
        $b = $this->member('Viewer B', 'active');
        $this->member('Uma Of A', 'inactive', $a, 'left');

        $this->actingAs($b, 'member')->get(route('member.team.index'))->assertOk()->assertDontSee('Uma Of A');
        $this->actingAs($b, 'member')->get(route('member.downline.index'))->assertOk()->assertDontSee('Uma Of A');
        $this->actingAs($b, 'member')->get(route('member.pending.index'))->assertOk()->assertDontSee('Uma Of A');
    }

    // ------------------------------------------------------------------------------------------ pending: which level, which leg

    /** V (active) has X (active) on the left, Z (not active) on the right, and Y (not active) placed below X on the left. */
    private function pendingTree(): array
    {
        $v = $this->member('Viewer Vik', 'active');
        $x = $this->member('Paid Xena', 'active', $v, 'left');
        $z = $this->member('Right Zed', 'inactive', $v, 'right');
        $y = $this->member('Spill Yash', 'inactive', $x, 'left', $v);     // sponsored by V, but placed under X
        $q = $this->member('Deep Quin', 'inactive', $y, 'right', $v);       // sponsored by V, three levels down on the left

        return compact('v', 'x', 'z', 'y', 'q');
    }

    public function test_the_service_works_out_level_and_leg_from_the_viewer(): void
    {
        $t = $this->pendingTree();
        $found = app(NetworkService::class)->locate($t['v'], collect([$t['z'], $t['y'], $t['q'], $t['x']]));

        $this->assertSame(['level' => 1, 'leg' => 'right'], $found[$t['z']->id]);
        $this->assertSame(['level' => 2, 'leg' => 'left'], $found[$t['y']->id]);
        $this->assertSame(['level' => 3, 'leg' => 'left'], $found[$t['q']->id]);
        $this->assertSame(['level' => 1, 'leg' => 'left'], $found[$t['x']->id]);
    }

    public function test_a_deep_placement_on_the_right_is_level_and_leg_correct_too(): void
    {
        $t = $this->pendingTree();
        $w = $this->member('Right Wen', 'inactive', $t['z'], 'left', $t['v']);      // under Zed (V's right), on Zed's left
        $u = $this->member('Right Uma', 'inactive', $w, 'right', $t['v']);          // one more level down

        $found = app(NetworkService::class)->locate($t['v'], collect([$w, $u]));

        $this->assertSame(['level' => 2, 'leg' => 'right'], $found[$w->id], 'The leg is the viewer\'s leg, not the position under Zed');
        $this->assertSame(['level' => 3, 'leg' => 'right'], $found[$u->id]);
    }

    public function test_the_admin_list_copes_with_people_who_have_no_sponsor(): void
    {
        $this->member('Company Cara', 'inactive');            // registered under no sponsor at all

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.activation.index'))->assertOk()->assertSee('Company Cara');
        $this->assertNull($res->viewData('people')->first()->level);
    }

    public function test_someone_outside_the_viewers_subtree_has_no_level_or_leg(): void
    {
        $t = $this->pendingTree();
        $stranger = $this->member('Stranger Sam', 'inactive');

        $this->assertNull(app(NetworkService::class)->locate($t['v'], collect([$stranger]))[$stranger->id] ?? null);
    }

    public function test_the_members_pending_page_shows_level_and_leg_for_each_person(): void
    {
        $t = $this->pendingTree();

        $res = $this->actingAs($t['v'], 'member')->get(route('member.pending.index'))->assertOk()
            ->assertSee('Right Zed')->assertSee('Spill Yash')->assertSee('Deep Quin')
            ->assertSee('Level 1')->assertSee('Level 2')->assertSee('Level 3')
            ->assertSee('Left leg')->assertSee('Right leg');

        $byName = $res->viewData('people')->keyBy('name');
        $this->assertSame([1, 'right'], [$byName['Right Zed']->level, $byName['Right Zed']->leg]);
        $this->assertSame([2, 'left'], [$byName['Spill Yash']->level, $byName['Spill Yash']->leg]);
        $this->assertSame([3, 'left'], [$byName['Deep Quin']->level, $byName['Deep Quin']->leg]);
    }

    public function test_the_pending_page_also_says_who_each_person_is_placed_under(): void
    {
        $t = $this->pendingTree();

        $this->actingAs($t['v'], 'member')->get(route('member.pending.index'))->assertOk()->assertSee('Placed under')->assertSee('Paid Xena');
    }

    public function test_the_admin_pipeline_shows_level_and_leg_from_the_sponsor_too(): void
    {
        $t = $this->pendingTree();

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.activation.index'))->assertOk()
            ->assertSee('Level 2')->assertSee('Left leg')->assertSee('Right leg');
        $byName = $res->viewData('people')->keyBy('name');
        $this->assertSame([2, 'left'], [$byName['Spill Yash']->level, $byName['Spill Yash']->leg]);
        $this->assertNotNull($t['q']);
    }

    // ------------------------------------------------------------------------------------------ registering without a product

    private function register(array $fields, ?string $sponsorCode = null): void
    {
        $this->app['auth']->guard('member')->logout();
        $this->flushSession();
        if ($sponsorCode) {
            $this->get(route('register', ['sponsor' => $sponsorCode, 'side' => 'left']));
        }
        $this->post(route('register.send'), $fields + ['name' => 'New Nila']);
        if (! session('errors')) {
            $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);
        }
    }

    public function test_a_link_without_a_product_still_lets_the_person_register(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'active');

        $this->register(['phone' => '9822200001'], $sponsor->member_code);

        $member = Member::where('phone', '9822200001')->firstOrFail();
        $this->assertSame($sponsor->id, $member->sponsor_id);
        $this->assertNull($member->interested_plan_id);
        $this->assertSame(0, Order::where('member_id', $member->id)->count(), 'No product chosen, so no order yet');
        $this->assertSame('inactive', $member->status);
    }

    public function test_after_registering_without_a_product_they_land_on_the_plans_to_choose_one(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'active');
        $this->get(route('register', ['sponsor' => $sponsor->member_code]));
        $this->post(route('register.send'), ['name' => 'New Nila', 'phone' => '9822200002']);

        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')])->assertRedirect(route('member.plans.index'));
        $this->get(route('member.plans.index'))->assertOk()->assertSee('Essential')->assertSee('Buy now');
    }

    public function test_the_register_page_offers_the_product_as_a_choice_that_can_wait(): void
    {
        $this->startRegion();
        $this->get(route('register', ['region' => 'MAIN']))->assertOk()
            ->assertSee('Which plan do you want to buy?')->assertSee('Decide later')
            ->assertDontSee('id="plan_id" name="plan_id" required', false);
    }

    public function test_a_chosen_product_is_still_checked_and_a_locked_one_still_wins(): void
    {
        $this->startRegion();
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $foreign = Plan::create(['name' => 'Gujarat Plan', 'slug' => 'g', 'price' => 700, 'bv' => 280, 'is_active' => true, 'region_id' => $other->id]);

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9822200003', 'region' => 'MAIN', 'plan_id' => $foreign->id])->assertSessionHasErrors('plan_id');
        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9822200003', 'region' => 'MAIN', 'plan_id' => 99999])->assertSessionHasErrors('plan_id');
    }

    public function test_a_person_who_decided_later_can_buy_from_the_plans_page_and_is_activated_as_usual(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'active');
        $this->register(['phone' => '9822200004'], $sponsor->member_code);
        $member = Member::where('phone', '9822200004')->firstOrFail();

        $this->actingAs($member, 'member')->post(route('member.plans.buy', $this->plan))->assertRedirect();
        $order = Order::where('member_id', $member->id)->firstOrFail();
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR9'), $this->admin);

        $this->assertSame('active', $member->fresh()->status);
        $this->actingAs($member->fresh(), 'member')->get(route('member.dashboard'))->assertOk();
    }

    public function test_a_region_with_no_plans_and_no_choice_still_registers(): void
    {
        $this->startRegion();
        Plan::query()->delete();

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9822200005', 'region' => 'MAIN'])->assertSessionHasNoErrors();
    }
}
