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
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\CompensationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The root of a region's tree (the first member, who joined through the region's root link) is never outside the
 * level window: when a matching event happens deeper than the window reaches, the root is still paid. The negative
 * side is tested hard: switched off, a zero or invalid rate, an unpaid root, caps, two regions, broken data.
 *
 * The standard network: a chain G0 (root) > G1 > ... > Gn down the left, and two buyers X, Y under the last one. Their
 * legs match 800 BV, so the event is at the last chain member, and the window is the 7 nearest on the line (as the
 * screenshot shows: Gen 8 down to Gen 2 paid, Gen 1 and the root outside).
 */
class RootIncomeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Region $region;

    private int $n = 0;

    /** @var array<string, Member> */
    private array $m = [];

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
        // Seven levels with a different rate each, so every payee can be told apart: 10, 9, 8, 7, 6, 5, 4 per cent.
        $this->configureCompensation(['levels' => [10, 9, 8, 7, 6, 5, 4], 'circuit_breaker_pct' => 100, 'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'rank_pool_pct' => 0]);
    }

    private function member(string $name, ?Member $parent, string $position = 'left', ?Region $region = null): Member
    {
        $this->n++;
        $region ??= $this->region;

        return $this->m[$name] = Member::create([
            'member_code' => 'RT'.$this->n, 'name' => $name, 'phone' => '91'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive', 'region_id' => $region->id,
            'sponsor_id' => $parent?->id, 'placement_id' => $parent?->id, 'position' => $parent ? $position : null, 'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
        ]);
    }

    private function buy(Member $member, ?Plan $plan = null): void
    {
        $order = app(OrderService::class)->place($member, $plan ?? $this->plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);
    }

    /** G0..G$last down the left, X and Y under the last. Everyone buys, except those named in $unpaid. */
    private function chain(int $last, array $unpaid = [], ?Region $region = null, string $prefix = 'G'): void
    {
        $parent = null;
        for ($i = 0; $i <= $last; $i++) {
            $parent = $this->member($prefix.$i, $parent, 'left', $region);
        }
        $this->member($prefix.'X', $parent, 'left', $region);
        $this->member($prefix.'Y', $parent, 'right', $region);

        foreach (range(0, $last) as $i) {
            if (! in_array($prefix.$i, $unpaid, true)) {
                $this->buy($this->m[$prefix.$i]);
            }
        }
        $this->buy($this->m[$prefix.'X']);
        $this->buy($this->m[$prefix.'Y']);
    }

    private function cycle(): Cycle
    {
        $service = app(CycleService::class);
        $cycle = $service->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $service->approve($cycle, $this->admin);

        return $cycle->fresh();
    }

    private function matching(string $name): float
    {
        return (float) LedgerEntry::where('type', 'matching')->where('member_id', $this->m[$name]->id)->sum('amount');
    }

    private function totalMatching(): float
    {
        return (float) LedgerEntry::where('type', 'matching')->sum('amount');
    }

    // ------------------------------------------------------------------------------------------ the rule

    public function test_the_root_takes_the_last_of_the_seven_slots_and_the_nearest_upline_keep_the_rest(): void
    {
        $this->chain(8);
        $this->cycle();

        // 800 BV matched at G8: G8..G3 are levels 1..6 (10, 9, 8, 7, 6, 5 per cent), the root is level 7 (4 per cent).
        foreach (['G8' => 80, 'G7' => 72, 'G6' => 64, 'G5' => 56, 'G4' => 48, 'G3' => 40, 'G0' => 32] as $name => $amount) {
            $this->assertEqualsWithDelta($amount, $this->matching($name), 0.01, $name);
        }
        $this->assertSame(0.0, $this->matching('G2'), 'G2 gave up the seventh slot to the root');
        $this->assertSame(0.0, $this->matching('G1'), 'G1 is outside the window');
    }

    public function test_exactly_seven_people_are_paid_for_a_deep_event_never_eight(): void
    {
        $this->chain(8);
        $this->cycle();

        $this->assertSame(7, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->m['G8']->id)->distinct()->count('member_id'));
        $this->assertSame(7, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->m['G8']->id)->count(), 'One payment each');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('depths')]
    public function test_the_root_is_paid_however_deep_the_event_is(int $depth): void
    {
        $this->chain($depth);
        $this->cycle();

        $this->assertEqualsWithDelta(32.0, $this->matching('G0'), 0.01, "event at generation {$depth}");
        $this->assertSame(0.0, $this->matching('G1'), "G1 stays outside at generation {$depth}");
        $this->assertSame(0.0, $this->matching('G2'), "G2 stays outside at generation {$depth}");
        $this->assertSame(7, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->m['G'.$depth]->id)->count(), "seven payees at generation {$depth}");
    }

    public static function depths(): array
    {
        return ['8 (just outside)' => [8], '9' => [9], '12' => [12], '15' => [15]];
    }

    public function test_the_ledger_line_says_it_is_a_root_payment_and_names_its_level(): void
    {
        $this->chain(8);
        $this->cycle();

        $entry = LedgerEntry::where('type', 'matching')->where('member_id', $this->m['G0']->id)->firstOrFail();
        $this->assertStringContainsString('Root', $entry->description);
        $this->assertStringContainsString('level 7', strtolower($entry->description));
        $this->assertSame($this->m['G8']->id, $entry->source_member_id, 'It names the matching event it came from');
    }

    public function test_when_the_window_is_one_level_wide_the_root_is_not_added_on_top(): void
    {
        $this->configureCompensation(['levels' => [10], 'circuit_breaker_pct' => 100, 'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'rank_pool_pct' => 0]);
        $this->chain(8);
        $this->cycle();

        $this->assertEqualsWithDelta(80.0, $this->matching('G8'), 0.01);
        $this->assertSame(0.0, $this->matching('G0'), 'One slot only: the event member keeps it, the root does not make it two');
        $this->assertSame(1, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->m['G8']->id)->count());
    }

    public function test_a_root_inside_the_window_is_paid_once_at_its_normal_level_not_twice(): void
    {
        $this->chain(3);   // G3 > G2 > G1 > G0: the root is level 4, inside the 7-level window
        $this->cycle();

        $this->assertEqualsWithDelta(56.0, $this->matching('G0'), 0.01, 'Level 4 is 7% of 800, as for anyone else');
        $this->assertSame(1, LedgerEntry::where('type', 'matching')->where('member_id', $this->m['G0']->id)->count());
    }

    public function test_when_the_root_is_the_matched_member_it_is_paid_once_as_level_one(): void
    {
        $root = $this->member('Root', null);
        $this->member('A', $root, 'left');
        $this->member('B', $root, 'right');
        foreach (['Root', 'A', 'B'] as $name) {
            $this->buy($this->m[$name]);
        }
        $this->cycle();

        $this->assertEqualsWithDelta(80.0, $this->matching('Root'), 0.01);
        $this->assertSame(1, LedgerEntry::where('type', 'matching')->where('member_id', $root->id)->count());
    }

    public function test_nobody_is_paid_twice_for_the_same_event(): void
    {
        $this->chain(10);
        $this->cycle();

        $dupes = LedgerEntry::where('type', 'matching')->selectRaw('member_id, source_member_id, count(*) c')->groupBy('member_id', 'source_member_id')->having('c', '>', 1)->count();
        $this->assertSame(0, $dupes);
    }

    // ------------------------------------------------------------------------------------------ negative: switched off or zero

    public function test_with_the_rule_switched_off_the_root_is_outside_the_window_as_before(): void
    {
        Setting::set('root_matching_enabled', 0);
        $this->chain(8);
        $this->cycle();

        $this->assertSame(0.0, $this->matching('G0'));
        $this->assertSame(0.0, $this->matching('G1'));
        $this->assertEqualsWithDelta(32.0, $this->matching('G2'), 0.01, 'The seventh slot goes back to G2');
        $this->assertEqualsWithDelta(80.0, $this->matching('G8'), 0.01, 'Everyone else is unaffected');
        $this->assertSame(7, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->m['G8']->id)->count());
    }

    public function test_there_is_no_separate_root_rate_any_more(): void
    {
        $this->assertFalse(method_exists(CompensationSettings::class, 'rootMatchingPct'), 'The root is paid the rate of the slot it holds');

        Setting::set('root_matching_pct', 50);   // a leftover value from the old design must change nothing
        $this->chain(8);
        $this->cycle();

        $this->assertEqualsWithDelta(32.0, $this->matching('G0'), 0.01);
    }

    public function test_the_helper_reads_the_switch_exactly(): void
    {
        $this->assertTrue(CompensationSettings::rootAlwaysPaid(), 'On unless switched off');

        Setting::set('root_matching_enabled', 0);
        $this->assertFalse(CompensationSettings::rootAlwaysPaid());
        Setting::set('root_matching_enabled', 1);
        $this->assertTrue(CompensationSettings::rootAlwaysPaid());
    }

    // ------------------------------------------------------------------------------------------ negative: the root's own status and limits

    public function test_a_root_who_has_not_paid_earns_nothing_unless_the_admin_allows_non_active_matching(): void
    {
        $this->chain(8, ['G0']);
        $this->cycle();
        $this->assertSame(0.0, $this->matching('G0'), 'Not active, and the non-active rule is off');
        $this->assertSame(0.0, $this->matching('G2'), 'The slot stays the root\'s: it is not handed to anyone else');
        $this->assertSame(6, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->m['G8']->id)->count());
    }

    public function test_an_unpaid_root_is_paid_when_the_admin_allows_non_active_members_to_earn_matching(): void
    {
        Setting::set('inactive_earns_matching', 1);
        $this->chain(8, ['G0']);
        $this->cycle();

        $this->assertEqualsWithDelta(32.0, $this->matching('G0'), 0.01);
        $this->assertSame(0.0, $this->matching('G2'));
    }

    public function test_the_weekly_and_monthly_caps_apply_to_the_root_too(): void
    {
        Setting::set('match_cap_per_week', 20);
        $this->chain(8);
        $this->cycle();
        $this->assertEqualsWithDelta(20.0, $this->matching('G0'), 0.01, 'Capped at 20, not 32');

        LedgerEntry::query()->delete();
        Order::query()->delete();
        Member::query()->delete();
        \App\Models\MemberLegTotal::query()->delete();
        \App\Models\MemberWeeklyTotal::query()->delete();
        Cycle::query()->delete();
        Setting::set('match_cap_per_week', 0);
        Setting::set('match_cap_per_cycle', 25);
        $this->chain(8);
        $this->cycle();
        $this->assertEqualsWithDelta(25.0, $this->matching('G0'), 0.01);
    }

    public function test_the_circuit_breaker_scales_everyone_including_the_root_and_never_overpays(): void
    {
        Setting::set('circuit_breaker_pct', 5);   // 5% of the layer's 1,600 new BV = 80 to share between all payees
        $this->chain(8);
        $cycle = $this->cycle();

        $this->assertLessThanOrEqual(80.05, $this->totalMatching());
        $this->assertGreaterThan(0, $this->matching('G0'));
        $this->assertLessThanOrEqual((float) $cycle->pool_bv, (float) $cycle->total_paid, 'Total paid never exceeds the pool');
    }

    public function test_the_total_paid_never_exceeds_the_pool_even_with_big_rates(): void
    {
        $this->configureCompensation(['levels' => [100, 100, 100, 100, 100, 100, 100], 'circuit_breaker_pct' => 100, 'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'self_pct' => 40, 'sponsor_pct' => 40]);
        $this->chain(8);
        $cycle = $this->cycle();

        $this->assertLessThanOrEqual((float) $cycle->pool_bv + 0.01, (float) $cycle->total_paid);
        $this->assertSame(0, LedgerEntry::whereIn('type', ['self', 'sponsor', 'matching', 'rank'])->where('amount', '<', 0)->count());
    }

    // ------------------------------------------------------------------------------------------ negative: regions and broken data

    public function test_each_regions_root_is_paid_only_from_its_own_regions_events(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $gjPlan = Plan::create(['name' => 'GJ Plan', 'slug' => 'gj', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $gj->id]);
        $gjPlan->products()->attach(Product::first()->id, ['qty' => 1]);
        $this->chain(8);
        // A Gujarat chain, bought with the Gujarat plan.
        $parent = null;
        foreach (range(0, 8) as $i) {
            $parent = $this->member('J'.$i, $parent, 'left', $gj);
        }
        $this->member('JX', $parent, 'left', $gj);
        $this->member('JY', $parent, 'right', $gj);
        foreach (array_merge(array_map(fn ($i) => 'J'.$i, range(0, 8)), ['JX', 'JY']) as $name) {
            $this->buy($this->m[$name], $gjPlan);
        }
        $this->cycle();

        $this->assertEqualsWithDelta(32.0, $this->matching('G0'), 0.01, 'The Main root is paid once, from the Main event only');
        $this->assertEqualsWithDelta(32.0, $this->matching('J0'), 0.01, 'The Gujarat root is paid once, from the Gujarat event only');
        $crossed = LedgerEntry::whereNotNull('source_member_id')->get()->filter(fn ($e) => Member::find($e->member_id)->region_id !== Member::find($e->source_member_id)->region_id);
        $this->assertCount(0, $crossed, 'Income never crosses a region');
    }

    public function test_a_broken_path_never_crashes_the_cycle_and_pays_nobody_wrongly(): void
    {
        $this->chain(8);
        // Corrupt the matched member's path so it names a root that does not exist.
        Member::where('id', $this->m['G8']->id)->update(['path' => '99999.'.$this->m['G7']->id.'.']);

        $this->cycle();

        $this->assertSame(0.0, $this->matching('G0'), 'The root cannot be found, so no root payment is invented');
        $this->assertSame('approved', Cycle::first()->status);
    }

    public function test_a_member_with_no_path_and_a_parent_does_not_crash_the_cycle(): void
    {
        $this->chain(8);
        Member::where('id', $this->m['G8']->id)->update(['path' => null]);

        $this->cycle();

        $this->assertSame('approved', Cycle::first()->status);
        $this->assertSame(0.0, $this->matching('G0'));
    }

    public function test_the_root_gets_nothing_from_a_network_that_has_no_matching_event(): void
    {
        $root = $this->member('Root', null);
        $a = $this->member('A', $root, 'left');
        $this->buy($root);
        $this->buy($a);          // only one leg: nothing to match
        $this->cycle();

        $this->assertSame(0.0, $this->totalMatching());
    }

    // ------------------------------------------------------------------------------------------ the root is the member the admin created

    private function join(array $fields, array $linkQuery): Member
    {
        $this->app['auth']->guard('member')->logout();
        $this->flushSession();
        $this->get(route('register', $linkQuery));
        $this->post(route('register.send'), $fields + ['name' => 'Joiner']);
        $this->assertEmpty(session('errors')?->all() ?? [], json_encode(session('errors')?->all()));
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        return Member::where('phone', $fields['phone'])->firstOrFail();
    }

    public function test_the_member_the_admin_creates_for_a_region_is_its_root(): void
    {
        // (The root used to be whoever signed up first through the region link. Now only the admin can start a region.)
        $first = $this->startRegion($this->region, 'Region Root', '9833300001');

        $this->assertNull($first->placement_id);
        $this->assertNull($first->sponsor_id);
        $this->assertNull($first->path);
        $this->assertTrue($this->region->fresh()->root()->is($first));
    }

    public function test_later_people_through_the_same_region_link_are_not_the_root_and_land_under_it(): void
    {
        $first = $this->startRegion($this->region, 'Region Root', '9833300002');
        $second = $this->join(['phone' => '9833300003', 'region' => 'MAIN'], ['region' => 'MAIN']);
        $third = $this->join(['phone' => '9833300004', 'region' => 'MAIN'], ['region' => 'MAIN']);

        $this->assertTrue($this->region->fresh()->root()->is($first), 'Still the first person');
        $this->assertSame($first->id, $second->placement_id);
        $this->assertSame($first->id, $third->placement_id);
        $this->assertNotNull($second->path);
        $this->assertSame(1, Member::whereNull('placement_id')->where('region_id', $this->region->id)->count(), 'There is only ever one root per region');
    }

    public function test_a_person_with_a_sponsor_can_never_become_a_root(): void
    {
        $root = $this->startRegion($this->region, 'Region Root', '9833300005');
        $child = $this->join(['phone' => '9833300006', 'sponsor_code' => $root->member_code], ['sponsor' => $root->member_code, 'side' => 'right']);

        $this->assertSame($root->id, $child->placement_id);
        $this->assertSame('right', $child->position);
        $this->assertFalse($this->region->fresh()->root()->is($child));
    }

    public function test_a_second_region_gets_its_own_root_started_by_the_admin(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $main = $this->startRegion($this->region, 'Main Root', '9833300007');
        $guj = $this->startRegion($gj, 'Gujarat Root', '9833300008');
        $joiner = $this->join(['phone' => '9833300009', 'region' => 'GJ'], ['region' => 'GJ']);

        $this->assertTrue($this->region->fresh()->root()->is($main));
        $this->assertTrue($gj->fresh()->root()->is($guj));
        $this->assertNull($guj->placement_id, 'A different region starts its own tree');
        $this->assertSame($guj->id, $joiner->placement_id, 'A public sign-up to Gujarat lands under the Gujarat root, not the Main one');
    }

    // ------------------------------------------------------------------------------------------ the admin's controls

    private function settingsPayload(array $extra = []): array
    {
        return $extra + [
            'split_cost_pct' => 40, 'split_pool_pct' => 40, 'split_company_pct' => 20, 'self_pct' => 10, 'sponsor_pct' => 20,
            'rank_pool_pct' => 3, 'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'circuit_breaker_pct' => 60, 'levels' => [10, 9, 8, 7, 6, 5, 4],
        ];
    }

    public function test_the_settings_page_has_the_root_switch_and_no_rate_with_an_explanation(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('id="sec-root"', false)->assertSee('name="root_matching_enabled"', false)->assertDontSee('name="root_matching_pct"', false)
            ->assertSee('Top of the tree')->assertSee('last of the');
    }

    public function test_the_root_choice_saves_and_shows_again(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['root_section' => 1, 'root_matching_enabled' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(CompensationSettings::rootAlwaysPaid());

        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['root_section' => 1]))->assertSessionHasNoErrors();
        $this->assertFalse(CompensationSettings::rootAlwaysPaid(), 'An unticked box on the real form means off');
    }

    public function test_a_save_that_does_not_send_the_root_section_leaves_the_choice_alone(): void
    {
        Setting::set('root_matching_enabled', 0);

        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload())->assertSessionHasNoErrors();

        $this->assertFalse(CompensationSettings::rootAlwaysPaid());
    }

    public function test_a_posted_root_rate_is_ignored_and_never_saved(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['root_section' => 1, 'root_matching_enabled' => '1', 'root_matching_pct' => '99']))->assertSessionHasNoErrors();

        $this->assertNull(Setting::get('root_matching_pct'));
    }

    public function test_only_staff_with_finance_access_can_change_the_root_rule(): void
    {
        $ops = User::create(['name' => 'Ops', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members'], 'is_active' => true]);

        $this->actingAs($ops, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['root_section' => 1]))->assertForbidden();

        $this->assertTrue(CompensationSettings::rootAlwaysPaid(), 'Untouched');
    }

    public function test_changing_the_root_rule_is_in_the_audit_log_with_old_and_new_values(): void
    {
        Setting::set('root_matching_enabled', 1);
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['root_section' => 1]))->assertSessionHasNoErrors();

        $row = \App\Models\AuditLog::where('subject', 'Setting: root_matching_enabled')->firstOrFail();
        $this->assertSame('1 → 0', $row->details);
    }

    public function test_the_business_guide_explains_the_root_rule(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.guide.index'))->assertOk()->assertSee('Root');
    }
}
