<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CycleService;
use App\Services\OtpService;
use App\Services\PaymentService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Regression tests for the security audit findings (F01 to F15). Each one asserts the SECURE behaviour.
 */
class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@sec.test', 'password' => 'CorrectHorse9', 'role' => 'admin', 'is_active' => true]);
    }

    private function member(string $code, string $phone, ?Region $r = null): Member
    {
        return Member::create(['member_code' => $code, 'name' => $code, 'phone' => $phone, 'status' => 'active', 'region_id' => $r?->id]);
    }

    private function plan(float $price = 1000, bool $active = true, ?Region $r = null, float $bv = 400): Plan
    {
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C', 'is_free' => false]);
        $p = Plan::create(['name' => 'Plan'.random_int(1, 99999), 'slug' => 's'.random_int(1, 99999), 'price' => $price, 'bv' => $bv, 'is_active' => $active, 'region_id' => $r?->id]);
        $prod = Product::firstOrCreate(['sku' => 'P1'], ['category_id' => $cat->id, 'name' => 'P', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $p->products()->syncWithoutDetaching([$prod->id => ['qty' => 1]]);

        return $p;
    }

    private function order(Member $m, Plan $p, string $status = 'pending_payment'): Order
    {
        return Order::create(['order_code' => 'O'.random_int(100000, 999999), 'member_id' => $m->id, 'plan_id' => $p->id, 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => $p->bv, 'status' => $status]);
    }

    private function withBank(Member $m): Member
    {
        $m->update(['bank_account_name' => 'E', 'bank_account_number' => '123456789012', 'bank_ifsc' => 'HDFC0001234']);

        return $m->fresh();
    }

    // -------------------------------------------------------------------------------- F02 OTP exposure

    public function test_F02_the_otp_is_never_returned_or_logged_outside_local_development(): void
    {
        // Pretend to be production. The test runner normally skips CSRF only in the "testing" environment.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]); // the misconfiguration found in .env
        $this->member('V1', '9811100001');
        Log::spy();

        $this->post(route('login.send'), ['phone' => '9811100001'])->assertRedirect(route('login.verify'));

        $this->assertNull(session('debug_code'), 'The code must not be handed to the requester');
        $this->get(route('login.verify'))->assertDontSee('Your OTP is');
        Log::shouldNotHaveReceived('info', fn ($message) => str_contains((string) $message, 'OTP for'));
    }

    public function test_F02_local_development_still_shows_the_code(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
        $this->member('V2', '9811100002');

        $this->post(route('login.send'), ['phone' => '9811100002']);

        $this->assertNotNull(session('debug_code'));
    }

    // -------------------------------------------------------------------------------- F01 admin brute force

    public function test_F01_admin_login_is_rate_limited_per_account_and_address(): void
    {
        $this->admin();
        $codes = [];
        for ($i = 0; $i < 7; $i++) {
            $codes[] = $this->post(route('admin.login.submit'), ['email' => 'admin@sec.test', 'password' => 'wrong'.$i])->getStatusCode();
        }

        $this->assertContains(429, $codes, 'Repeated wrong passwords must be throttled');
        // While locked out, even the right password is refused.
        $this->post(route('admin.login.submit'), ['email' => 'admin@sec.test', 'password' => 'CorrectHorse9'])->assertStatus(429);
    }

    public function test_F01_a_normal_login_is_unaffected(): void
    {
        $this->admin();

        $this->post(route('admin.login.submit'), ['email' => 'admin@sec.test', 'password' => 'CorrectHorse9'])->assertRedirect(route('admin.dashboard'));
    }

    // -------------------------------------------------------------------------------- F04 OTP flooding

    public function test_F04_only_a_few_codes_can_be_issued_per_number_per_hour(): void
    {
        $sent = 0;
        foreach (range(1, 12) as $i) {
            Carbon::setTestNow(now()->addSeconds(61));
            $sent += app(OtpService::class)->issue('9811100003', 'login')['throttled'] ? 0 : 1;
        }
        Carbon::setTestNow();

        $this->assertLessThanOrEqual(5, $sent, 'At most 5 codes per hour per number');
        $this->assertGreaterThanOrEqual(1, $sent);
    }

    public function test_F04_the_member_otp_endpoints_are_rate_limited_per_address(): void
    {
        $codes = [];
        foreach (range(1, 14) as $i) {
            $codes[] = $this->post(route('login.send'), ['phone' => '98111'.str_pad((string) (30000 + $i), 5, '0')])->getStatusCode();
        }

        $this->assertContains(429, $codes);
    }

    // -------------------------------------------------------------------------------- F09 withdrawal state machine

    public function test_F09_a_paid_withdrawal_cannot_be_approved_rejected_or_paid_again(): void
    {
        $admin = $this->admin();
        $m = $this->withBank($this->member('E1', '9811100008'));
        LedgerEntry::create(['member_id' => $m->id, 'type' => 'self', 'amount' => 1000, 'description' => 'x']);
        $w = app(WithdrawalService::class)->request($m, 400);

        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.approve', $w));
        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => 'UTR-A']);
        $this->assertSame('paid', $w->fresh()->status);

        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.approve', $w))->assertSessionHasErrors();
        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.reject', $w))->assertSessionHasErrors();
        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => 'UTR-B'])->assertSessionHasErrors();

        $this->assertSame('paid', $w->fresh()->status);
        $this->assertSame(1, LedgerEntry::where('withdrawal_id', $w->id)->count(), 'Exactly one debit');
    }

    public function test_F09_only_pending_requests_can_be_approved_and_rejected_ones_stay_rejected(): void
    {
        $admin = $this->admin();
        $m = $this->withBank($this->member('E2', '9811100009'));
        LedgerEntry::create(['member_id' => $m->id, 'type' => 'self', 'amount' => 1000, 'description' => 'x']);
        $w = app(WithdrawalService::class)->request($m, 100);

        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.reject', $w))->assertSessionHasNoErrors();
        $this->actingAs($admin, 'web')->put(route('admin.withdrawals.approve', $w))->assertSessionHasErrors();
        $this->assertSame('rejected', $w->fresh()->status);
    }

    // -------------------------------------------------------------------------------- F10 cycle double-run

    public function test_F10_a_stale_cycle_object_cannot_be_approved_a_second_time(): void
    {
        $admin = $this->admin();
        $m = $this->member('C1', '9811100010');
        $this->order($m, $this->plan(), 'paid');

        $cycle = app(CycleService::class)->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $stale = Cycle::findOrFail($cycle->id);            // an admin's browser tab still showing "draft"
        app(CycleService::class)->approve($cycle, $admin);  // another admin approves first

        $late = $this->order($m, $this->plan(), 'paid');    // a new payment arrives after the cycle closed

        try {
            app(CycleService::class)->approve($stale, $admin);
            $this->fail('A cycle that is already approved must not run again');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already been approved', $e->getMessage());
        }

        $this->assertNull($late->fresh()->cycle_id, 'The late order must not be pulled into the closed cycle');
    }

    // -------------------------------------------------------------------------------- F12 javascript: links

    public function test_F12_social_links_must_be_http_or_https_urls(): void
    {
        $sub = User::create(['name' => 'S', 'email' => 's@sec.test', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['content'], 'is_active' => true]);

        foreach (['javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:x', 'not a url'] as $bad) {
            $this->actingAs($sub, 'web')->put(route('admin.site-content.update'), ['site_name' => 'B', 'site_facebook_url' => $bad])->assertSessionHasErrors('site_facebook_url');
        }
        $this->actingAs($sub, 'web')->put(route('admin.site-content.update'), ['site_name' => 'B', 'site_facebook_url' => 'https://facebook.com/acme'])->assertSessionHasNoErrors();
    }

    public function test_F12_an_unsafe_stored_value_is_never_rendered_as_a_link(): void
    {
        Setting::set('site_facebook_url', 'javascript:alert(1)'); // e.g. saved before the fix

        $this->get('/')->assertOk()->assertDontSee('javascript:alert', false);
    }

    // -------------------------------------------------------------------------------- F11 CSV injection

    public function test_F11_csv_cells_that_start_with_a_formula_character_are_neutralised(): void
    {
        $m = Member::create(['member_code' => 'EVIL1', 'name' => '=HYPERLINK("http://evil.test","x")', 'phone' => '9811100011', 'status' => 'active']);
        $this->order($m, $this->plan(), 'settled');

        $csv = $this->actingAs($this->admin(), 'web')->get(route('admin.reports.sales.purchases.csv'))->assertOk()->streamedContent();

        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_F11_real_numbers_and_normal_text_are_left_alone(): void
    {
        $out = fopen('php://memory', 'w+');
        $csv = app(\App\Services\ReportService::class)->csv([["Plain Name", -400.5, "-12.00", 1200, "9811100011"]], ["A", "B", "C", "D", "E"]);
        ob_start();
        $csv->sendContent();
        $body = ob_get_clean();
        fclose($out);

        $this->assertStringContainsString('"Plain Name",-400.5,-12.00,1200,9811100011', $body);
        $this->assertStringContainsString('9811100011', $body);
    }

    // -------------------------------------------------------------------------------- F07 / F06 payment states

    public function test_F07_verifying_a_payment_never_revives_a_cancelled_or_settled_order(): void
    {
        $admin = $this->admin();
        $m = $this->member('B3', '9811100012');

        foreach (['cancelled', 'settled'] as $status) {
            $order = $this->order($m, $this->plan(), $status);
            $payment = Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'UTR-'.$status, 'status' => 'pending']);

            $this->actingAs($admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasErrors();

            $this->assertSame($status, $order->fresh()->status);
            $this->assertSame('pending', $payment->fresh()->status);
        }
    }

    public function test_F07_a_payment_can_only_be_verified_once(): void
    {
        $admin = $this->admin();
        $m = $this->member('B4', '9811100013');
        $order = $this->order($m, $this->plan());
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'UTR-ONCE', 'status' => 'pending']);

        $this->actingAs($admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasNoErrors();
        $this->actingAs($admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasErrors();
        $this->actingAs($admin, 'web')->put(route('admin.payments.reject', $payment))->assertSessionHasErrors();

        $this->assertSame('verified', $payment->fresh()->status);
    }

    public function test_F06_a_member_cannot_submit_a_payment_on_a_settled_or_cancelled_order(): void
    {
        $m = $this->member('B5', '9811100014');
        $settled = $this->order($m, $this->plan(), 'settled');
        Payment::create(['order_id' => $settled->id, 'method' => 'razorpay', 'reference' => 'pay_REAL', 'status' => 'verified', 'verified_at' => now()]);
        $cancelled = $this->order($m, $this->plan(), 'cancelled');
        $body = ['method' => 'upi', 'reference' => 'FAKE', 'billing_name' => 'X', 'billing_phone' => '9811100014'];

        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $settled), $body)->assertSessionHasErrors();
        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $cancelled), $body)->assertSessionHasErrors();

        $this->assertSame('pay_REAL', $settled->fresh()->payment->reference);
        $this->assertSame('verified', $settled->fresh()->payment->status);
        $this->assertSame(0, Payment::where('order_id', $cancelled->id)->count());
    }

    public function test_F06_a_gateway_confirmation_replayed_on_a_settled_order_changes_nothing(): void
    {
        $m = $this->member('B6', '9811100015');
        $order = $this->order($m, $this->plan(), 'settled');
        $order->update(['cycle_id' => null]);

        app(PaymentService::class)->confirmGatewayPayment($order, 'pay_REPLAY');

        $this->assertSame('settled', $order->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    // -------------------------------------------------------------------------------- F05 inactive plan

    public function test_F05_a_deactivated_plan_cannot_be_bought(): void
    {
        $m = $this->member('B7', '9811100016');
        $plan = $this->plan(1000, false);

        $this->actingAs($m, 'member')->post(route('member.plans.buy', $plan))->assertSessionHasErrors('plan');

        $this->assertSame(0, Order::where('plan_id', $plan->id)->count());
    }

    // -------------------------------------------------------------------------------- F08 reused reference

    public function test_F08_a_payment_reference_cannot_be_reused_on_another_order(): void
    {
        $m = $this->member('B8', '9811100017');
        $plan = $this->plan();
        $first = $this->order($m, $plan);
        $second = $this->order($m, $plan);
        $body = ['method' => 'upi', 'reference' => 'UTR-SAME-1', 'billing_name' => 'X', 'billing_phone' => '9811100017'];

        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $first), $body)->assertSessionHasNoErrors();
        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $second), ['reference' => 'utr-same-1'] + $body)->assertSessionHasErrors('reference');
        // The same order may resubmit its own reference.
        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $first), $body)->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::where('reference', 'UTR-SAME-1')->count());
    }

    public function test_F08_a_rejected_payments_reference_can_be_used_again(): void
    {
        $m = $this->member('B9', '9811100018');
        $plan = $this->plan();
        $first = $this->order($m, $plan);
        $second = $this->order($m, $plan);
        Payment::create(['order_id' => $first->id, 'method' => 'upi', 'reference' => 'UTR-REJ', 'status' => 'rejected']);

        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $second), ['method' => 'upi', 'reference' => 'UTR-REJ', 'billing_name' => 'X', 'billing_phone' => '9811100018'])->assertSessionHasNoErrors();
    }

    // -------------------------------------------------------------------------------- F13 / F14 / redirect

    public function test_F13_security_headers_are_sent(): void
    {
        $r = $this->get(route('admin.login'));

        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $r->headers->get('Referrer-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $r->headers->get('Content-Security-Policy'));
    }

    public function test_F13_hsts_is_only_sent_over_https(): void
    {
        $this->assertNull($this->get(route('admin.login'))->headers->get('Strict-Transport-Security'));
        $this->assertNotNull($this->get('https://localhost/admin/login')->headers->get('Strict-Transport-Security'));
    }

    public function test_F14_the_contact_form_is_rate_limited(): void
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->post(route('contact.store'), ['name' => 'Spam', 'email' => 's@x.test', 'message' => 'hello'])->getStatusCode();
        }

        $this->assertContains(429, $codes);
        $this->assertLessThanOrEqual(5, DB::table('contact_messages')->count());
    }

    public function test_an_unauthenticated_visit_to_admin_goes_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/members')->assertRedirect(route('admin.login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------------- F15 cycle totals across regions

    public function test_F15_a_region_limited_user_does_not_see_company_wide_cycle_totals(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $mh = Region::create(['name' => 'Maharashtra', 'code' => 'MH']);
        $this->order($this->member('GJ1', '9811100019', $gj), $this->plan(1000, true, $gj, 111), 'paid');
        $this->order($this->member('MH1', '9811100020', $mh), $this->plan(1000, true, $mh, 777), 'paid');
        $cycle = app(CycleService::class)->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $superAdmin = $this->admin();
        app(CycleService::class)->approve($cycle, $superAdmin);

        $limited = User::create(['name' => 'S', 'email' => 'r@sec.test', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['cycles', 'finance'], 'region_ids' => [$gj->id], 'is_active' => true]);

        $this->actingAs($limited, 'web')->get(route('admin.cycles.index'))->assertOk()->assertDontSee('888')->assertDontSee('777');
        // An unrestricted finance user still sees the totals.
        $this->actingAs($superAdmin, 'web')->get(route('admin.cycles.index'))->assertSee('888');
    }

    // -------------------------------------------------------------------------------- sessions after password change

    public function test_other_sessions_are_ended_when_a_password_is_changed_or_reset(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->admin();
        $row = fn (string $id) => ['id' => $id, 'user_id' => $user->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()];
        DB::table('sessions')->insert([$row('stolen-session-1'), $row('stolen-session-2')]);

        $this->actingAs($user, 'web')->put(route('admin.account.password.update'), ['current_password' => 'CorrectHorse9', 'password' => 'BrandNew12345', 'password_confirmation' => 'BrandNew12345'])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->whereIn('id', ['stolen-session-1', 'stolen-session-2'])->count());

        $sub = User::create(['name' => 'S', 'email' => 'sub@sec.test', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => [], 'is_active' => true]);
        DB::table('sessions')->insert(['id' => 'sub-session', 'user_id' => $sub->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        $this->actingAs($user, 'web')->put(route('admin.team.password', $sub))->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->where('id', 'sub-session')->count());
    }
}
