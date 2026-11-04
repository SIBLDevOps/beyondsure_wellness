<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Rank;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every setting on the admin Settings page, changed through the real form and then followed into the real money:
 * a cycle is run and the figures are checked against what the setting says. Also: saving, showing, validating.
 *
 * The network: R0 (top) > R1 > R2 down the left side, and R2 has two buyers X (left) and Y (right).
 * Everyone bought one plan worth 800 BV, so R2's two legs match 800 and R2, R1 and R0 are paid up the line.
 */
class SettingsEffectTest extends TestCase
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
    }

    /** What an admin presses Save on: every field of the page, with sensible values, and $over changing some. */
    private function payload(array $over = []): array
    {
        return $over + [
            'split_cost_pct' => 40, 'split_pool_pct' => 40, 'split_company_pct' => 20,
            'self_pct' => 10, 'sponsor_pct' => 20, 'rank_pool_pct' => 0,
            'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'circuit_breaker_pct' => 100,
            'levels' => [10, 10, 10], 'show_inactive_in_downline' => '1',
        ];
    }

    private function save(array $over = [])
    {
        return $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->payload($over));
    }

    private function member(string $name, ?Member $parent, string $position = 'left'): Member
    {
        $this->n++;

        return $this->m[$name] = Member::create([
            'member_code' => 'SE'.$this->n, 'name' => $name, 'phone' => '93'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive', 'region_id' => $this->region->id,
            'sponsor_id' => $parent?->id, 'placement_id' => $parent?->id, 'position' => $parent ? $position : null, 'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
        ]);
    }

    private function buy(Member $m): void
    {
        $order = app(OrderService::class)->place($m, $this->plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);
    }

    private function build(): void
    {
        $r0 = $this->member('R0', null);
        $r1 = $this->member('R1', $r0);
        $r2 = $this->member('R2', $r1);
        $x = $this->member('X', $r2, 'left');
        $y = $this->member('Y', $r2, 'right');
        foreach ([$r0, $r1, $r2, $x, $y] as $member) {
            $this->buy($member);
        }
    }

    private function cycle(): void
    {
        // Through the service, built fresh each time: inside one test Laravel keeps the first request's controller (and the
        // settings its engine read) alive, which would hide a setting changed between two runs. A real request is always fresh.
        $service = app(\App\Services\CycleService::class);
        $cycle = $service->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $service->approve($cycle, $this->admin);
    }

    private function earned(string $type, string $name): float
    {
        return (float) LedgerEntry::where('type', $type)->where('member_id', $this->m[$name]->id)->sum('amount');
    }

    private function total(string $type): float
    {
        return (float) LedgerEntry::where('type', $type)->sum('amount');
    }

    private function runWith(array $over = []): void
    {
        $this->save($over)->assertSessionHasNoErrors();
        $this->build();
        $this->cycle();
    }

    // ------------------------------------------------------------------------------------------ the rates

    public function test_self_income_percentage_is_what_the_buyer_earns_on_their_own_purchase(): void
    {
        $this->runWith(['self_pct' => 15]);

        foreach (['R0', 'R1', 'R2', 'X', 'Y'] as $name) {
            $this->assertEqualsWithDelta(120.0, $this->earned('self', $name), 0.01, "{$name}: 15% of 800");
        }
    }

    public function test_changing_self_income_to_zero_pays_none(): void
    {
        $this->runWith(['self_pct' => 0]);

        $this->assertSame(0.0, $this->total('self'));
    }

    public function test_sponsor_income_percentage_is_what_a_sponsor_earns_on_each_direct_purchase(): void
    {
        $this->runWith(['sponsor_pct' => 25]);

        $this->assertEqualsWithDelta(200.0, $this->earned('sponsor', 'R0'), 0.01);   // R0 sponsored R1
        $this->assertEqualsWithDelta(200.0, $this->earned('sponsor', 'R1'), 0.01);
        $this->assertEqualsWithDelta(400.0, $this->earned('sponsor', 'R2'), 0.01);   // R2 sponsored X and Y
    }

    public function test_level_percentages_set_how_much_each_level_above_a_match_earns(): void
    {
        $this->runWith(['levels' => [10, 5, 2.5]]);

        $this->assertEqualsWithDelta(80.0, $this->earned('matching', 'R2'), 0.01, 'level 1: 10% of 800');
        $this->assertEqualsWithDelta(40.0, $this->earned('matching', 'R1'), 0.01, 'level 2: 5%');
        $this->assertEqualsWithDelta(20.0, $this->earned('matching', 'R0'), 0.01, 'level 3: 2.5%');
    }

    public function test_the_number_of_levels_saved_limits_how_far_up_matching_reaches(): void
    {
        Setting::set('root_matching_enabled', 0);   // the root rule is tested on its own; here only the window matters
        $this->runWith(['levels' => [10, 5]]);

        $this->assertEqualsWithDelta(80.0, $this->earned('matching', 'R2'), 0.01);
        $this->assertEqualsWithDelta(40.0, $this->earned('matching', 'R1'), 0.01);
        $this->assertSame(0.0, $this->earned('matching', 'R0'), 'Only two levels are configured, so level 3 earns nothing');
        $this->assertSame('2', (string) Setting::get('cascade_depth'));
        $this->assertSame('10', (string) Setting::get('matching_pct'), 'The first level rate is also kept as the base rate');
    }

    public function test_the_root_is_still_paid_beyond_the_levels_saved_unless_that_is_switched_off(): void
    {
        $this->runWith(['levels' => [10, 5]]);   // R0 is the root, one level outside the two-level window

        $this->assertEqualsWithDelta(80.0, $this->earned('matching', 'R2'), 0.01);
        $this->assertEqualsWithDelta(40.0, $this->earned('matching', 'R0'), 0.01, 'The root takes the second slot: level 2 is 5% of 800');
        $this->assertSame(0.0, $this->earned('matching', 'R1'), 'R1 would have had that slot; the root is never outside the window');
    }

    public function test_the_rank_pool_percentage_sets_the_size_of_the_rank_pool(): void
    {
        Rank::query()->delete();
        Rank::create(['name' => 'Bronze', 'sort_order' => 1, 'matched_bv_threshold' => 100, 'min_active_directs' => 0, 'share' => 1]);
        $this->runWith(['rank_pool_pct' => 2]);

        $this->assertEqualsWithDelta(80.0, $this->total('rank'), 0.02, '2% of the cycle\'s 4,000 BV');
        $this->assertEqualsWithDelta(80.0, $this->earned('rank', 'R2'), 0.02, 'R2 is the only one with matched volume');
    }

    public function test_a_rank_pool_of_zero_pays_nothing(): void
    {
        Rank::query()->delete();
        Rank::create(['name' => 'Bronze', 'sort_order' => 1, 'matched_bv_threshold' => 100, 'min_active_directs' => 0, 'share' => 1]);
        $this->runWith(['rank_pool_pct' => 0]);

        $this->assertSame(0.0, $this->total('rank'));
    }

    // ------------------------------------------------------------------------------------------ the safety limits

    public function test_the_circuit_breaker_caps_what_one_layer_pays_as_a_share_of_its_new_volume(): void
    {
        $this->runWith(['circuit_breaker_pct' => 100]);
        $uncapped = $this->total('matching');
        $this->assertEqualsWithDelta(240.0, $uncapped, 0.02, 'three levels at 10% of the 800 BV match');

        LedgerEntry::query()->delete();
        \App\Models\Order::query()->delete();
        Member::query()->delete();
        \App\Models\MemberLegTotal::query()->delete();
        Cycle::query()->delete();

        $this->runWith(['circuit_breaker_pct' => 5]);   // 5% of this layer's 1,600 new BV = 80
        $this->assertSame('5', (string) Setting::get('circuit_breaker_pct'), 'the form stored the new limit');
        $this->assertSame(5, \App\Models\LedgerEntry::where('type', 'self')->count(), 'a clean second run: five buyers, five self incomes');
        $this->assertEqualsWithDelta(80.0, $this->total('matching'), 0.05);
    }

    public function test_the_weekly_cap_limits_matching_per_member(): void
    {
        $this->runWith(['match_cap_per_week' => 50, 'levels' => [10, 5, 2.5]]);

        $this->assertEqualsWithDelta(50.0, $this->earned('matching', 'R2'), 0.01, 'R2 would have earned 80');
        $this->assertEqualsWithDelta(40.0, $this->earned('matching', 'R1'), 0.01, 'R1 is under the cap');
        $this->assertEqualsWithDelta(20.0, $this->earned('matching', 'R0'), 0.01, 'R0 is under the cap');
    }

    public function test_the_monthly_cap_limits_matching_per_member(): void
    {
        $this->runWith(['match_cap_per_cycle' => 60, 'levels' => [10, 5, 2.5]]);

        $this->assertEqualsWithDelta(60.0, $this->earned('matching', 'R2'), 0.01);
        $this->assertEqualsWithDelta(40.0, $this->earned('matching', 'R1'), 0.01);
    }

    public function test_a_cap_of_zero_means_no_cap(): void
    {
        $this->runWith(['match_cap_per_week' => 0, 'match_cap_per_cycle' => 0]);

        $this->assertEqualsWithDelta(80.0, $this->earned('matching', 'R2'), 0.01);
    }

    public function test_the_matching_eligibility_minimum_forfeits_a_layer_that_is_too_small(): void
    {
        // R2 is layer 2 and generated 1,600 new BV: a minimum of 5,000 for that layer means its match is not paid.
        $this->runWith(['eligibility' => [0, 5000, 0, 0, 0, 0, 0], 'eligibility_fallback' => 0]);

        $this->assertSame(0.0, $this->total('matching'));
    }

    public function test_a_minimum_that_is_met_pays_as_normal_and_the_fallback_covers_deeper_layers(): void
    {
        $this->runWith(['eligibility' => [0, 1000], 'eligibility_fallback' => 0, 'levels' => [10, 5]]);
        $this->assertEqualsWithDelta(120.0, $this->total('matching'), 0.02, '1,600 new BV meets the 1,000 minimum: two slots, 80 to R2 and 40 to the root (which took the second slot from R1)');

        LedgerEntry::query()->delete();
        \App\Models\Order::query()->delete();
        Member::query()->delete();
        \App\Models\MemberLegTotal::query()->delete();
        Cycle::query()->delete();

        $this->runWith(['eligibility' => [0], 'eligibility_fallback' => 9999]);   // layer 2 has no entry, so the fallback applies
        $this->assertSame(0.0, $this->total('matching'));
    }

    // ------------------------------------------------------------------------------------------ saving and showing

    public function test_the_page_has_fields_for_the_eligibility_minimums_and_shows_what_was_saved(): void
    {
        $this->save(['eligibility' => [100, 250, 0, 0, 0, 0, 0], 'eligibility_fallback' => 75])->assertSessionHasNoErrors();

        $this->assertSame(['1' => 100.0, '2' => 250.0, '3' => 0.0, '4' => 0.0, '5' => 0.0, '6' => 0.0, '7' => 0.0, 'fallback' => 75.0], Setting::getEligibilityMinVolumes());

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('name="eligibility[]"', false)->assertSee('name="eligibility_fallback"', false);
        $this->assertSame([100.0, 250.0, 0.0, 0.0, 0.0, 0.0, 0.0], array_values($res->viewData('eligibility')['layers']));
        $this->assertSame(75.0, $res->viewData('eligibility')['fallback']);
    }

    public function test_saving_the_page_does_not_wipe_eligibility_minimums_that_it_did_not_send(): void
    {
        Setting::setEligibilityMinVolumes([100, 250], 75);

        $payload = $this->payload();
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();   // no eligibility fields at all

        $this->assertSame(250.0, Setting::getEligibilityMinVolumes()['2'], 'An older form or a script that leaves them out must not erase them');
    }

    public function test_every_saved_value_comes_back_on_the_page(): void
    {
        $this->save([
            'split_cost_pct' => 35, 'split_pool_pct' => 45, 'split_company_pct' => 20, 'self_pct' => 12, 'sponsor_pct' => 18, 'rank_pool_pct' => 4,
            'match_cap_per_cycle' => 250000, 'match_cap_per_week' => 60000, 'circuit_breaker_pct' => 55, 'levels' => [9, 8, 7, 6],
        ])->assertSessionHasNoErrors();

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk();
        $values = $res->viewData('values');

        $this->assertEquals([35, 45, 20, 12, 18, 4, 250000, 60000, 55], [
            $values['split_cost_pct'], $values['split_pool_pct'], $values['split_company_pct'], $values['self_pct'], $values['sponsor_pct'],
            $values['rank_pool_pct'], $values['match_cap_per_cycle'], $values['match_cap_per_week'], $values['circuit_breaker_pct'],
        ]);
        $this->assertSame([1 => 9.0, 2 => 8.0, 3 => 7.0, 4 => 6.0], $res->viewData('levelPcts'));
    }

    public function test_a_fresh_install_shows_the_recommended_starting_values_not_zeros(): void
    {
        $values = $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()->viewData('values');

        $this->assertEquals([40, 40, 20, 10, 20, 3, 600000, 150000, 60], [
            $values['split_cost_pct'], $values['split_pool_pct'], $values['split_company_pct'], $values['self_pct'], $values['sponsor_pct'],
            $values['rank_pool_pct'], $values['match_cap_per_cycle'], $values['match_cap_per_week'], $values['circuit_breaker_pct'],
        ], 'Pressing Save on a fresh page must not store zeros');
    }

    public function test_an_unticked_box_is_saved_as_off_and_a_ticked_one_as_on(): void
    {
        $this->save(['require_sponsor' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('1', (string) Setting::get('require_sponsor'));

        $this->save()->assertSessionHasNoErrors();
        $this->assertSame('0', (string) Setting::get('require_sponsor'));
    }

    // ------------------------------------------------------------------------------------------ checking the numbers

    public function test_a_save_that_fails_changes_nothing_at_all(): void
    {
        $this->save(['self_pct' => 10, 'levels' => [10, 10, 10]])->assertSessionHasNoErrors();

        // Self income would change to 99, but the split is wrong, so nothing may be saved (not even the valid parts).
        $this->save(['self_pct' => 99, 'split_cost_pct' => 50, 'levels' => [1, 2]])->assertSessionHasErrors('split_pool_pct');

        $this->assertSame('10', (string) Setting::get('self_pct'));
        $this->assertSame([1 => 10.0, 2 => 10.0, 3 => 10.0], Setting::getLevelPercentages());
    }

    public function test_impossible_numbers_are_refused_with_a_message(): void
    {
        foreach ([
            ['self_pct' => -1], ['self_pct' => 101], ['self_pct' => 'abc'], ['sponsor_pct' => 150], ['rank_pool_pct' => -5],
            ['circuit_breaker_pct' => 101], ['match_cap_per_cycle' => -1], ['match_cap_per_week' => 'x'],
            ['levels' => [10, 120]], ['levels' => [10, -2]], ['levels' => array_fill(0, 26, 1)],
            ['eligibility' => [-5]], ['eligibility_fallback' => -1],
        ] as $bad) {
            $this->save($bad)->assertSessionHasErrors();
        }
        $this->assertNull(Setting::get('self_pct'), 'None of the refused saves stored anything');
    }

    public function test_a_required_field_left_empty_is_refused(): void
    {
        foreach (['split_cost_pct', 'split_pool_pct', 'split_company_pct', 'self_pct', 'sponsor_pct', 'rank_pool_pct', 'match_cap_per_cycle', 'match_cap_per_week', 'circuit_breaker_pct'] as $field) {
            $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), array_diff_key($this->payload(), [$field => 1]))->assertSessionHasErrors($field);
        }
    }

    public function test_paying_out_more_than_100_percent_of_the_volume_is_saved_with_a_clear_warning(): void
    {
        $res = $this->save(['self_pct' => 40, 'sponsor_pct' => 40, 'levels' => [20, 20, 20], 'rank_pool_pct' => 5])->assertSessionHasNoErrors();

        $res->assertSessionHas('calculation_warning');
        $this->assertStringContainsString('exceeds 100%', session('calculation_warning'));
    }

    public function test_a_sensible_save_reports_how_much_is_left_as_a_reserve(): void
    {
        $this->save(['levels' => [10, 10, 10]])->assertSessionHas('status');

        $this->assertStringContainsString('reserve', session('status'));
        $this->assertNull(session('calculation_warning'));
    }

    public function test_the_revenue_split_drives_the_cost_warning_on_the_plans_page(): void
    {
        // Plan price 2,000 with 100 of product cost = 5% cost share: fine at a 40% target, flagged when the target is lowered to 4%.
        $this->save(['split_cost_pct' => 4, 'split_pool_pct' => 76, 'split_company_pct' => 20])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->actingAs($this->admin, 'web')->get(route('admin.plans.index'))->assertOk()->viewData('stats')['attention']);

        $this->save()->assertSessionHasNoErrors();
        $this->assertSame(0, $this->actingAs($this->admin, 'web')->get(route('admin.plans.index'))->assertOk()->viewData('stats')['attention']);
    }

    // ------------------------------------------------------------------------------------------ who may use it

    public function test_only_staff_with_finance_access_can_open_or_save_the_settings(): void
    {
        $ops = User::create(['name' => 'Ops', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members', 'cycles'], 'is_active' => true]);

        $this->actingAs($ops, 'web')->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($ops, 'web')->put(route('admin.settings.update'), $this->payload(['self_pct' => 50]))->assertForbidden();
        $this->assertNull(Setting::get('self_pct'));
    }

    public function test_each_section_of_the_page_is_present_and_the_page_opens(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('id="sec-registration"', false)->assertSee('id="sec-inactive"', false)->assertSee('id="sec-split"', false)
            ->assertSee('id="sec-rates"', false)->assertSee('id="sec-levels"', false)->assertSee('id="sec-breaker"', false)
            ->assertSee('id="sec-caps"', false)->assertSee('id="sec-eligibility"', false)->assertSee('id="sec-ranks"', false);
    }
}
