<?php

namespace Tests\Feature;

use App\Models\Category;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The 7th level cascade report: for every matching event, the seven slots (level 1 to 7), who held each and what they
 * were paid, with the root's slot marked; plus a level summary that adds up to the ledger.
 *
 * Network: G0 (root) > G1 > ... > Gn down the left, X and Y under the last; 800 BV matches at the last chain member.
 */
class CascadeReportTest extends TestCase
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
        $this->configureCompensation(['levels' => [10, 9, 8, 7, 6, 5, 4], 'circuit_breaker_pct' => 100, 'match_cap_per_cycle' => 0, 'match_cap_per_week' => 0, 'rank_pool_pct' => 0]);
    }

    private function member(string $name, ?Member $parent, string $position = 'left', ?Region $region = null): Member
    {
        $this->n++;
        $region ??= $this->region;

        return $this->m[$name] = Member::create([
            'member_code' => 'CR'.$this->n, 'name' => $name, 'phone' => '92'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive', 'region_id' => $region->id,
            'sponsor_id' => $parent?->id, 'placement_id' => $parent?->id, 'position' => $parent ? $position : null, 'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
        ]);
    }

    private function buy(Member $member, ?Plan $plan = null): void
    {
        $order = app(OrderService::class)->place($member, $plan ?? $this->plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);
    }

    private function chain(int $last, ?Region $region = null, string $prefix = 'G', ?Plan $plan = null): void
    {
        $parent = null;
        for ($i = 0; $i <= $last; $i++) {
            $parent = $this->member($prefix.$i, $parent, 'left', $region);
        }
        $this->member($prefix.'X', $parent, 'left', $region);
        $this->member($prefix.'Y', $parent, 'right', $region);
        foreach (array_merge(array_map(fn ($i) => $prefix.$i, range(0, $last)), [$prefix.'X', $prefix.'Y']) as $name) {
            $this->buy($this->m[$name], $plan);
        }
    }

    private function cycle(): void
    {
        $service = app(CycleService::class);
        $cycle = $service->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $service->approve($cycle, $this->admin);
    }

    private function report(array $query = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->get(route('admin.reports.cascade', $query));
    }

    private function csvBody(array $query = []): string
    {
        return $this->actingAs($this->admin, 'web')->get(route('admin.reports.cascade.csv', $query))->assertOk()->streamedContent();
    }

    // ------------------------------------------------------------------------------------------ the report

    public function test_the_page_shows_all_seven_slots_of_a_deep_event_with_the_root_in_the_last(): void
    {
        $this->chain(8);
        $this->cycle();

        $page = $this->report()->assertOk()->assertSee('7th level cascade')->assertViewHas('events');
        $event = $page->viewData('events')->first();

        $this->assertSame($this->m['G8']->id, $event['source']->id);
        $this->assertCount(7, $event['slots'], 'Seven slots, always');
        $names = collect($event['slots'])->map(fn ($s) => $s['member']->name)->values()->all();
        $this->assertSame(['G8', 'G7', 'G6', 'G5', 'G4', 'G3', 'G0'], $names);
        $this->assertEqualsWithDelta(80, $event['slots'][1]['amount'], 0.01);
        $this->assertEqualsWithDelta(32, $event['slots'][7]['amount'], 0.01);
        $this->assertTrue($event['slots'][7]['root']);
        $this->assertFalse($event['slots'][1]['root']);
        $this->assertEqualsWithDelta(80 + 72 + 64 + 56 + 48 + 40 + 32, $event['total'], 0.01);
    }

    public function test_the_page_text_names_the_people_the_root_marker_and_the_amounts(): void
    {
        $this->chain(8);
        $this->cycle();

        $this->report()->assertOk()->assertSee('G0')->assertSee('G8')->assertSee('Root slot')->assertSee('₹32.00', false)->assertSee('₹80.00', false);
    }

    public function test_a_shallow_event_leaves_the_unused_slots_empty_and_has_no_root_marker(): void
    {
        $this->chain(3);   // the root is level 4, inside the window
        $this->cycle();

        $event = $this->report()->assertOk()->viewData('events')->first();

        $this->assertCount(7, $event['slots']);
        $this->assertSame(['G3', 'G2', 'G1', 'G0'], collect($event['slots'])->filter()->map(fn ($s) => $s['member']->name)->values()->all());
        $this->assertNull($event['slots'][5]);
        $this->assertNull($event['slots'][7]);
        $this->assertFalse($event['slots'][4]['root'], 'Paid at its normal level: not a root-slot payment');
    }

    public function test_the_level_summary_adds_up_to_the_ledger(): void
    {
        $this->chain(8);
        $this->cycle();

        $data = $this->report()->assertOk()->viewData('summary');
        $ledger = (float) LedgerEntry::where('type', 'matching')->sum('amount');

        $this->assertCount(7, $data['levels']);
        $this->assertEqualsWithDelta($ledger, $data['total'], 0.01);
        $this->assertEqualsWithDelta($ledger, collect($data['levels'])->sum('paid'), 0.01);
        $this->assertSame(1, $data['levels'][7]['payments']);
        $this->assertSame(1, $data['levels'][7]['root_payments']);
        $this->assertEqualsWithDelta(32, $data['levels'][7]['paid'], 0.01);
        $this->assertEqualsWithDelta(32, $data['root_paid'], 0.01);
        $this->assertSame(4.0, (float) $data['levels'][7]['rate']);
        $this->assertGreaterThanOrEqual(1, $data['events']);
    }

    public function test_every_event_pays_at_most_seven_people(): void
    {
        $this->chain(12);
        $this->cycle();

        foreach ($this->report()->viewData('events') as $event) {
            $this->assertLessThanOrEqual(7, collect($event['slots'])->filter()->count());
        }
    }

    // ------------------------------------------------------------------------------------------ filters

    public function test_the_date_range_filters_events(): void
    {
        $this->chain(8);
        $this->cycle();

        $this->assertGreaterThan(0, $this->report(['from' => now()->toDateString(), 'to' => now()->toDateString()])->viewData('events')->count());
        $past = $this->report(['from' => now()->subYear()->toDateString(), 'to' => now()->subYear()->addDay()->toDateString()]);
        $past->assertOk()->assertSee('No cascade payments');
        $this->assertSame(0, $past->viewData('events')->count());
        $this->assertSame(0.0, (float) $past->viewData('summary')['total']);
    }

    public function test_the_region_filter_shows_only_that_regions_events(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $gjPlan = Plan::create(['name' => 'GJ Plan', 'slug' => 'gj', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $gj->id]);
        $gjPlan->products()->attach(Product::first()->id, ['qty' => 1]);
        $this->chain(8);
        $this->chain(8, $gj, 'J', $gjPlan);
        $this->cycle();

        $all = $this->report()->viewData('events')->map(fn ($e) => $e['source']->name)->all();
        $this->assertContains('G8', $all);
        $this->assertContains('J8', $all);

        $only = $this->report(['region' => $gj->id])->viewData('events')->map(fn ($e) => $e['source']->name)->all();
        $this->assertContains('J8', $only);
        $this->assertNotContains('G8', $only);
        $this->assertEqualsWithDelta(
            (float) LedgerEntry::where('type', 'matching')->whereHas('member', fn ($q) => $q->where('region_id', $gj->id))->sum('amount'),
            (float) $this->report(['region' => $gj->id])->viewData('summary')['total'],
            0.01
        );
    }

    public function test_only_matching_income_is_counted_not_self_sponsor_or_rank(): void
    {
        $this->chain(8);
        $this->cycle();

        $summary = $this->report()->viewData('summary');
        $this->assertEqualsWithDelta((float) LedgerEntry::where('type', 'matching')->sum('amount'), $summary['total'], 0.01);
        $this->assertLessThan((float) LedgerEntry::sum('amount'), $summary['total']);
    }

    // ------------------------------------------------------------------------------------------ CSV

    public function test_the_csv_has_one_row_per_event_with_seven_slots(): void
    {
        $this->chain(8);
        $this->cycle();

        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $this->csvBody()))));
        $header = str_getcsv($lines[0]);

        $this->assertContains('Level 1 member', $header);
        $this->assertContains('Level 7 amount', $header);
        $this->assertContains('Root slot', $header);
        $row = collect($lines)->skip(1)->map(fn ($l) => str_getcsv($l))->first(fn ($r) => in_array('G8', $r, true));
        $this->assertNotNull($row, 'The deepest event has its row');
        $this->assertContains('G0', $row);
        $this->assertContains('32.00', $row);
    }

    public function test_the_csv_download_is_named_like_the_other_payout_reports(): void
    {
        $this->chain(8);
        $this->cycle();

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.reports.cascade.csv'));
        $this->assertStringContainsString('payouts-cascade-7th-level-', $response->headers->get('content-disposition'));
    }

    public function test_the_csv_defuses_spreadsheet_formulas_in_names(): void
    {
        $this->chain(8);
        Member::where('id', $this->m['G7']->id)->update(['name' => '=HYPERLINK("http://x")']);
        $this->cycle();

        $this->assertStringNotContainsString(',=HYPERLINK', $this->csvBody());
        $this->assertStringContainsString("'=HYPERLINK", $this->csvBody());
    }

    // ------------------------------------------------------------------------------------------ safety

    public function test_a_member_name_with_markup_is_escaped_on_the_page(): void
    {
        $this->chain(8);
        Member::where('id', $this->m['G7']->id)->update(['name' => '<script>alert(1)</script>']);
        $this->cycle();

        $this->report()->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_guests_and_members_cannot_open_the_report(): void
    {
        $this->chain(3);
        $this->get(route('admin.reports.cascade'))->assertRedirect();
        $this->get(route('admin.reports.cascade.csv'))->assertRedirect();
        $this->actingAs($this->m['G0'], 'member')->get(route('admin.reports.cascade'))->assertRedirect();
    }

    public function test_staff_need_finance_access(): void
    {
        $ops = User::create(['name' => 'Ops', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members'], 'is_active' => true]);
        $fin = User::create(['name' => 'Fin', 'email' => 'f@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['finance'], 'is_active' => true]);

        $this->report([], $ops)->assertForbidden();
        $this->actingAs($ops, 'web')->get(route('admin.reports.cascade.csv'))->assertForbidden();
        $this->report([], $fin)->assertOk();
    }

    public function test_a_garbage_date_or_region_does_not_crash_the_page(): void
    {
        $this->chain(3);
        $this->cycle();

        $this->report(['region' => 'abc'])->assertOk();
        $this->report(['region' => '999999'])->assertOk()->assertSee('No cascade payments');
    }

    public function test_the_report_tab_is_in_the_reports_menu(): void
    {
        $this->report()->assertOk()->assertSee(route('admin.reports.cascade'), false)->assertSee('7th level cascade');
    }

    public function test_an_empty_system_shows_a_friendly_empty_state_with_seven_zero_levels(): void
    {
        $page = $this->report()->assertOk()->assertSee('No cascade payments');
        $this->assertCount(7, $page->viewData('summary')['levels']);
        $this->assertSame(0.0, (float) $page->viewData('summary')['total']);
    }

    // ------------------------------------------------------------------------------------------ PDF (print) view

    public function test_the_pdf_view_lists_every_event_and_the_summary_in_a_print_layout(): void
    {
        $this->chain(8);
        $this->cycle();

        $this->actingAs($this->admin, 'web')->get(route('admin.reports.cascade.pdf'))->assertOk()
            ->assertSee('7th level cascade report')->assertSee('@page', false)->assertSee('landscape', false)
            ->assertSee('Root slot')->assertSee('₹32.00', false)->assertSee('Save as PDF')
            ->assertDontSee('rep-tabs', false);   // no admin menu on the printout
    }

    public function test_the_pdf_view_follows_the_date_filter(): void
    {
        $this->chain(8);
        $this->cycle();

        $this->actingAs($this->admin, 'web')->get(route('admin.reports.cascade.pdf', ['from' => now()->subYear()->toDateString(), 'to' => now()->subYear()->addDay()->toDateString()]))
            ->assertOk()->assertSee('No cascade payments')->assertDontSee('₹80.00', false);
    }

    public function test_the_pdf_view_escapes_names_and_needs_finance_access(): void
    {
        $this->chain(8);
        Member::where('id', $this->m['G7']->id)->update(['name' => '<script>alert(1)</script>']);
        $this->cycle();
        $ops = User::create(['name' => 'Ops', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members'], 'is_active' => true]);

        $this->actingAs($this->admin, 'web')->get(route('admin.reports.cascade.pdf'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($ops, 'web')->get(route('admin.reports.cascade.pdf'))->assertForbidden();
        $this->app['auth']->guard('web')->logout();
        $this->get(route('admin.reports.cascade.pdf'))->assertRedirect();
    }

    public function test_the_report_page_links_to_the_pdf_view(): void
    {
        $this->report()->assertOk()->assertSee(route('admin.reports.cascade.pdf'), false);
    }

    public function test_the_page_stays_fast_with_many_events(): void
    {
        $this->chain(10);
        $this->cycle();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->report()->assertOk();
        $this->assertLessThan(40, count(\Illuminate\Support\Facades\DB::getQueryLog()), 'No query per row');
    }
}
