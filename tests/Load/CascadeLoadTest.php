<?php

namespace Tests\Load;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\CycleService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Load and correctness of the 7 level cascade at size. Not part of the normal suite (it is slow): run it with
 *
 *     CASCADE_LOAD_N=300 CASCADE_LOAD_OUT=path/to/results.json php artisan test tests/Load
 *
 * Every scenario builds a network in an isolated in-memory database (never the real one), runs a real cycle, then checks
 * the money rules (at most seven payees per match, nobody paid twice for one match, the root held the last slot,
 * nothing paid beyond the pool, a re-run pays nothing new) and records time, query count and memory.
 */
class CascadeLoadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Region $region;

    private int $n = 0;

    private array $results = [];

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
        $this->configureCompensation(['levels' => [10, 9, 8, 7, 6, 5, 4], 'circuit_breaker_pct' => 100, 'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'rank_pool_pct' => 0]);
    }

    private function scale(): int
    {
        return max(20, (int) (getenv('CASCADE_LOAD_N') ?: 120));
    }

    private function member(?Member $parent, string $position = 'left'): Member
    {
        $this->n++;

        return Member::create([
            'member_code' => 'LD'.$this->n, 'name' => 'M'.$this->n, 'phone' => '93'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive', 'region_id' => $this->region->id,
            'sponsor_id' => $parent?->id, 'placement_id' => $parent?->id, 'position' => $parent ? $position : null, 'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
        ]);
    }

    private function buy(Member $member): void
    {
        $order = app(OrderService::class)->place($member, $this->plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);
    }

    /** Runs $fn and returns [result, seconds, queries, peak MB]. */
    private function measure(callable $fn): array
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $t = microtime(true);
        $result = $fn();

        return [$result, round(microtime(true) - $t, 3), $queries, round(memory_get_peak_usage(true) / 1048576, 1)];
    }

    private function runCycle(): Cycle
    {
        $service = app(CycleService::class);
        $cycle = $service->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $service->approve($cycle, $this->admin);

        return $cycle->fresh();
    }

    /** The money rules, checked in SQL against everything the cycle posted. */
    private function assertMoneyRules(Cycle $cycle, bool $expectRootSlots): array
    {
        $matching = LedgerEntry::where('type', 'matching');
        $perEvent = (clone $matching)->selectRaw('cycle_id, source_member_id, count(*) c')->groupBy('cycle_id', 'source_member_id')->pluck('c');
        $this->assertLessThanOrEqual(7, (int) $perEvent->max(), 'No match pays more than seven people');

        $dupes = (clone $matching)->selectRaw('member_id, source_member_id, cycle_id, count(*) c')->groupBy('member_id', 'source_member_id', 'cycle_id')->having('c', '>', 1)->count();
        $this->assertSame(0, $dupes, 'Nobody is paid twice for one match');

        $this->assertSame(0, LedgerEntry::where('amount', '<', 0)->count(), 'No negative income');
        // Recorded before the check, so a failure still leaves the numbers behind.
        $this->record('pool_check_'.$this->name(), [
            'pool_bv' => (float) $cycle->pool_bv, 'total_paid' => (float) $cycle->total_paid, 'overshoot' => round((float) $cycle->total_paid - (float) $cycle->pool_bv, 2),
            'by_type' => LedgerEntry::where('cycle_id', $cycle->id)->selectRaw('type, count(*) c, sum(amount) s')->groupBy('type')->get()->map(fn ($r) => [$r->type, (int) $r->c, round((float) $r->s, 2)])->all(),
        ]);
        $this->assertLessThanOrEqual((float) $cycle->pool_bv + 0.01, (float) $cycle->total_paid, 'Never more than the pool');
        $this->assertEqualsWithDelta((float) $cycle->total_paid, (float) LedgerEntry::where('cycle_id', $cycle->id)->whereIn('type', ['self', 'sponsor', 'matching', 'rank'])->sum('amount'), 0.05, 'The cycle total equals what was posted');

        $rootRows = (clone $matching)->where('description', 'like', 'Root matching%')->count();
        $deepEvents = DB::table('ledger_entries as l')->join('members as s', 's.id', '=', 'l.source_member_id')
            ->where('l.type', 'matching')->whereRaw('(length(s.path) - length(replace(s.path, \'.\', \'\'))) >= 7')->distinct()->count('l.source_member_id');
        if ($expectRootSlots) {
            $this->assertGreaterThan(0, $rootRows, 'The root held a slot on deep matches');
        }
        // The root is paid at most once per match.
        $this->assertSame(0, (clone $matching)->where('description', 'like', 'Root matching%')->selectRaw('source_member_id, cycle_id, count(*) c')->groupBy('source_member_id', 'cycle_id')->having('c', '>', 1)->count());

        return ['events' => $perEvent->count(), 'max_payees_per_event' => (int) $perEvent->max(), 'matching_rows' => (int) (clone $matching)->count(), 'root_rows' => $rootRows, 'deep_events' => $deepEvents,
            'matching_paid' => round((float) (clone $matching)->sum('amount'), 2), 'pool_bv' => (float) $cycle->pool_bv, 'total_paid' => (float) $cycle->total_paid];
    }

    private function record(string $scenario, array $data): void
    {
        $this->results[$scenario] = $data;
        if ($out = getenv('CASCADE_LOAD_OUT')) {
            $all = is_file($out) ? (json_decode((string) file_get_contents($out), true) ?: []) : [];
            $all[$scenario] = $data + ['n' => $this->scale()];
            file_put_contents($out, json_encode($all, JSON_PRETTY_PRINT));
        }
    }

    // ------------------------------------------------------------------------------------------ scenarios

    /** Worst case for depth: a spine of N members, each with a right-hand buyer, so EVERY spine member is a deep match. */
    public function test_a_deep_spine_where_every_member_is_a_deep_match(): void
    {
        $spine = [];
        $parent = null;
        [, $buildSecs] = $this->measure(function () use (&$spine, &$parent) {
            for ($i = 0; $i < $this->scale(); $i++) {
                $spine[] = $parent = $this->member($parent, 'left');
                $this->member($parent, 'right');
            }
            $this->member($parent, 'left');
            foreach (Member::orderBy('id')->get() as $m) {
                $this->buy($m);
            }
        });

        [$cycle, $secs, $queries, $mem] = $this->measure(fn () => $this->runCycle());
        $rules = $this->assertMoneyRules($cycle, true);

        // Every deep match (generation 8 or more) pays exactly seven people and the root holds the last slot.
        $root = $spine[0];
        $this->assertGreaterThan(0, LedgerEntry::where('member_id', $root->id)->where('type', 'matching')->count());

        // A second cycle straight away: no new volume, so no new matching.
        $before = LedgerEntry::where('type', 'matching')->count();
        $this->runCycle();
        $this->assertSame($before, LedgerEntry::where('type', 'matching')->count(), 'A re-run pays nothing new');

        // Approving the same cycle twice posts nothing extra.
        $rows = LedgerEntry::count();
        try {
            app(CycleService::class)->approve($cycle, $this->admin);
            $this->fail('A cycle cannot be approved twice');
        } catch (\RuntimeException) {
        }
        $this->assertSame($rows, LedgerEntry::count());

        $this->record('deep_spine', ['members' => Member::count(), 'build_s' => $buildSecs, 'cycle_s' => $secs, 'cycle_queries' => $queries, 'peak_mb' => $mem] + $rules);
        $this->addToAssertionCount(1);
    }

    /** A balanced binary tree: many matches at every depth, the shape a healthy network has. */
    public function test_a_balanced_tree(): void
    {
        $nodes = [];
        [, $buildSecs] = $this->measure(function () use (&$nodes) {
            $count = $this->scale() * 3;
            for ($i = 0; $i < $count; $i++) {
                $nodes[$i] = $i === 0 ? $this->member(null) : $this->member($nodes[intdiv($i - 1, 2)], $i % 2 === 1 ? 'left' : 'right');
            }
            foreach ($nodes as $m) {
                $this->buy($m);
            }
        });

        [$cycle, $secs, $queries, $mem] = $this->measure(fn () => $this->runCycle());
        $rules = $this->assertMoneyRules($cycle, $count ?? false);

        $this->record('balanced_tree', ['members' => Member::count(), 'build_s' => $buildSecs, 'cycle_s' => $secs, 'cycle_queries' => $queries, 'peak_mb' => $mem] + $rules);
        $this->addToAssertionCount(1);
    }

    /** The cascade report and its CSV / PDF view on the data the cycles above posted. */
    public function test_the_cascade_report_stays_fast_on_a_large_ledger(): void
    {
        $parent = null;
        for ($i = 0; $i < $this->scale(); $i++) {
            $parent = $this->member($parent, 'left');
            $this->member($parent, 'right');
        }
        foreach (Member::orderBy('id')->get() as $m) {
            $this->buy($m);
        }
        $this->runCycle();
        $ledger = LedgerEntry::where('type', 'matching')->count();

        $web = [];
        foreach (['page' => 'admin.reports.cascade', 'csv' => 'admin.reports.cascade.csv', 'pdf' => 'admin.reports.cascade.pdf'] as $label => $route) {
            [$response, $secs, $queries, $mem] = $this->measure(function () use ($route) {
                $r = $this->actingAs($this->admin, 'web')->get(route($route));
                $r->assertOk();
                if ($r->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                    $r->streamedContent();
                }

                return $r;
            });
            $web[$label] = ['seconds' => $secs, 'queries' => $queries, 'peak_mb' => $mem];
            $this->assertLessThan(60, $queries, "{$label}: query count does not grow with the ledger");
        }

        $this->record('report', ['ledger_matching_rows' => $ledger] + $web);
        $this->addToAssertionCount(1);
    }
}
