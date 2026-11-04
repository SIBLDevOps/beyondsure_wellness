<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\MemberLegTotal;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\CycleService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** "What would the next cycle pay?" A real run of the compensation engine on today's paid orders, rolled back afterwards. */
class CycleSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Region $region;

    /** @var array<int, Member> */
    private array $m = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureCompensation();
        Mail::fake();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        foreach (['self_pct' => 10, 'sponsor_pct' => 20, 'rank_pool_pct' => 1, 'circuit_breaker_pct' => 90, 'match_cap_per_cycle' => 100000000, 'match_cap_per_week' => 100000000] as $k => $v) {
            Setting::set($k, $v);
        }
        Setting::setEligibilityMinVolumes([0], 0);
        Setting::setLevelPercentages([10, 9, 8, 7, 6, 5, 4]);
        Setting::set('cascade_depth', 7);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($p->id, ['qty' => 1]);
    }

    /** A root with two directs (left, right) and a grandchild under the left: enough to match and cascade. */
    private function tree(): void
    {
        $root = $this->member('Root', null, null);
        $left = $this->member('Left', $root, 'left');
        $right = $this->member('Right', $root, 'right');
        $gl = $this->member('GrandLeft', $left, 'left');
        foreach ([$root, $left, $right, $gl] as $x) {
            $this->pay($x);
        }
        $this->m = compact('root', 'left', 'right', 'gl');
    }

    private function member(string $name, ?Member $sponsor, ?string $side): Member
    {
        static $n = 0;
        $n++;

        return Member::create([
            'member_code' => 'SM'.$n, 'name' => $name, 'phone' => '91'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'email' => 'sm'.$n.'@example.com', 'status' => 'inactive', 'region_id' => $this->region->id,
            'sponsor_id' => $sponsor?->id, 'placement_id' => $sponsor?->id, 'position' => $side, 'path' => $sponsor ? (($sponsor->path ?? '').$sponsor->id.'.') : null,
        ]);
    }

    private function pay(Member $m): void
    {
        $order = app(OrderService::class)->place($m, $this->plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'U'.$order->id), $this->admin);
    }

    private function simulate()
    {
        return $this->actingAs($this->admin, 'web')->post(route('admin.cycles.simulate.run'));
    }

    private function sub(array $perms): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);
    }

    // ------------------------------------------------------------------------------------------ the page shows the live rules

    public function test_the_page_shows_the_live_settings(): void
    {
        Setting::set('self_pct', 12);
        Setting::set('sponsor_pct', 25);
        Setting::setLevelPercentages([11, 9, 7]);

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.cycles.simulate'))->assertOk();

        $res->assertSee('Compensation simulation')->assertSee('Self income')->assertSee('12%')->assertSee('Sponsor bonus')->assertSee('25%')
            ->assertSee('Level 1')->assertSee('11%')->assertSee('Level 3')->assertSee('7%')->assertDontSee('Level 4')
            ->assertSee('Run simulation');
    }

    public function test_a_cap_of_zero_is_shown_as_no_limit(): void
    {
        Setting::set('match_cap_per_cycle', 0);
        Setting::set('match_cap_per_week', 0);

        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.simulate'))->assertOk()->assertSee('No monthly limit')->assertSee('no weekly limit')->assertDontSee('₹0.00 a month');
    }

    public function test_the_settings_shown_follow_a_change_straight_away(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.simulate'))->assertSee('10%');
        Setting::set('self_pct', 33);

        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.simulate'))->assertSee('33%');
    }

    // ------------------------------------------------------------------------------------------ it is a real run that changes nothing

    public function test_the_simulation_pays_out_nothing_and_leaves_every_record_as_it_was(): void
    {
        $this->tree();
        $before = [
            'ledger' => LedgerEntry::count(), 'cycles' => Cycle::count(), 'paid' => Order::where('status', 'paid')->count(), 'settled' => Order::where('status', 'settled')->count(),
            'legs' => MemberLegTotal::all()->map->only(['member_id', 'left_bv', 'right_bv', 'matched_bv'])->all(), 'ranks' => Member::pluck('rank', 'id')->all(),
            'weekly' => \DB::table('member_weekly_totals')->count(),
        ];

        Mail::fake();   // forget the emails from building the network: only what the simulation sends counts
        $this->simulate()->assertOk()->assertSee('Nothing was paid');

        $this->assertSame($before['ledger'], LedgerEntry::count());
        $this->assertSame($before['cycles'], Cycle::count());
        $this->assertSame($before['paid'], Order::where('status', 'paid')->count());
        $this->assertSame($before['settled'], Order::where('status', 'settled')->count());
        $this->assertEquals($before['legs'], MemberLegTotal::all()->map->only(['member_id', 'left_bv', 'right_bv', 'matched_bv'])->all());
        $this->assertSame($before['ranks'], Member::pluck('rank', 'id')->all());
        $this->assertSame($before['weekly'], \DB::table('member_weekly_totals')->count());
        Mail::assertNothingSent();
    }

    public function test_it_predicts_exactly_what_approving_the_cycle_then_pays(): void
    {
        $this->tree();
        $sim = $this->simulate()->assertOk()->viewData('result');

        $cycle = app(CycleService::class)->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        app(CycleService::class)->approve($cycle, $this->admin);

        foreach (['self', 'sponsor', 'matching', 'rank'] as $type) {
            $this->assertEqualsWithDelta((float) LedgerEntry::where('type', $type)->sum('amount'), $sim['totals'][$type], 0.001, "{$type} predicted");
        }
        $this->assertEqualsWithDelta((float) $cycle->fresh()->total_paid, $sim['total_paid'], 0.001);
        $this->assertEqualsWithDelta((float) $cycle->fresh()->pool_bv, $sim['pool'], 0.001);
        $this->assertSame(Order::where('status', 'settled')->count(), $sim['orders']);
    }

    public function test_the_level_table_adds_up_and_matches_the_rates(): void
    {
        $this->tree();
        $sim = $this->simulate()->assertOk()->viewData('result');

        $this->assertEqualsWithDelta($sim['totals']['matching'], array_sum($sim['by_level']), 0.001, 'Levels add up to the matching total');
        $this->assertGreaterThan(0, $sim['by_level'][1] ?? 0, 'Level 1 is paid');
        // Matched BV is 800 for the root (left subtree 1600, right 800): level 1 is 10% of it.
        $this->assertEqualsWithDelta(80.0, $sim['by_level'][1], 0.001);
        $this->assertSame([], array_diff(array_keys($sim['by_level']), range(1, 7)), 'Nothing beyond level 7');
    }

    public function test_it_lists_who_would_earn_most_and_how_much_of_the_pool_is_used(): void
    {
        $this->tree();
        $res = $this->simulate()->assertOk();
        $sim = $res->viewData('result');

        $this->assertNotEmpty($sim['earners']);
        $this->assertSame('Root', $sim['earners'][0]['name'], 'The top of the tree earns most');
        $this->assertEqualsWithDelta($sim['total_paid'], array_sum($sim['totals']), 0.01);
        $this->assertLessThanOrEqual($sim['pool'] + 0.01, $sim['total_paid']);
        $res->assertSee('Root')->assertSee('of the pool')->assertSee('Self income')->assertSee('Matching income');
    }

    public function test_a_changed_setting_changes_the_simulated_result_without_touching_the_real_one(): void
    {
        $this->tree();
        $a = $this->simulate()->viewData('result');
        Setting::set('sponsor_pct', 30);
        $b = $this->simulate()->viewData('result');

        $this->assertGreaterThan($a['totals']['sponsor'], $b['totals']['sponsor']);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_running_it_twice_gives_the_same_answer(): void
    {
        $this->tree();

        $this->assertEquals($this->simulate()->viewData('result')['totals'], $this->simulate()->viewData('result')['totals']);
    }

    public function test_with_nothing_to_settle_it_says_so(): void
    {
        $this->simulate()->assertOk()->assertSee('no paid orders waiting');
        $this->assertSame(0, Cycle::count());
    }

    public function test_a_real_cycle_can_still_be_made_after_simulating(): void
    {
        $this->tree();
        $this->simulate();
        $this->simulate();

        $cycle = app(CycleService::class)->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        app(CycleService::class)->approve($cycle, $this->admin);

        $this->assertSame(1, Cycle::count());
        $this->assertGreaterThan(0, LedgerEntry::count());
        $this->assertSame(0, Order::where('status', 'paid')->count());
    }

    // ------------------------------------------------------------------------------------------ who may use it

    public function test_it_needs_finance_access(): void
    {
        $this->tree();

        $this->actingAs($this->sub(['cycles']), 'web')->get(route('admin.cycles.simulate'))->assertForbidden();
        $this->actingAs($this->sub(['cycles']), 'web')->post(route('admin.cycles.simulate.run'))->assertForbidden();
        $this->actingAs($this->sub(['cycles', 'finance']), 'web')->post(route('admin.cycles.simulate.run'))->assertOk();
    }

    public function test_staff_limited_to_some_regions_cannot_run_a_company_wide_simulation(): void
    {
        $this->tree();
        $limited = User::create(['name' => 'Lim', 'email' => 'l@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['cycles', 'finance'], 'region_ids' => [$this->region->id], 'is_active' => true]);

        $this->actingAs($limited, 'web')->get(route('admin.cycles.simulate'))->assertForbidden();
        $this->actingAs($limited, 'web')->post(route('admin.cycles.simulate.run'))->assertForbidden();
    }

    public function test_the_cycles_page_links_to_it_for_people_who_may_open_it(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.index'))->assertOk()->assertSee(route('admin.cycles.simulate'), false);
        $this->actingAs($this->sub(['cycles']), 'web')->get(route('admin.cycles.index'))->assertOk()->assertDontSee(route('admin.cycles.simulate'), false);
    }

    public function test_the_run_is_recorded_in_the_audit_log_as_a_simulation(): void
    {
        $this->tree();
        $this->simulate();

        $this->assertSame('admin.cycles.simulate.run', \App\Models\AuditLog::latest('id')->first()->action);
    }
}
