<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A proper GST invoice for every paid order: numbered, dated, itemised, with the company's logo and details. */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private Category $cat;

    private Member $member;

    private User $admin;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 11:00:00');
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->member = $this->makeMember('Invoice Buyer');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (glob(public_path(\App\Support\Site::DIR.'/logo-test-*')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function makeMember(string $name, ?Region $region = null): Member
    {
        $this->n++;

        return Member::create(['member_code' => 'IV'.$this->n, 'name' => $name, 'phone' => '97'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive', 'region_id' => ($region ?? $this->region)->id]);
    }

    private function product(string $name, float $sell, float $gst, float $cost = 100): Product
    {
        $this->n++;

        return Product::create(['category_id' => $this->cat->id, 'name' => $name, 'sku' => 'SKU'.$this->n, 'cost_price' => $cost, 'sell_price' => $sell, 'gst_pct' => $gst, 'is_active' => true]);
    }

    /** @param array<int, array{0: Product, 1: int}> $items */
    private function plan(array $items, float $price = 2000): Plan
    {
        $this->n++;
        $plan = Plan::create(['name' => 'Plan '.$this->n, 'slug' => 'plan'.$this->n, 'price' => $price, 'bv' => $price * 0.4, 'is_active' => true, 'region_id' => $this->region->id]);
        foreach ($items as [$product, $qty]) {
            $plan->products()->attach($product->id, ['qty' => $qty]);
        }

        return $plan->fresh();
    }

    /** An order placed the real way, so the GST snapshot is the real one. */
    private function place(Plan $plan, ?Member $member = null): Order
    {
        $order = app(\App\Services\OrderService::class)->place($member ?? $this->member, $plan);
        $order->update(['billing_name' => 'Invoice Buyer', 'billing_phone' => '9712345678', 'billing_email' => 'buyer@example.com', 'billing_address' => "12 Park Street\nAhmedabad 380001"]);

        return $order->fresh();
    }

    private function approve(Order $order): Order
    {
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'UTR'.$order->id, 'status' => 'pending']);
        app(PaymentService::class)->verify($payment, $this->admin);

        return $order->fresh();
    }

    private function paidOrder(?Plan $plan = null, ?Member $member = null): Order
    {
        $plan ??= $this->plan([[$this->product('Immunity Booster', 1200, 12), 1], [$this->product('Daily Detox', 1000, 12), 1]]);

        return $this->approve($this->place($plan, $member));
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function memberInvoice(Order $order, ?Member $as = null)
    {
        return $this->actingAs($as ?? $this->member, 'member')->get(route('member.orders.invoice', $order));
    }

    // ------------------------------------------------------------------------------------------ numbering

    public function test_an_invoice_number_and_date_are_given_when_the_payment_is_approved(): void
    {
        $order = $this->paidOrder();

        $this->assertSame('INV/2026-27/00001', $order->invoice_number);
        $this->assertSame('2026-10-30', $order->invoice_date->toDateString());
    }

    public function test_numbers_are_consecutive_and_never_repeat(): void
    {
        $numbers = collect(range(1, 5))->map(fn () => $this->paidOrder()->invoice_number)->all();

        $this->assertSame(['INV/2026-27/00001', 'INV/2026-27/00002', 'INV/2026-27/00003', 'INV/2026-27/00004', 'INV/2026-27/00005'], $numbers);
    }

    public function test_an_unpaid_or_cancelled_order_uses_up_no_number(): void
    {
        $plan = $this->plan([[$this->product('Syrup', 1000, 12), 1]]);
        $this->place($plan);                                   // never paid
        $cancelled = $this->place($plan);
        $cancelled->update(['status' => 'cancelled']);

        $this->assertSame('INV/2026-27/00001', $this->approve($this->place($plan))->invoice_number, 'No gaps from unpaid orders');
    }

    public function test_the_numbering_restarts_on_the_first_of_april(): void
    {
        Carbon::setTestNow('2027-03-31 23:00:00');
        $last = $this->paidOrder();
        Carbon::setTestNow('2027-04-01 00:30:00');
        $first = $this->paidOrder();

        $this->assertSame('INV/2026-27/00001', $last->invoice_number);
        $this->assertSame('INV/2027-28/00001', $first->invoice_number);
        $this->assertSame('2026-27', InvoiceService::financialYear(Carbon::parse('2027-03-31')));
        $this->assertSame('2027-28', InvoiceService::financialYear(Carbon::parse('2027-04-01')));
    }

    public function test_issuing_twice_keeps_the_same_number(): void
    {
        $order = $this->paidOrder();
        $before = $order->invoice_number;

        app(InvoiceService::class)->issue($order);
        app(InvoiceService::class)->issue($order->fresh());

        $this->assertSame($before, $order->fresh()->invoice_number);
        $this->assertSame(1, (int) \DB::table('invoice_sequences')->value('last_number'));
    }

    public function test_a_payment_confirmed_by_the_gateway_is_numbered_too(): void
    {
        $order = $this->place($this->plan([[$this->product('Syrup', 1000, 12), 1]]));

        app(PaymentService::class)->confirmGatewayPayment($order, 'pay_TEST123');

        $this->assertSame('INV/2026-27/00001', $order->fresh()->invoice_number);
    }

    public function test_an_order_paid_before_invoices_existed_gets_its_number_when_first_opened(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['invoice_number' => null, 'invoice_date' => null])->save();
        \DB::table('invoice_sequences')->delete();

        $this->memberInvoice($order)->assertOk()->assertSee('INV/2026-27/00001');
        $this->assertSame('INV/2026-27/00001', $order->fresh()->invoice_number);
    }

    public function test_the_prefix_can_be_changed_but_only_to_safe_characters(): void
    {
        Setting::set('inv_prefix', 'BSW');
        $this->assertSame('BSW/2026-27/00001', $this->paidOrder()->invoice_number);

        Setting::set('inv_prefix', '<b>x</b>/..');
        $this->assertMatchesRegularExpression('#^INV/2026-27/00002$#', $this->paidOrder()->invoice_number, 'An unsafe prefix falls back to INV');
    }

    public function test_an_invoice_without_a_number_says_so_instead_of_printing_a_blank(): void
    {
        // Paid, but never numbered (for example the database update has not been run yet).
        $order = $this->place($this->plan([[$this->product('Syrup', 1000, 12), 1]]));
        $order->update(['status' => 'paid']);

        $html = view('invoices.show', app(InvoiceService::class)->build($order->fresh()))->render();

        $this->assertStringContainsString('Invoice number missing', $html);
        $this->assertStringContainsString('class="warn no-print"', $html, 'The warning is not printed');
    }

    public function test_number_column_headings_line_up_with_their_figures(): void
    {
        $html = $this->memberInvoice($this->paidOrder())->assertOk()->getContent();

        // Headings of right-aligned and centred columns are aligned the same way as the figures under them.
        $this->assertStringContainsString('table.items th.r { text-align: right; }', $html);
        $this->assertStringContainsString('table.items th.c { text-align: center; }', $html);
        $this->assertStringContainsString('<th class="r">Amount</th>', $html);
        $this->assertStringContainsString('<th class="c">Qty</th>', $html);
    }

    public function test_the_items_table_scrolls_inside_its_own_box_on_narrow_screens(): void
    {
        $html = $this->memberInvoice($this->paidOrder())->assertOk()->getContent();

        $this->assertStringContainsString('class="tablewrap"', $html);
        $this->assertMatchesRegularExpression('/<div class="tablewrap">\s*<table class="items">/', $html);
    }

    public function test_the_payment_strip_has_fixed_column_widths_and_a_short_label_for_online_payments(): void
    {
        $order = $this->place($this->plan([[$this->product('Syrup', 1000, 12), 1]]));
        app(PaymentService::class)->confirmGatewayPayment($order, 'pay_Tu170mkohOtQhcLongReference123');

        $html = $this->memberInvoice($order->fresh())->assertOk()->assertSee('Online (Razorpay)')->assertDontSee('Online payment (Razorpay)')->getContent();

        $this->assertStringContainsString('table-layout: fixed', $html);
        $this->assertStringContainsString('pay_Tu170mkohOtQhcLongReference123', $html);
    }

    public function test_a_numbered_invoice_shows_no_warning(): void
    {
        $this->memberInvoice($this->paidOrder())->assertOk()->assertDontSee('Invoice number missing');
    }

    // ------------------------------------------------------------------------------------------ content

    public function test_the_invoice_shows_the_company_logo_and_details(): void
    {
        $file = 'logo-test-'.bin2hex(random_bytes(3)).'.png';
        @mkdir(public_path(\App\Support\Site::DIR), 0755, true);
        file_put_contents(public_path(\App\Support\Site::DIR.'/'.$file), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        Setting::set('site_logo', $file);
        Setting::set('inv_legal_name', 'BeyondSure Wellness Pvt. Ltd.');
        Setting::set('inv_gstin', '24ABCDE1234F1Z5');
        Setting::set('inv_pan', 'ABCDE1234F');
        Setting::set('inv_address', "Wellness Tower, MG Road\nAhmedabad, Gujarat 380001");
        Setting::set('inv_state', 'Gujarat');
        Setting::set('site_contact_phone', '+91 79 4000 1234');
        Setting::set('site_contact_email', 'billing@beyondsure.test');

        $this->memberInvoice($this->paidOrder())->assertOk()
            ->assertSee('/'.\App\Support\Site::DIR.'/'.$file, false)
            ->assertSee('BeyondSure Wellness Pvt. Ltd.')->assertSee('GSTIN')->assertSee('24ABCDE1234F1Z5')
            ->assertSee('ABCDE1234F')->assertSee('Wellness Tower, MG Road')->assertSee('Gujarat')
            ->assertSee('+91 79 4000 1234')->assertSee('billing@beyondsure.test')
            ->assertSee('TAX INVOICE');
    }

    public function test_without_a_logo_it_falls_back_to_the_brand_name_and_without_a_gstin_it_is_a_plain_invoice(): void
    {
        $res = $this->memberInvoice($this->paidOrder())->assertOk()->assertSee(\App\Support\Site::name());

        $res->assertDontSee('TAX INVOICE')->assertSee('INVOICE')->assertDontSee('<img', false);
    }

    public function test_it_shows_the_invoice_number_dates_buyer_and_every_product(): void
    {
        $order = $this->paidOrder();

        $this->memberInvoice($order)->assertOk()
            ->assertSee('INV/2026-27/00001')->assertSee('30 Oct 2026')->assertSee($order->order_code)
            ->assertSee('Invoice Buyer')->assertSee('9712345678')->assertSee('buyer@example.com')->assertSee('12 Park Street')->assertSee('Ahmedabad 380001')
            ->assertSee($this->member->member_code)
            ->assertSee('Immunity Booster')->assertSee('Daily Detox')
            ->assertSee('Payment')->assertSee('UPI')->assertSee('UTR'.$order->id);
    }

    public function test_the_totals_gst_split_and_words_are_correct(): void
    {
        // Plan price 2,000 taxable, both products 12%: GST 240, total 2,240.
        $order = $this->paidOrder();
        $this->assertSame(2240.0, (float) $order->amount);

        $this->memberInvoice($order)->assertOk()
            ->assertSee('₹2,000.00')->assertSee('₹240.00')->assertSee('₹2,240.00')
            ->assertSee('CGST')->assertSee('SGST')->assertSee('₹120.00')->assertDontSee('IGST')
            ->assertSee('Rupees Two Thousand Two Hundred Forty Only');
    }

    public function test_igst_is_shown_instead_when_the_company_sells_across_states(): void
    {
        Setting::set('inv_tax_type', 'igst');

        $this->memberInvoice($this->paidOrder())->assertOk()->assertSee('IGST')->assertDontSee('CGST')->assertDontSee('SGST');
    }

    public function test_the_terms_and_a_computer_generated_note_are_printed(): void
    {
        Setting::set('inv_terms', 'Goods once sold will not be taken back.');

        $this->memberInvoice($this->paidOrder())->assertOk()->assertSee('Goods once sold will not be taken back.')->assertSee('computer-generated');
    }

    public function test_the_page_is_a_print_ready_document_with_a_print_button_and_is_kept_out_of_search_engines(): void
    {
        $this->memberInvoice($this->paidOrder())->assertOk()
            ->assertSee('window.print()', false)->assertSee('@media print', false)->assertSee('noindex', false);
    }

    // ------------------------------------------------------------------------------------------ money integrity

    public function test_the_lines_always_add_up_to_the_order_to_the_paisa(): void
    {
        $cases = [
            [[[1200, 12, 1], [1000, 12, 1]], 2000],
            [[[999, 5, 3], [1499, 18, 2], [250, 0, 7]], 3333],
            [[[333.33, 28, 1], [777.77, 12, 3], [10, 18, 11]], 1234.56],
            [[[1, 5, 1], [1, 12, 1], [1, 18, 1]], 100.01],
            [[[5000, 18, 1]], 4999.99],
        ];

        foreach ($cases as $i => [$products, $price]) {
            $items = collect($products)->map(fn ($p, $k) => [$this->product("P{$i}-{$k}", $p[0], $p[1]), $p[2]])->all();
            $order = $this->paidOrder($this->plan($items, $price));
            $lines = app(InvoiceService::class)->lines($order);

            $this->assertEqualsWithDelta((float) $order->taxable_amount, round($lines->sum('taxable'), 2), 0.001, "case {$i} taxable");
            $this->assertEqualsWithDelta((float) $order->gst_amount, round($lines->sum('gst'), 2), 0.001, "case {$i} gst");
            $this->assertEqualsWithDelta((float) $order->amount, round($lines->sum('total'), 2), 0.001, "case {$i} total");
        }
    }

    public function test_a_later_gst_rate_change_does_not_change_an_issued_invoice(): void
    {
        $syrup = $this->product('Syrup', 1000, 12);
        $order = $this->paidOrder($this->plan([[$syrup, 1]], 1000));
        $before = app(InvoiceService::class)->lines($order);

        $syrup->update(['gst_pct' => 18]);
        $after = app(InvoiceService::class)->lines($order->fresh());

        $this->assertEqualsWithDelta((float) $order->gst_amount, round($after->sum('gst'), 2), 0.001, 'Still the GST that was charged');
        $this->assertEquals($before->sum('gst'), $after->sum('gst'));
    }

    public function test_a_zero_gst_order_shows_zero_tax_and_a_matching_total(): void
    {
        $order = $this->paidOrder($this->plan([[$this->product('Exempt item', 500, 0), 1]], 500));

        $this->memberInvoice($order)->assertOk()->assertSee('₹500.00')->assertSee('Rupees Five Hundred Only');
        $this->assertSame(0.0, (float) $order->gst_amount);
    }

    // ------------------------------------------------------------------------------------------ who may open it

    public function test_an_unpaid_order_has_no_invoice(): void
    {
        $order = $this->place($this->plan([[$this->product('Syrup', 1000, 12), 1]]));

        $this->memberInvoice($order)->assertNotFound();
        $this->assertNull($order->fresh()->invoice_number);
    }

    public function test_a_cancelled_order_has_no_invoice(): void
    {
        $order = $this->paidOrder();
        $order->update(['status' => 'cancelled']);

        $this->memberInvoice($order)->assertNotFound();
    }

    public function test_a_member_cannot_open_somebody_elses_invoice(): void
    {
        $order = $this->paidOrder();
        $other = $this->makeMember('Someone Else');

        $this->memberInvoice($order, $other)->assertForbidden();
    }

    public function test_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $order = $this->paidOrder();

        $this->get(route('member.orders.invoice', $order))->assertRedirect(route('login'));
        $this->get(route('admin.orders.invoice', $order))->assertRedirect(route('admin.login'));
    }

    public function test_the_admin_can_open_any_invoice_and_sees_the_same_one(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->admin, 'web')->get(route('admin.orders.invoice', $order))->assertOk()
            ->assertSee('INV/2026-27/00001')->assertSee('Invoice Buyer')->assertSee('₹2,240.00');
    }

    public function test_staff_need_orders_access_and_region_limited_staff_stay_in_their_region(): void
    {
        $order = $this->paidOrder();
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);

        $this->actingAs($this->sub(['members']), 'web')->get(route('admin.orders.invoice', $order))->assertForbidden();
        $this->actingAs($this->sub(['orders']), 'web')->get(route('admin.orders.invoice', $order))->assertOk();
        $this->actingAs($this->sub(['orders'], [$gj->id]), 'web')->get(route('admin.orders.invoice', $order))->assertNotFound();
    }

    public function test_invoices_show_no_cost_price_margin_or_bv(): void
    {
        $this->memberInvoice($this->paidOrder())->assertOk()
            ->assertDontSee('Business Volume')->assertDontSee('BV')->assertDontSee('Cost')->assertDontSee('Margin');
    }

    public function test_what_the_customer_typed_is_escaped(): void
    {
        $order = $this->paidOrder();
        $order->update(['billing_name' => '<script>alert(1)</script>', 'billing_address' => '<img src=x onerror=alert(2)>']);
        Setting::set('inv_terms', '<script>alert(3)</script>');

        $this->memberInvoice($order)->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false)->assertDontSee('<script>alert(3)</script>', false);
    }

    // ------------------------------------------------------------------------------------------ links

    public function test_paid_orders_get_an_invoice_link_in_the_members_order_list_and_unpaid_ones_do_not(): void
    {
        $paid = $this->paidOrder();
        $unpaid = $this->place($this->plan([[$this->product('Syrup', 1000, 12), 1]]));

        $this->actingAs($this->member->fresh(), 'member')->get(route('member.orders.index'))->assertOk()
            ->assertSee(route('member.orders.invoice', $paid), false)->assertDontSee(route('member.orders.invoice', $unpaid), false);
    }

    public function test_the_checkout_page_offers_the_invoice_once_the_payment_is_approved(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->member->fresh(), 'member')->get(route('member.orders.pay', $order))->assertOk()
            ->assertSee('Download invoice')->assertSee(route('member.orders.invoice', $order), false);
    }

    public function test_the_admin_order_list_links_to_paid_invoices(): void
    {
        $paid = $this->paidOrder();
        $unpaid = $this->place($this->plan([[$this->product('Syrup', 1000, 12), 1]]));

        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk()
            ->assertSee(route('admin.orders.invoice', $paid), false)->assertDontSee(route('admin.orders.invoice', $unpaid), false);
    }

    // ------------------------------------------------------------------------------------------ invoice settings

    private function fields(array $extra = []): array
    {
        return $extra + ['site_name' => 'BeyondSure+'];
    }

    public function test_the_super_admin_can_save_the_invoice_details_from_website_settings(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), $this->fields([
            'inv_legal_name' => 'BeyondSure Wellness Pvt. Ltd.', 'inv_gstin' => '24abcde1234f1z5', 'inv_pan' => 'abcde1234f',
            'inv_address' => "Line 1\nLine 2", 'inv_state' => 'Gujarat', 'inv_tax_type' => 'igst', 'inv_prefix' => 'BSW', 'inv_terms' => 'Terms here',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('24ABCDE1234F1Z5', Setting::get('inv_gstin'), 'stored in capitals');
        $this->assertSame('ABCDE1234F', Setting::get('inv_pan'));
        $this->assertSame('igst', Setting::get('inv_tax_type'));
        $this->actingAs($this->admin, 'web')->get(route('admin.site-content.edit'))->assertOk()->assertSee('Invoice details')->assertSee('BeyondSure Wellness Pvt. Ltd.');
    }

    public function test_bad_invoice_details_are_refused(): void
    {
        foreach ([['inv_gstin' => '12345'], ['inv_gstin' => '24ABCDE1234F1Z'], ['inv_pan' => 'ABC123'], ['inv_tax_type' => 'vat'], ['inv_prefix' => 'A B'], ['inv_prefix' => 'TOOLONGPREFIX']] as $fields) {
            $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), $this->fields($fields))->assertSessionHasErrors(array_key_first($fields));
        }
    }

    public function test_staff_without_finance_access_cannot_see_or_change_the_invoice_details(): void
    {
        Setting::set('inv_gstin', '24ABCDE1234F1Z5');
        $content = $this->sub(['content']);

        $this->actingAs($content, 'web')->get(route('admin.site-content.edit'))->assertOk()->assertDontSee('Invoice details')->assertDontSee('24ABCDE1234F1Z5');
        $this->actingAs($content, 'web')->put(route('admin.site-content.update'), $this->fields(['inv_gstin' => '29AAAAA0000A1Z5']))->assertSessionHasNoErrors();

        $this->assertSame('24ABCDE1234F1Z5', Setting::get('inv_gstin'));
    }

    public function test_the_invoice_details_never_reach_the_public_homepage_settings(): void
    {
        $this->assertArrayNotHasKey('inv_gstin', \App\Http\Controllers\Admin\SiteContentController::fields());
        $this->assertArrayNotHasKey('pay_upi_id', \App\Http\Controllers\Admin\SiteContentController::fields());
    }
}
