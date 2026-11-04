<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Cycle;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Rank;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CycleService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Every part of the business sends the right email to the right person, and an email problem never breaks the business. */
class MailNotificationsTest extends TestCase
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
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $prod = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($prod->id, ['qty' => 1]);
        Setting::set('site_contact_email', 'office@beyondsure.test');
    }

    private function member(string $name, ?string $email = 'auto', string $status = 'inactive', ?Member $sponsor = null, ?Region $region = null): Member
    {
        $this->n++;

        return Member::create([
            'member_code' => 'ML'.$this->n, 'name' => $name, 'phone' => '96'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT),
            'email' => $email === 'auto' ? 'm'.$this->n.'@example.com' : $email, 'status' => $status, 'region_id' => ($region ?? $this->region)->id,
            'sponsor_id' => $sponsor?->id, 'placement_id' => $sponsor?->id, 'position' => $sponsor ? 'left' : null, 'path' => $sponsor ? (($sponsor->path ?? '').$sponsor->id.'.') : null,
        ]);
    }

    private function mails(string $email)
    {
        return Mail::sent(BrandedMail::class)->filter(fn (BrandedMail $m) => $m->hasTo($email))->values();
    }

    private function assertMailed(string $email, string $subjectPart, int $times = 1): void
    {
        $found = $this->mails($email)->filter(fn (BrandedMail $m) => str_contains($m->mailSubject, $subjectPart));
        $this->assertCount($times, $found, "Expected {$times} mail(s) to {$email} with \"{$subjectPart}\"; got subjects: ".$this->mails($email)->pluck('mailSubject')->implode(' | '));
    }

    private function placeOrder(Member $m, ?Plan $plan = null): Order
    {
        return app(OrderService::class)->place($m, $plan ?? $this->plan);
    }

    private function submit(Order $order): Payment
    {
        return app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id);
    }

    private function approve(Order $order): Order
    {
        $payment = $order->payment ?? $this->submit($order);
        app(PaymentService::class)->verify($payment, $this->admin);

        return $order->fresh();
    }

    // ------------------------------------------------------------------------------------------ registration

    public function test_registering_welcomes_the_member_and_tells_the_sponsor(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');

        $this->get(route('register', ['sponsor' => $sponsor->member_code, 'side' => 'left', 'plan' => $this->plan->id]));
        $this->post(route('register.send'), ['name' => 'New Nisha', 'phone' => '9811122233', 'email' => 'nisha@example.com', 'plan_id' => $this->plan->id]);
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        $this->assertMailed('nisha@example.com', 'Welcome');
        $this->assertMailed('sam@example.com', 'New Nisha');
        $this->assertMailed('nisha@example.com', 'Complete your payment');   // the order placed for the chosen plan
        $welcome = $this->mails('nisha@example.com')->first(fn ($m) => str_contains($m->mailSubject, 'Welcome'));
        $this->assertStringContainsString('New Nisha', $welcome->render());
    }

    public function test_someone_who_gave_no_email_is_simply_not_emailed(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');

        $this->get(route('register', ['sponsor' => $sponsor->member_code, 'plan' => $this->plan->id]));
        $this->post(route('register.send'), ['name' => 'No Email', 'phone' => '9811122244', 'plan_id' => $this->plan->id]);
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        $this->assertDatabaseHas('members', ['phone' => '9811122244']);
        $this->assertMailed('sam@example.com', 'No Email');   // the sponsor is still told
        Mail::assertSentCount(1);                            // only the sponsor's note: there is no address for the new member
    }

    // ------------------------------------------------------------------------------------------ orders and payments

    public function test_placing_an_order_emails_a_pay_now_link(): void
    {
        $m = $this->member('Buyer Bela', 'bela@example.com');
        $order = $this->placeOrder($m);

        $mail = $this->mails('bela@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Complete your payment'));
        $this->assertNotNull($mail);
        $this->assertSame(route('member.orders.pay', $order), $mail->ctaUrl);
        $this->assertStringContainsString('₹2,240', $mail->render());
    }

    public function test_sending_a_payment_confirms_receipt_to_the_member_and_alerts_the_office(): void
    {
        $m = $this->member('Buyer Bela', 'bela@example.com');
        $this->submit($this->placeOrder($m));

        $this->assertMailed('bela@example.com', 'We have your payment');
        $this->assertMailed('office@beyondsure.test', 'Payment to verify');
    }

    public function test_an_approved_payment_sends_the_purchase_confirmation_with_a_working_invoice_link(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');
        $m = $this->member('Buyer Bela', 'bela@example.com', 'inactive', $sponsor);
        $order = $this->approve($this->placeOrder($m));

        $mail = $this->mails('bela@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Payment confirmed'));
        $this->assertNotNull($mail);
        $this->assertStringContainsString($order->invoice_number, $mail->render());

        // The button opens the invoice with no sign-in, because the link is signed.
        $this->get($mail->ctaUrl)->assertOk()->assertSee($order->invoice_number);
    }

    public function test_the_sponsor_hears_when_their_person_becomes_active_but_not_on_a_repeat_purchase(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');
        $m = $this->member('Buyer Bela', 'bela@example.com', 'inactive', $sponsor);

        $this->approve($this->placeOrder($m));
        $this->assertMailed('sam@example.com', 'Buyer Bela is now active');

        $this->approve($this->placeOrder($m->fresh()));
        $this->assertMailed('sam@example.com', 'Buyer Bela is now active');   // still only once
    }

    public function test_a_gateway_confirmed_payment_sends_the_same_emails(): void
    {
        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');
        $m = $this->member('Buyer Bela', 'bela@example.com', 'inactive', $sponsor);

        app(PaymentService::class)->confirmGatewayPayment($this->placeOrder($m), 'pay_TEST1');

        $this->assertMailed('bela@example.com', 'Payment confirmed');
        $this->assertMailed('sam@example.com', 'Buyer Bela is now active');
    }

    public function test_a_rejected_payment_tells_the_member_how_to_try_again(): void
    {
        $m = $this->member('Buyer Bela', 'bela@example.com');
        $order = $this->placeOrder($m);
        app(PaymentService::class)->reject($this->submit($order), $this->admin);

        $mail = $this->mails('bela@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'could not be verified'));
        $this->assertNotNull($mail);
        $this->assertSame(route('member.orders.pay', $order), $mail->ctaUrl);
    }

    // ------------------------------------------------------------------------------------------ money out

    private function activeWithBalance(float $amount = 5000): Member
    {
        $m = $this->member('Earner Esha', 'esha@example.com', 'active');
        $m->forceFill(['bank_account_name' => 'Esha', 'bank_account_number' => '123456789012', 'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC'])->save();
        \App\Models\LedgerEntry::create(['member_id' => $m->id, 'type' => 'sponsor', 'amount' => $amount, 'description' => 'seed']);

        return $m->fresh();
    }

    public function test_a_withdrawal_request_alerts_the_office_and_each_decision_emails_the_member(): void
    {
        $m = $this->activeWithBalance();
        $svc = app(WithdrawalService::class);

        $w = $svc->request($m, 1000);
        $this->assertMailed('office@beyondsure.test', 'Withdrawal request');
        $this->assertMailed('esha@example.com', 'Withdrawal request received');

        $svc->approve($w, $this->admin);
        $this->assertMailed('esha@example.com', 'Withdrawal approved');

        $svc->markPaid($w->fresh(), 'PAYREF1');
        $paid = $this->mails('esha@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Withdrawal paid'));
        $this->assertNotNull($paid);
        $this->assertStringContainsString('PAYREF1', $paid->render());

        $w2 = $svc->request($m, 500);
        $svc->reject($w2, $this->admin);
        $this->assertMailed('esha@example.com', 'Withdrawal declined');
    }

    public function test_approving_a_cycle_emails_each_earner_their_total_and_any_new_rank(): void
    {
        Rank::query()->delete();
        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');
        $buyer = $this->member('Buyer Bela', 'bela@example.com', 'inactive', $sponsor);
        $this->approve($this->placeOrder($buyer));

        $cycle = app(CycleService::class)->create(now()->subMonth()->toDateString(), now()->addDay()->toDateString());
        app(CycleService::class)->approve($cycle, $this->admin);

        $income = $this->mails('sam@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'income'));
        $this->assertNotNull($income, 'The sponsor earned a sponsor bonus and is told');
        $this->assertStringContainsString('₹', $income->render());
        // The buyer earned self income too.
        $this->assertNotNull($this->mails('bela@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'income')));
        $this->assertNotNull(Cycle::first());
    }

    // ------------------------------------------------------------------------------------------ an email problem never breaks the business

    public function test_a_broken_mail_server_does_not_stop_a_payment_from_being_approved(): void
    {
        config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
        Mail::swap(app('mail.manager'));   // use the real (broken) mailer instead of the fake

        $sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');
        $m = $this->member('Buyer Bela', 'bela@example.com', 'inactive', $sponsor);
        $order = $this->approve($this->placeOrder($m));

        $this->assertSame('paid', $order->status);
        $this->assertSame('active', $m->fresh()->status);
        $this->assertNotNull($order->invoice_number);
    }

    public function test_an_invalid_stored_email_address_is_skipped_quietly(): void
    {
        $m = $this->member('Buyer Bela', 'not-an-email');
        $this->placeOrder($m);

        $this->assertCount(0, $this->mails('not-an-email'));
    }

    // ------------------------------------------------------------------------------------------ contact form

    public function test_the_contact_form_forwards_the_message_to_the_office(): void
    {
        $this->post(route('contact.store'), ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello there'])->assertSessionHasNoErrors();

        $mail = $this->mails('office@beyondsure.test')->first(fn ($x) => str_contains($x->mailSubject, 'New message'));
        $this->assertNotNull($mail);
        $this->assertStringContainsString('Hello there', $mail->render());
    }

    public function test_without_an_office_email_set_no_alert_is_attempted(): void
    {
        \DB::table('settings')->where('key', 'site_contact_email')->delete();
        \Cache::flush();

        $m = $this->member('Buyer Bela', 'bela@example.com');
        $this->submit($this->placeOrder($m));

        $this->assertCount(0, Mail::sent(BrandedMail::class)->filter(fn ($x) => str_contains($x->mailSubject, 'Payment to verify')));
    }

    // ------------------------------------------------------------------------------------------ optional emails and unsubscribe

    public function test_unsubscribing_stops_optional_emails_only(): void
    {
        $m = $this->member('Buyer Bela', 'bela@example.com');
        $url = URL::signedRoute('email.unsubscribe', ['member' => $m->id]);

        $this->get($url)->assertOk()->assertSee('unsubscribed');
        $this->assertTrue((bool) $m->fresh()->email_opt_out);

        $this->placeOrder($m->fresh());
        $this->assertMailed('bela@example.com', 'Complete your payment');      // their own order: still sent

        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'Hello all', 'body' => 'News', 'audience' => 'all', 'send_email' => 1]);
        $this->assertCount(0, $this->mails('bela@example.com')->filter(fn ($x) => str_contains($x->mailSubject, 'Hello all')));
    }

    public function test_the_unsubscribe_link_cannot_be_forged_or_reused_for_somebody_else(): void
    {
        $m = $this->member('Buyer Bela', 'bela@example.com');
        $other = $this->member('Other Omar', 'omar@example.com');

        $this->get(route('email.unsubscribe', ['member' => $m->id]))->assertForbidden();   // unsigned
        $forged = str_replace('/unsubscribe/'.$m->id, '/unsubscribe/'.$other->id, URL::signedRoute('email.unsubscribe', ['member' => $m->id]));
        $this->assertNotSame(URL::signedRoute('email.unsubscribe', ['member' => $m->id]), $forged, 'the test really changed the link');
        $this->get($forged)->assertForbidden();
        $this->assertFalse((bool) $other->fresh()->email_opt_out);
    }

    public function test_optional_emails_carry_an_unsubscribe_link_but_order_emails_do_not(): void
    {
        $m = $this->member('Buyer Bela', 'bela@example.com');

        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'Big news', 'body' => 'Body', 'audience' => 'all', 'send_email' => 1]);
        $news = $this->mails('bela@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Big news'));
        $this->assertStringContainsString('/email/unsubscribe/'.$m->id, $news->render());

        $this->placeOrder($m);
        $order = $this->mails('bela@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Complete your payment'));
        $this->assertStringNotContainsString('/email/unsubscribe', $order->render());
    }

    // ------------------------------------------------------------------------------------------ the message itself

    public function test_emails_carry_the_brand_and_escape_what_people_typed(): void
    {
        Setting::set('site_name', 'Acme Wellness');
        $m = $this->member('<script>alert(1)</script>', 'bela@example.com');
        $this->placeOrder($m);

        $html = $this->mails('bela@example.com')->first()->render();

        $this->assertStringContainsString('Acme Wellness', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
