<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Refunds: a clear action on a paid order, a record of how the money went back, and one page listing them all. */
class RefundsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($p->id, ['qty' => 1]);
    }

    private function paid(?Region $region = null, string $name = 'Refund Rita'): Order
    {
        static $n = 0;
        $n++;
        $m = Member::create(['member_code' => 'RF'.$n, 'name' => $name, 'phone' => '90'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'email' => 'rf'.$n.'@example.com', 'status' => 'inactive', 'region_id' => ($region ?? $this->region)->id]);
        $plan = $region ? Plan::create(['name' => 'R'.$n, 'slug' => 'r'.$n, 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $region->id]) : $this->plan;
        $order = app(OrderService::class)->place($m, $plan);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'U'.$order->id), $this->admin);

        return $order->fresh();
    }

    private function refund(Order $o, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->post(route('admin.orders.credit-note.store', $o), $extra + ['reason' => 'Customer asked for a refund', 'refund_method' => 'upi', 'refund_reference' => 'RFND123']);
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    public function test_the_paid_order_has_a_button_called_refund_with_the_fields_to_fill_in(): void
    {
        $this->paid();

        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk()
            ->assertSee('Refund')->assertSee('name="refund_method"', false)->assertSee('name="refund_reference"', false)->assertSee('name="reason"', false);
    }

    public function test_how_the_money_went_back_is_recorded_with_the_refund(): void
    {
        $o = $this->paid();
        $this->refund($o, ['refund_method' => 'bank_transfer', 'refund_reference' => 'UTR998877'])->assertSessionHasNoErrors();

        $note = CreditNote::firstOrFail();
        $this->assertSame('bank_transfer', $note->refund_method);
        $this->assertSame('UTR998877', $note->refund_reference);
        $this->assertNotNull($note->refunded_on);
        $this->assertSame('cancelled', $o->fresh()->status);
    }

    public function test_the_method_must_be_a_known_one_and_a_reference_is_needed_except_for_cash(): void
    {
        $o = $this->paid();

        $this->refund($o, ['refund_method' => 'carrier-pigeon'])->assertSessionHasErrors('refund_method');
        $this->refund($o, ['refund_method' => ''])->assertSessionHasErrors('refund_method');
        $this->refund($o, ['refund_method' => 'upi', 'refund_reference' => ''])->assertSessionHasErrors('refund_reference');
        $this->refund($o, ['refund_reference' => str_repeat('x', 300)])->assertSessionHasErrors('refund_reference');
        $this->assertSame(0, CreditNote::count());

        $this->refund($o, ['refund_method' => 'cash', 'refund_reference' => ''])->assertSessionHasNoErrors();
        $this->assertSame(1, CreditNote::count());
    }

    public function test_the_credit_note_and_the_email_say_how_the_customer_was_refunded(): void
    {
        $o = $this->paid();
        $this->refund($o, ['refund_method' => 'upi', 'refund_reference' => 'RFND555']);

        $this->actingAs($this->admin, 'web')->get(route('admin.orders.credit-note', $o))->assertOk()->assertSee('Refunded by')->assertSee('UPI')->assertSee('RFND555');
        $mail = Mail::sent(BrandedMail::class)->first(fn ($m) => str_contains($m->mailSubject, 'Credit note'));
        $this->assertStringContainsString('RFND555', $mail->render());
        $this->assertStringContainsString('UPI', $mail->render());
    }

    public function test_the_refunds_page_lists_every_refund_with_totals(): void
    {
        $a = $this->paid(null, 'Alpha Refund');
        $b = $this->paid(null, 'Beta Refund');
        $this->paid(null, 'Not Refunded');
        $this->refund($a);
        $this->refund($b, ['refund_method' => 'cash', 'refund_reference' => '']);

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.refunds.index'))->assertOk();
        $res->assertSee('Refunds')->assertSee('Alpha Refund')->assertSee('Beta Refund')->assertDontSee('Not Refunded')
            ->assertSee('CN/')->assertSee('RFND123')->assertSee('₹4,480.00');
        $this->assertSame(2, $res->viewData('notes')->total());
        $this->assertEqualsWithDelta(4480.0, $res->viewData('total'), 0.01);
    }

    public function test_the_refunds_page_is_finance_only_in_the_menu_and_stays_inside_the_region(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $main = $this->paid(null, 'Main Refund');
        $guj = $this->paid($gj, 'Gujarat Refund');
        $this->refund($main);
        $this->refund($guj);

        $this->actingAs($this->sub(['orders']), 'web')->get(route('admin.refunds.index'))->assertForbidden();
        $this->actingAs($this->sub(['finance'], [$gj->id]), 'web')->get(route('admin.refunds.index'))->assertOk()->assertSee('Gujarat Refund')->assertDontSee('Main Refund');
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee(route('admin.refunds.index'), false);
        $this->actingAs($this->sub(['orders']), 'web')->get(route('admin.dashboard'))->assertDontSee(route('admin.refunds.index'), false);
    }

    public function test_a_settled_order_explains_why_it_cannot_be_refunded_here(): void
    {
        $o = $this->paid();
        $o->update(['status' => 'settled']);

        $this->refund($o)->assertSessionHas('error');
        $this->assertSame(0, CreditNote::count());
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk()->assertSee('Settled orders cannot be refunded here');
    }

    public function test_refund_details_are_escaped(): void
    {
        $o = $this->paid();
        $this->refund($o, ['refund_reference' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin, 'web')->get(route('admin.refunds.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.credit-note', $o))->assertDontSee('<script>alert(1)</script>', false);
    }
}
