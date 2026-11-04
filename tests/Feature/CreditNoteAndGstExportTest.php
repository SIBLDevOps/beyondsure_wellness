<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\CycleService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Reversing a paid order with a numbered credit note, and the monthly GST sales export that includes both. */
class CreditNoteAndGstExportTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private Plan $plan;

    private User $admin;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureCompensation();
        Mail::fake();
        Carbon::setTestNow('2026-10-15 11:00:00');
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($p->id, ['qty' => 1]);
        Setting::set('inv_tax_type', 'cgst_sgst');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name = 'Buyer Bela', ?Region $region = null): Member
    {
        $this->n++;

        return Member::create(['member_code' => 'CN'.$this->n, 'name' => $name, 'phone' => '94'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'email' => 'cn'.$this->n.'@example.com', 'status' => 'inactive', 'region_id' => ($region ?? $this->region)->id]);
    }

    private function paid(?Member $m = null, ?Plan $plan = null): Order
    {
        $m ??= $this->member();
        $order = app(OrderService::class)->place($m, $plan ?? $this->plan);
        $order->update(['billing_name' => $m->name, 'billing_address' => "1 Road\nTown"]);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);

        return $order->fresh();
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function issue(Order $order, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->post(route('admin.orders.credit-note.store', $order), $extra + ['reason' => 'Customer cancelled the order', 'refund_method' => 'cash']);
    }

    // ------------------------------------------------------------------------------------------ issuing

    public function test_a_credit_note_reverses_a_paid_order_with_its_own_number(): void
    {
        $order = $this->paid();
        $this->issue($order)->assertSessionHasNoErrors()->assertRedirect();

        $note = CreditNote::firstOrFail();
        $this->assertSame('CN/2026-27/00001', $note->number);
        $this->assertSame($order->id, $note->order_id);
        $this->assertSame('2026-10-15', $note->issued_on->toDateString());
        $this->assertEqualsWithDelta(2000.0, (float) $note->taxable_amount, 0.001);
        $this->assertEqualsWithDelta(240.0, (float) $note->gst_amount, 0.001);
        $this->assertEqualsWithDelta(2240.0, (float) $note->amount, 0.001);
        $this->assertSame('Customer cancelled the order', $note->reason);
        $this->assertSame($this->admin->id, $note->created_by);

        $order = $order->fresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->invoice_number, 'The original invoice number is kept: it was issued');
    }

    public function test_the_buyer_goes_back_to_inactive_unless_they_have_another_purchase(): void
    {
        $only = $this->member('Only Once');
        $this->issue($this->paid($only));
        $this->assertSame('inactive', $only->fresh()->status);

        $twice = $this->member('Twice Buyer');
        $first = $this->paid($twice);
        $this->paid($twice->fresh());
        $this->issue($first);
        $this->assertSame('active', $twice->fresh()->status, 'Still has a paid order');
    }

    public function test_numbers_are_consecutive_and_restart_each_financial_year(): void
    {
        $this->issue($this->paid());
        $this->issue($this->paid());
        Carbon::setTestNow('2027-04-02 10:00:00');
        $this->issue($this->paid());

        $this->assertSame(['CN/2026-27/00001', 'CN/2026-27/00002', 'CN/2027-28/00001'], CreditNote::orderBy('id')->pluck('number')->all());
    }

    public function test_a_reason_is_required_and_only_a_paid_unsettled_order_qualifies(): void
    {
        $order = $this->paid();
        $this->issue($order, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->issue($order, ['reason' => str_repeat('x', 300)])->assertSessionHasErrors('reason');

        $pending = app(OrderService::class)->place($this->member(), $this->plan);
        $this->issue($pending)->assertSessionHas('error');

        $this->issue($order)->assertSessionHasNoErrors();
        $this->issue($order->fresh())->assertSessionHas('error');                       // already cancelled / already has one
        $this->assertSame(1, CreditNote::count());

        $settled = $this->paid();
        $cycle = app(CycleService::class)->create(now()->subMonth()->toDateString(), now()->addDay()->toDateString());
        app(CycleService::class)->approve($cycle, $this->admin);
        $this->assertSame('settled', $settled->fresh()->status);
        $this->issue($settled)->assertSessionHas('error');                               // income was already posted
        $this->assertSame(1, CreditNote::count());
    }

    public function test_the_member_is_emailed_the_credit_note(): void
    {
        $order = $this->paid();
        $this->issue($order);

        $mail = Mail::sent(BrandedMail::class)->first(fn ($m) => str_contains($m->mailSubject, 'Credit note'));
        $this->assertNotNull($mail);
        $this->assertTrue($mail->hasTo($order->member->email));
        $this->assertStringContainsString('CN/2026-27/00001', $mail->render());
    }

    public function test_it_needs_finance_access_and_stays_inside_the_region(): void
    {
        $order = $this->paid();
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);

        $this->issue($order, [], $this->sub(['orders']))->assertForbidden();
        $this->issue($order, [], $this->sub(['orders', 'finance'], [$gj->id]))->assertNotFound();
        $this->assertSame(0, CreditNote::count());
        $this->issue($order, [], $this->sub(['orders', 'finance']))->assertSessionHasNoErrors();
        $this->assertSame(1, CreditNote::count());
    }

    // ------------------------------------------------------------------------------------------ the document

    public function test_the_member_and_the_admin_can_open_the_credit_note_and_nobody_else(): void
    {
        $order = $this->paid();
        $this->issue($order);
        $other = $this->member('Other Omar');

        $this->actingAs($order->member, 'member')->get(route('member.orders.credit-note', $order))->assertOk()
            ->assertSee('CREDIT NOTE')->assertSee('CN/2026-27/00001')->assertSee($order->invoice_number)->assertSee('Customer cancelled the order')
            ->assertSee('₹2,240.00')->assertSee('Rupees Two Thousand Two Hundred Forty Only');
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.credit-note', $order))->assertOk()->assertSee('CN/2026-27/00001');
        $this->actingAs($other, 'member')->get(route('member.orders.credit-note', $order))->assertForbidden();
        $this->app['auth']->guard('member')->logout();
        $this->get(route('member.orders.credit-note', $order))->assertRedirect(route('login'));
    }

    public function test_an_order_without_a_credit_note_has_none_to_open(): void
    {
        $order = $this->paid();

        $this->actingAs($order->member, 'member')->get(route('member.orders.credit-note', $order))->assertNotFound();
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.credit-note', $order))->assertNotFound();
    }

    public function test_the_admin_order_list_offers_the_credit_note_action_and_link(): void
    {
        $paid = $this->paid();
        $done = $this->paid();
        $this->issue($done);

        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk()
            ->assertSee(route('admin.orders.credit-note.store', $paid), false)
            ->assertSee(route('admin.orders.credit-note', $done), false)
            ->assertDontSee('<form method="POST" action="'.route('admin.orders.credit-note.store', $done).'"', false)   // no second credit note offered
            ->assertSee('<form method="POST" action="'.route('admin.orders.credit-note.store', $paid).'"', false);
    }

    // ------------------------------------------------------------------------------------------ the GST export

    private function csv(array $query = [], ?User $as = null): array
    {
        $res = $this->actingAs($as ?? $this->admin, 'web')->get(route('admin.reports.gst.csv', $query))->assertOk();
        $rows = array_map('str_getcsv', array_filter(explode("\n", $res->streamedContent())));

        return $rows;
    }

    public function test_the_page_totals_invoices_and_credit_notes_for_a_month(): void
    {
        $this->paid();
        $this->paid();
        $this->issue($this->paid());                                    // 3 invoices, 1 credit note, all in October 2026

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', ['month' => '2026-10']))->assertOk();
        $t = $res->viewData('totals');

        $this->assertSame(3, $t['invoices']);
        $this->assertSame(1, $t['credit_notes']);
        $this->assertEqualsWithDelta(6000.0, $t['taxable'], 0.01);
        $this->assertEqualsWithDelta(720.0, $t['gst'], 0.01);
        $this->assertEqualsWithDelta(6720.0, $t['total'], 0.01);
        $this->assertEqualsWithDelta(-2000.0, $t['credit_taxable'], 0.01);
        $this->assertEqualsWithDelta(-240.0, $t['credit_gst'], 0.01);
        $this->assertEqualsWithDelta(4000.0, $t['net_taxable'], 0.01);
        $this->assertEqualsWithDelta(480.0, $t['net_gst'], 0.01);
        $res->assertSee('₹4,000.00')->assertSee('CGST')->assertSee('SGST');
    }

    public function test_a_credit_note_counts_in_the_month_it_was_issued_not_the_invoice_month(): void
    {
        $order = $this->paid();                                          // October invoice
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->issue($order);                                            // November credit note

        $oct = $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', ['month' => '2026-10']))->viewData('totals');
        $nov = $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', ['month' => '2026-11']))->viewData('totals');

        $this->assertSame([1, 0, 2000.0], [$oct['invoices'], $oct['credit_notes'], $oct['net_taxable']]);
        $this->assertSame([0, 1, -2000.0], [$nov['invoices'], $nov['credit_notes'], $nov['net_taxable']]);
    }

    public function test_the_csv_lists_every_invoice_and_credit_note_with_the_gst_split_and_its_totals_match_the_page(): void
    {
        $this->paid($this->member('Alpha'));
        $this->issue($this->paid($this->member('Beta')));

        $rows = $this->csv(['month' => '2026-10']);

        $this->assertSame(['Type', 'Number', 'Date', 'Order', 'Customer', 'Mobile', 'Region', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Total', 'Against invoice'], $rows[0]);
        $body = array_slice($rows, 1);
        $this->assertCount(3, $body, '2 invoices and 1 credit note');
        $types = array_column($body, 0);
        $this->assertSame(['Invoice', 'Invoice', 'Credit note'], $types);

        $invoice = $body[0];
        $this->assertSame(['2000.00', '120.00', '120.00', '0.00', '2240.00'], array_slice($invoice, 7, 5));
        $credit = $body[2];
        $this->assertSame(['-2000.00', '-120.00', '-120.00', '0.00', '-2240.00'], array_slice($credit, 7, 5));
        $this->assertStringStartsWith('INV/', $credit[12], 'A credit note names the invoice it reverses');

        $this->assertEqualsWithDelta(2000.0, array_sum(array_map('floatval', array_column($body, 7))), 0.001, 'Net taxable');
        $this->assertEqualsWithDelta(2240.0, array_sum(array_map('floatval', array_column($body, 11))), 0.001, 'Net total');
    }

    public function test_igst_mode_puts_the_tax_in_the_igst_column(): void
    {
        Setting::set('inv_tax_type', 'igst');
        $this->paid();

        $row = $this->csv(['month' => '2026-10'])[1];

        $this->assertSame(['2000.00', '0.00', '0.00', '240.00', '2240.00'], array_slice($row, 7, 5));
    }

    public function test_the_region_filter_narrows_both_page_and_csv(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $gjPlan = Plan::create(['name' => 'GJ Plan', 'slug' => 'g', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $gj->id]);
        $gjPlan->products()->attach(Product::first()->id, ['qty' => 1]);
        $this->paid();
        $this->paid($this->member('Gita', $gj), $gjPlan);

        $rows = $this->csv(['month' => '2026-10', 'region' => $gj->id]);
        $this->assertCount(2, $rows);                                    // header + Gujarat's one invoice
        $this->assertSame('Gita', $rows[1][4]);
        $this->assertSame(1, $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', ['month' => '2026-10', 'region' => $gj->id]))->viewData('totals')['invoices']);
    }

    public function test_spreadsheet_formulas_in_a_customer_name_are_neutralised(): void
    {
        $this->paid($this->member('=HYPERLINK("http://evil","x")'));

        $row = $this->csv(['month' => '2026-10'])[1];

        $this->assertStringStartsWith("'=", $row[4]);
    }

    public function test_a_bad_or_missing_month_falls_back_to_the_current_month_and_an_empty_month_is_fine(): void
    {
        $this->paid();

        foreach ([[], ['month' => 'nonsense'], ['month' => ['x']], ['month' => '2026-13']] as $q) {
            $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', $q))->assertOk()->assertSee('October 2026');
        }
        $this->assertSame(0, $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', ['month' => '2020-01']))->viewData('totals')['invoices']);
        $this->assertCount(1, $this->csv(['month' => '2020-01']), 'Only the header row');
    }

    public function test_the_report_is_finance_only_and_appears_in_the_reports_menu(): void
    {
        $this->actingAs($this->sub(['orders']), 'web')->get(route('admin.reports.gst'))->assertForbidden();
        $this->actingAs($this->sub(['orders']), 'web')->get(route('admin.reports.gst.csv'))->assertForbidden();
        $this->actingAs($this->sub(['finance']), 'web')->get(route('admin.reports.gst'))->assertOk();
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.overview'))->assertOk()->assertSee('GST sales');
    }

    public function test_orders_without_an_invoice_number_are_left_out(): void
    {
        $order = $this->paid();
        $order->forceFill(['invoice_number' => null, 'invoice_date' => null])->save();

        $this->assertSame(0, $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst', ['month' => '2026-10']))->viewData('totals')['invoices']);
    }
}
