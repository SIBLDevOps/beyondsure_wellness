<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Sharing invoices, inviting people with a plan chosen, announcements, and news about new plans. */
class MemberCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private Plan $plan;

    private User $admin;

    private Member $sponsor;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $this->product = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($this->product->id, ['qty' => 1]);
        $this->sponsor = $this->member('Sponsor Sam', 'sam@example.com', 'active');
    }

    private Product $product;

    private function member(string $name, ?string $email = 'auto', string $status = 'inactive', ?Region $region = null): Member
    {
        $this->n++;

        return Member::create(['member_code' => 'MC'.$this->n, 'name' => $name, 'phone' => '95'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'email' => $email === 'auto' ? 'c'.$this->n.'@example.com' : $email, 'status' => $status, 'region_id' => ($region ?? $this->region)->id]);
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function mails(string $email)
    {
        return Mail::sent(BrandedMail::class)->filter(fn (BrandedMail $m) => $m->hasTo($email))->values();
    }

    private function paid(Member $m): Order
    {
        $order = app(OrderService::class)->place($m, $this->plan);
        $order->update(['billing_name' => $m->name, 'billing_email' => $m->email]);
        app(PaymentService::class)->verify(app(PaymentService::class)->submit($order, 'upi', 'UTR'.$order->id), $this->admin);

        return $order->fresh();
    }

    // ------------------------------------------------------------------------------------------ share an invoice

    public function test_a_signed_invoice_link_opens_without_signing_in_and_a_tampered_one_does_not(): void
    {
        $order = $this->paid($this->member('Buyer Bela', 'bela@example.com'));
        $url = URL::temporarySignedRoute('invoice.shared', now()->addDays(30), ['order' => $order->id]);

        $this->get($url)->assertOk()->assertSee($order->invoice_number)->assertSee('Buyer Bela');
        $this->get($url.'x')->assertForbidden();
        $this->get(route('invoice.shared', ['order' => $order->id]))->assertForbidden();
        $this->get(str_replace('/'.$order->id.'/', '/'.($order->id + 1).'/', $url))->assertForbidden();
    }

    public function test_an_expired_invoice_link_stops_working(): void
    {
        $order = $this->paid($this->member('Buyer Bela', 'bela@example.com'));
        $url = URL::temporarySignedRoute('invoice.shared', now()->addDays(1), ['order' => $order->id]);

        Carbon::setTestNow(now()->addDays(2));
        $this->get($url)->assertForbidden();
        Carbon::setTestNow();
    }

    public function test_a_signed_link_never_shows_an_unpaid_order(): void
    {
        $order = app(OrderService::class)->place($this->member('Buyer Bela', 'bela@example.com'), $this->plan);
        $url = URL::temporarySignedRoute('invoice.shared', now()->addDays(30), ['order' => $order->id]);

        $this->get($url)->assertNotFound();
    }

    public function test_a_member_can_email_their_invoice_to_any_address(): void
    {
        $buyer = $this->member('Buyer Bela', 'bela@example.com');
        $order = $this->paid($buyer);

        $this->actingAs($buyer->fresh(), 'member')->post(route('member.orders.invoice.email', $order), ['email' => 'accounts@company.com'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $mail = $this->mails('accounts@company.com')->first();
        $this->assertNotNull($mail);
        $this->assertStringContainsString($order->invoice_number, $mail->mailSubject);
        $this->get($mail->ctaUrl)->assertOk()->assertSee($order->invoice_number);
    }

    public function test_emailing_an_invoice_needs_a_real_address_and_your_own_order(): void
    {
        $buyer = $this->member('Buyer Bela', 'bela@example.com');
        $order = $this->paid($buyer);
        $other = $this->member('Other Omar', 'omar@example.com', 'active');

        $this->actingAs($buyer->fresh(), 'member')->post(route('member.orders.invoice.email', $order), ['email' => 'nope'])->assertSessionHasErrors('email');
        $this->actingAs($other, 'member')->post(route('member.orders.invoice.email', $order), ['email' => 'x@y.com'])->assertForbidden();
        $this->assertCount(0, $this->mails('x@y.com'));
    }

    public function test_the_invoice_page_offers_an_email_button_but_the_shared_copy_does_not(): void
    {
        $buyer = $this->member('Buyer Bela', 'bela@example.com');
        $order = $this->paid($buyer);

        $this->actingAs($buyer->fresh(), 'member')->get(route('member.orders.invoice', $order))->assertOk()->assertSee('Email invoice');
        $this->get(URL::temporarySignedRoute('invoice.shared', now()->addDay(), ['order' => $order->id]))->assertOk()->assertDontSee('Email invoice');
    }

    public function test_emailing_invoices_is_rate_limited(): void
    {
        $buyer = $this->member('Buyer Bela', 'bela@example.com');
        $order = $this->paid($buyer);

        $codes = collect(range(1, 12))->map(fn ($i) => $this->actingAs($buyer->fresh(), 'member')->post(route('member.orders.invoice.email', $order), ['email' => "t{$i}@x.com"])->getStatusCode());

        $this->assertContains(429, $codes->all(), 'Too many sends in a short time are refused');
    }

    // ------------------------------------------------------------------------------------------ the downline invite panel

    public function test_the_downline_page_lets_the_sponsor_choose_a_plan_for_each_leg_link_and_share_it(): void
    {
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        Plan::create(['name' => 'Gujarat Plan', 'slug' => 'g', 'price' => 700, 'bv' => 280, 'is_active' => true, 'region_id' => $other->id]);

        $this->actingAs($this->sponsor, 'member')->get(route('member.downline.index'))->assertOk()
            ->assertSee('Plan for the person you invite')->assertSee('Essential')->assertDontSee('Gujarat Plan')
            ->assertSee('plan='.$this->plan->id, false)
            ->assertSee('https://wa.me/', false)
            ->assertSee('Email this invite');
    }

    public function test_a_sponsor_can_email_an_invite_with_the_plan_and_leg_already_chosen(): void
    {
        $this->actingAs($this->sponsor, 'member')->post(route('member.invite.send'), ['email' => 'friend@example.com', 'name' => 'Fiona', 'side' => 'right', 'plan_id' => $this->plan->id])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $mail = $this->mails('friend@example.com')->first();
        $this->assertNotNull($mail);
        $this->assertStringContainsString('Sponsor Sam', $mail->mailSubject);
        $this->assertStringContainsString('sponsor='.$this->sponsor->member_code, $mail->ctaUrl);
        $this->assertStringContainsString('side=right', $mail->ctaUrl);
        $this->assertStringContainsString('plan='.$this->plan->id, $mail->ctaUrl);
        $this->assertStringContainsString('Essential', $mail->render());

        // The link really opens the register form with everything locked in (for the new person, who is not signed in).
        $this->app['auth']->guard('member')->logout();
        $this->get($mail->ctaUrl)->assertOk()->assertSee('Chosen by your sponsor')->assertSee('Invited by')->assertSee('Sponsor Sam');
    }

    public function test_an_invite_needs_a_valid_address_a_plan_from_the_same_region_and_a_real_leg(): void
    {
        $other = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $foreign = Plan::create(['name' => 'Gujarat Plan', 'slug' => 'g', 'price' => 700, 'bv' => 280, 'is_active' => true, 'region_id' => $other->id]);
        $off = Plan::create(['name' => 'Off', 'slug' => 'o', 'price' => 700, 'bv' => 280, 'is_active' => false, 'region_id' => $this->region->id]);
        $as = fn (array $d) => $this->actingAs($this->sponsor, 'member')->post(route('member.invite.send'), $d + ['email' => 'f@example.com']);

        $as(['email' => 'nope'])->assertSessionHasErrors('email');
        $as(['plan_id' => $foreign->id])->assertSessionHasErrors('plan_id');
        $as(['plan_id' => $off->id])->assertSessionHasErrors('plan_id');
        $as(['side' => 'middle'])->assertSessionHasErrors('side');
        $this->assertCount(0, $this->mails('f@example.com'));
    }

    public function test_an_invite_without_a_chosen_plan_lets_the_new_person_choose(): void
    {
        $this->actingAs($this->sponsor, 'member')->post(route('member.invite.send'), ['email' => 'friend@example.com'])->assertSessionHasNoErrors();

        $mail = $this->mails('friend@example.com')->first();
        $this->assertStringNotContainsString('plan=', $mail->ctaUrl);
        $this->assertStringNotContainsString('side=', $mail->ctaUrl);
    }

    public function test_invites_are_rate_limited_and_only_for_people_who_have_bought(): void
    {
        $codes = collect(range(1, 14))->map(fn ($i) => $this->actingAs($this->sponsor, 'member')->post(route('member.invite.send'), ['email' => "i{$i}@example.com"])->getStatusCode());
        $this->assertContains(429, $codes->all());

        $unpaid = $this->member('Unpaid Uma', 'uma@example.com');
        $this->actingAs($unpaid, 'member')->post(route('member.invite.send'), ['email' => 'z@example.com'])->assertRedirect(route('member.plans.index'));
    }

    // ------------------------------------------------------------------------------------------ announcements

    private function announce(array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->post(route('admin.announcements.store'), $extra + ['title' => 'Diwali offer', 'body' => 'New festive plan is here.', 'audience' => 'all']);
    }

    public function test_an_announcement_is_saved_shown_to_members_and_emailed_when_asked(): void
    {
        $active = $this->member('Active Amit', 'amit@example.com', 'active');
        $unpaid = $this->member('Unpaid Uma', 'uma@example.com', 'inactive');
        $noMail = $this->member('NoMail Nia', null, 'active');

        $this->announce(['send_email' => 1])->assertRedirect(route('admin.announcements.index'));

        $a = Announcement::firstOrFail();
        $this->assertSame('Diwali offer', $a->title);
        foreach (['amit@example.com', 'uma@example.com', 'sam@example.com'] as $email) {
            $this->assertNotNull($this->mails($email)->first(fn ($x) => str_contains($x->mailSubject, 'Diwali offer')), "{$email} is emailed");
        }
        $this->assertSame(3, $a->fresh()->emailed_count);
        $this->actingAs($active, 'member')->get(route('member.dashboard'))->assertOk()->assertSee('Diwali offer')->assertSee('New festive plan is here.');
        $this->assertNotNull($noMail);
    }

    public function test_an_announcement_without_the_email_box_is_not_emailed(): void
    {
        $this->announce();

        Mail::assertNothingSent();
        $this->assertSame(0, Announcement::first()->emailed_count);
    }

    public function test_an_announcement_can_go_to_active_members_or_one_region_only(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $this->member('Unpaid Uma', 'uma@example.com', 'inactive');
        $this->member('Gujarat Gita', 'gita@example.com', 'active', $gj);

        $this->announce(['audience' => 'active', 'send_email' => 1]);
        $this->assertCount(0, $this->mails('uma@example.com'), 'Unpaid people are not in an active-members audience');
        $this->assertNotNull($this->mails('gita@example.com')->first());

        Mail::fake();
        $this->announce(['title' => 'Gujarat only', 'region_id' => $gj->id, 'send_email' => 1]);
        $this->assertNotNull($this->mails('gita@example.com')->first());
        $this->assertCount(0, $this->mails('sam@example.com'), 'Main region members do not get a Gujarat announcement');
    }

    public function test_members_only_see_announcements_for_their_own_region(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        Announcement::create(['title' => 'For everyone', 'body' => 'a', 'audience' => 'all']);
        Announcement::create(['title' => 'Gujarat secret', 'body' => 'b', 'audience' => 'all', 'region_id' => $gj->id]);

        $this->actingAs($this->sponsor, 'member')->get(route('member.dashboard'))->assertOk()->assertSee('For everyone')->assertDontSee('Gujarat secret');
    }

    public function test_announcements_validate_and_escape_what_staff_type(): void
    {
        $this->announce(['title' => ''])->assertSessionHasErrors('title');
        $this->announce(['body' => ''])->assertSessionHasErrors('body');
        $this->announce(['audience' => 'everyone-ever'])->assertSessionHasErrors('audience');
        $this->announce(['title' => str_repeat('x', 200)])->assertSessionHasErrors('title');

        $this->announce(['title' => '<script>alert(1)</script>', 'body' => '<p>Hello members</p><img src=x onerror=alert(2)>', 'send_email' => 1]);
        $this->actingAs($this->sponsor, 'member')->get(route('member.dashboard'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->mails('sam@example.com')->first()->render());
    }

    public function test_only_staff_with_the_announcements_permission_can_post_and_region_staff_stay_in_their_region(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);

        $this->actingAs($this->sub(['members']), 'web')->get(route('admin.announcements.index'))->assertForbidden();
        $this->announce([], $this->sub(['members']))->assertForbidden();
        $this->announce([], $this->sub(['announcements']))->assertRedirect();

        // A region-limited sub-admin cannot address another region (or everyone): theirs only.
        $limited = $this->sub(['announcements'], [$gj->id]);
        $this->announce(['title' => 'Mine', 'region_id' => $this->region->id], $limited)->assertSessionHasErrors('region_id');
        $this->announce(['title' => 'Everyone'], $limited)->assertRedirect();
        $this->assertSame($gj->id, Announcement::where('title', 'Everyone')->value('region_id'), 'Forced into their own region');
    }

    public function test_before_the_database_update_the_announcements_page_explains_instead_of_crashing(): void
    {
        \Illuminate\Support\Facades\Schema::drop('announcements');

        $this->actingAs($this->admin, 'web')->get(route('admin.announcements.index'))->assertOk()
            ->assertSee('database update')->assertSee('php artisan migrate');

        $this->announce(['send_email' => 1])->assertRedirect(route('admin.announcements.index'))->assertSessionHas('error');
        Mail::assertNothingSent();

        // Members are not affected either.
        $this->actingAs($this->sponsor, 'member')->get(route('member.dashboard'))->assertOk();
    }

    // ------------------------------------------------------------------------------------------ new plan news

    private function createPlan(array $extra = [])
    {
        return $this->actingAs($this->admin, 'web')->post(route('admin.plans.store'), $extra + [
            'region_id' => $this->region->id, 'name' => 'Festive Pack', 'description' => 'Limited time', 'price' => 3000, 'bv' => 1200, 'is_active' => 1,
            'qty' => [$this->product->id => 2],
        ]);
    }

    public function test_a_new_plan_can_be_announced_by_email_to_that_regions_members_only(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $this->member('Active Amit', 'amit@example.com', 'active');
        $this->member('Gujarat Gita', 'gita@example.com', 'active', $gj);

        $this->createPlan(['notify_members' => 1])->assertRedirect(route('admin.plans.index'));

        $this->assertNotNull($this->mails('amit@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Festive Pack')));
        $this->assertNotNull($this->mails('sam@example.com')->first(fn ($x) => str_contains($x->mailSubject, 'Festive Pack')));
        $this->assertCount(0, $this->mails('gita@example.com'));
        $mail = $this->mails('amit@example.com')->first();
        $this->assertStringContainsString('₹3,360', $mail->render());      // 3,000 + 12% GST
        $this->assertSame(route('member.plans.index'), $mail->ctaUrl);
    }

    public function test_a_new_plan_sends_nothing_unless_asked_or_when_it_is_switched_off(): void
    {
        $this->member('Active Amit', 'amit@example.com', 'active');

        $this->createPlan();
        Mail::assertNothingSent();

        $this->createPlan(['name' => 'Hidden Pack', 'notify_members' => 1, 'is_active' => 0]);
        Mail::assertNothingSent();
    }

    public function test_the_new_plan_form_has_the_email_checkbox(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create'))->assertOk()->assertSee('Email members about this plan');
    }
}
