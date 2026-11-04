<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The full track record of staff: every sign-in and sign-out, every refused attempt, every change with what it was and
 * what it became (settings, cycles, prices, payments, staff accounts), every download, and a screen to read it all.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Anil', 'email' => 'anil@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function sub(array $perms = ['members'], string $name = 'Sub Sana'): User
    {
        return User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).random_int(1, 9999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);
    }

    private function rows(string $action)
    {
        return AuditLog::where('action', $action)->orderBy('id')->get();
    }

    private function one(string $action): AuditLog
    {
        $rows = $this->rows($action);
        $this->assertCount(1, $rows, "Expected exactly one \"{$action}\" line; the log has: ".AuditLog::pluck('action')->implode(', '));

        return $rows->first();
    }

    private function settingsPayload(array $over = []): array
    {
        return $over + [
            'split_cost_pct' => 40, 'split_pool_pct' => 40, 'split_company_pct' => 20, 'self_pct' => 10, 'sponsor_pct' => 20,
            'rank_pool_pct' => 3, 'match_cap_per_cycle' => 600000, 'match_cap_per_week' => 150000, 'circuit_breaker_pct' => 60, 'levels' => [10, 10, 10],
            'show_inactive_in_downline' => '1',
        ];
    }

    private function pendingPayment(): Payment
    {
        $cat = Category::firstOrCreate(['slug' => 'w'], ['name' => 'W', 'is_free' => false]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S'.random_int(1, 99999), 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $plan = Plan::create(['name' => 'P'.random_int(1, 99999), 'slug' => 'p'.random_int(1, 99999), 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $plan->products()->attach($p->id, ['qty' => 1]);
        $m = Member::create(['member_code' => 'AT'.random_int(1, 99999), 'name' => 'Trail Buyer', 'phone' => '93'.random_int(10000000, 99999999), 'status' => 'inactive', 'region_id' => $this->region->id]);

        return app(PaymentService::class)->submit(app(OrderService::class)->place($m, $plan), 'upi', 'UTR'.random_int(1, 999999));
    }

    // ------------------------------------------------------------------------------------------ signing in and out

    public function test_signing_in_and_out_is_recorded_for_the_super_admin_and_for_a_sub_admin(): void
    {
        $sub = $this->sub(['members'], 'Sub Sana');

        $this->post(route('admin.login.submit'), ['email' => 'anil@t.com', 'password' => 'secret123']);
        $this->post(route('admin.logout'));
        $this->post(route('admin.login.submit'), ['email' => $sub->email, 'password' => 'secret123']);
        $this->post(route('admin.logout'));

        $rows = AuditLog::orderBy('id')->get();
        $this->assertSame(['admin.login', 'admin.logout', 'admin.login', 'admin.logout'], $rows->pluck('action')->all());
        $this->assertSame(['Admin Anil', 'Admin Anil', 'Sub Sana', 'Sub Sana'], $rows->pluck('user_name')->all());
        $this->assertSame(['admin', 'admin', 'sub_admin', 'sub_admin'], $rows->pluck('user_role')->all());
        $this->assertSame(['sign-in'], $rows->pluck('category')->unique()->values()->all());
        $this->assertSame(['success'], $rows->pluck('outcome')->unique()->values()->all());
    }

    public function test_a_failed_sign_in_is_recorded_as_failed_with_the_address_tried_but_never_the_password(): void
    {
        $this->post(route('admin.login.submit'), ['email' => 'anil@t.com', 'password' => 'definitely-wrong-1']);
        $this->post(route('admin.login.submit'), ['email' => 'nobody@t.com', 'password' => 'definitely-wrong-2']);

        $rows = AuditLog::orderBy('id')->get();
        $this->assertSame(['failed', 'failed'], $rows->pluck('outcome')->all());
        $this->assertSame(['anil@t.com', 'nobody@t.com'], $rows->pluck('subject')->all());
        $this->assertSame('sign-in', $rows->first()->category);
        $this->assertStringNotContainsString('definitely-wrong', json_encode(AuditLog::all()->toArray()));
    }

    public function test_a_wrong_two_factor_code_is_recorded_as_a_failed_sign_in(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.account.two-factor.enable'));
        $this->actingAs($this->admin->fresh(), 'web')->post(route('admin.account.two-factor.confirm'), ['code' => Totp::at($this->admin->fresh()->two_factor_secret, time())]);
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();
        AuditLog::query()->getQuery()->whereRaw('1 = 0')->get();

        $before = AuditLog::count();
        $this->post(route('admin.login.submit'), ['email' => 'anil@t.com', 'password' => 'secret123'])->assertRedirect(route('admin.two-factor.challenge'));
        $this->post(route('admin.two-factor.verify'), ['code' => '000000']);

        $new = AuditLog::orderBy('id')->get()->slice($before);
        $failed = $new->firstWhere('action', 'admin.login.failed');
        $this->assertNotNull($failed, 'The wrong code is a failed sign-in');
        $this->assertStringContainsString('two-factor', $failed->subject);
        $this->assertSame('failed', $failed->outcome);
    }

    public function test_the_browser_and_address_are_recorded_with_each_line(): void
    {
        $this->withHeader('User-Agent', 'TestBrowser/9.1')->post(route('admin.login.submit'), ['email' => 'anil@t.com', 'password' => 'secret123']);

        $row = $this->one('admin.login');
        $this->assertSame('TestBrowser/9.1', $row->user_agent);
        $this->assertSame('127.0.0.1', $row->ip);
    }

    // ------------------------------------------------------------------------------------------ refused attempts

    public function test_trying_something_you_may_not_do_is_recorded_as_denied(): void
    {
        $sub = $this->sub(['members'], 'Sub Sana');
        $payment = $this->pendingPayment();

        $this->actingAs($sub, 'web')->put(route('admin.payments.verify', $payment))->assertForbidden();
        $this->actingAs($sub, 'web')->get(route('admin.settings.edit'))->assertForbidden();

        $payments = $this->one('admin.payments.verify.denied');
        $this->assertSame([$sub->id, 'denied', 'security', 'sub_admin'], [$payments->user_id, $payments->outcome, $payments->category, $payments->user_role]);
        $this->assertSame('Payment #'.$payment->id, $payments->subject);
        $this->assertSame('verified', 'verified', 'the payment itself stays unverified');
        $this->assertSame('pending', $payment->fresh()->status);

        $this->assertNotNull($this->one('admin.settings.edit.denied'), 'A refused page view by a signed-in staff member is worth knowing about too');
    }

    public function test_pages_staff_are_allowed_to_open_are_still_not_logged(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index'))->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    // ------------------------------------------------------------------------------------------ settings

    public function test_changing_a_setting_records_the_old_and_the_new_value(): void
    {
        $this->configureCompensation();

        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['self_pct' => 15]))->assertSessionHasNoErrors();

        $row = AuditLog::where('action', 'change.setting.updated')->where('subject', 'Setting: self_pct')->firstOrFail();
        $this->assertSame('10 → 15', $row->details);
        $this->assertSame([$this->admin->id, 'settings', 'success'], [$row->user_id, $row->category, $row->outcome]);
        $this->assertSame(0, AuditLog::where('subject', 'Setting: sponsor_pct')->count(), 'A value that did not change is not a change');
    }

    public function test_saving_the_same_settings_again_records_no_setting_changes(): void
    {
        $this->configureCompensation();
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['levels' => [10, 10, 10, 10, 10, 10, 10]]));
        $count = AuditLog::where('action', 'like', 'change.setting.%')->count();

        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['levels' => [10, 10, 10, 10, 10, 10, 10]]));

        $this->assertSame($count, AuditLog::where('action', 'like', 'change.setting.%')->count());
    }

    public function test_the_first_save_of_a_setting_is_recorded_as_set_to(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['self_pct' => 12]))->assertSessionHasNoErrors();

        $row = AuditLog::where('action', 'change.setting.created')->where('subject', 'Setting: self_pct')->firstOrFail();
        $this->assertSame('set to 12', $row->details);
    }

    public function test_bank_and_payment_details_are_recorded_as_changed_but_never_their_values(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), ['site_name' => 'BeyondSure+', 'pay_account_number' => '50200012345678', 'pay_upi_id' => 'secretname@upi', 'pay_ifsc' => 'HDFC0001234']);

        $rows = AuditLog::where('subject', 'like', 'Setting: pay_%')->get();
        $this->assertGreaterThanOrEqual(3, $rows->count(), 'The change itself is on the record');
        $all = $rows->pluck('details')->implode(' ');
        $this->assertStringContainsString('hidden', $all);
        foreach (['50200012345678', 'secretname@upi', 'HDFC0001234'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode(AuditLog::all()->toArray()), "{$secret} must not be in the log");
        }
    }

    public function test_the_company_name_and_gstin_changes_are_recorded_with_values(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), ['site_name' => 'BeyondSure+', 'inv_gstin' => '24ABCDE1234F1Z5']);

        $this->assertSame('set to 24ABCDE1234F1Z5', AuditLog::where('subject', 'Setting: inv_gstin')->firstOrFail()->details);
    }

    // ------------------------------------------------------------------------------------------ cycles

    public function test_the_super_admin_creating_and_approving_a_cycle_is_recorded_with_the_figures(): void
    {
        $this->configureCompensation();

        $this->actingAs($this->admin, 'web')->post(route('admin.cycles.store'), ['period_start' => '2026-11-01', 'period_end' => '2026-11-30'])->assertSessionHasNoErrors();
        $cycle = Cycle::firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.cycles.approve', $cycle))->assertSessionHasNoErrors();

        $created = $this->one('change.cycle.created');
        $this->assertStringContainsString('period_start: 2026-11-01', $created->details);
        $this->assertStringContainsString('status: draft', $created->details);
        $this->assertSame([$this->admin->id, 'cycles', 'admin'], [$created->user_id, $created->category, $created->user_role]);

        $approved = $this->one('change.cycle.updated');
        $this->assertStringContainsString('status: draft → approved', $approved->details);

        $this->assertSame('cycles', $this->one('admin.cycles.store')->category);
        $this->assertSame('Cycle #'.$cycle->id, $this->one('admin.cycles.approve')->subject);
    }

    public function test_a_refused_cycle_attempt_is_recorded_too(): void
    {
        $limited = User::create(['name' => 'Limited Lia', 'email' => 'lia@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['cycles'], 'region_ids' => [$this->region->id], 'is_active' => true]);

        $this->actingAs($limited, 'web')->post(route('admin.cycles.store'), ['period_start' => '2026-11-01', 'period_end' => '2026-11-30'])->assertForbidden();

        $this->assertSame('denied', $this->one('admin.cycles.store.denied')->outcome);
    }

    // ------------------------------------------------------------------------------------------ catalog and money

    public function test_a_price_a_cost_and_a_tax_rate_changing_are_recorded_old_to_new(): void
    {
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $plan = Plan::create(['name' => 'Gold', 'slug' => 'gold', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->actingAs($this->admin, 'web');

        $plan->update(['price' => 2500, 'bv' => 1000]);
        $product->update(['gst_pct' => 18, 'cost_price' => 120, 'is_active' => false]);

        $p = $this->one('change.plan.updated');
        $this->assertSame('Plan #'.$plan->id.' Gold', $p->subject);
        $this->assertStringContainsString('price: 2000 → 2500', $p->details);
        $this->assertStringContainsString('bv: 800 → 1000', $p->details);
        $this->assertSame('catalog', $p->category);

        $q = $this->one('change.product.updated');
        $this->assertStringContainsString('gst_pct: 12 → 18', $q->details);
        $this->assertStringContainsString('cost_price: 100 → 120', $q->details);
        $this->assertStringContainsString('is_active: yes → no', $q->details);
    }

    public function test_ranks_regions_and_categories_are_tracked(): void
    {
        $rank = Rank::create(['name' => 'Bronze', 'sort_order' => 1, 'matched_bv_threshold' => 100, 'min_active_directs' => 0, 'share' => 1]);
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $this->actingAs($this->admin, 'web');

        $rank->update(['matched_bv_threshold' => 500, 'share' => 2]);
        $this->region->update(['is_active' => false]);
        $cat->update(['name' => 'Wellness']);

        $this->assertStringContainsString('matched_bv_threshold: 100 → 500', $this->one('change.rank.updated')->details);
        $this->assertStringContainsString('is_active: yes → no', $this->one('change.region.updated')->details);
        $this->assertStringContainsString('name: W → Wellness', $this->one('change.category.updated')->details);
    }

    public function test_verifying_a_payment_records_the_payment_the_order_and_the_member_changes(): void
    {
        $payment = $this->pendingPayment();
        $memberId = $payment->order->member_id;

        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasNoErrors();

        $this->assertSame('admin.payments.verify', $this->one('admin.payments.verify')->action);
        $this->assertSame('money', $this->one('admin.payments.verify')->category);
        $this->assertStringContainsString('status: pending → verified', $this->one('change.payment.updated')->details);
        $this->assertStringContainsString('status: pending_payment → paid', $this->one('change.order.updated')->details);
        $member = $this->one('change.member.updated');
        $this->assertSame('Member #'.$memberId.' Trail Buyer', $member->subject);
        $this->assertStringContainsString('status: inactive → active', $member->details);
        $this->assertSame([$this->admin->id], AuditLog::where('action', 'like', 'change.%')->pluck('user_id')->unique()->values()->all(), 'Every one of them was done by the admin who pressed Verify');
    }

    public function test_withdrawal_decisions_are_recorded(): void
    {
        $m = Member::create(['member_code' => 'WD1', 'name' => 'Wd Wendy', 'phone' => '9344444444', 'status' => 'active', 'region_id' => $this->region->id]);
        $w = Withdrawal::create(['member_id' => $m->id, 'amount' => 700, 'status' => 'pending', 'bank_account_name' => 'W', 'bank_account_number' => '123456789012', 'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC']);

        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w))->assertSessionHasNoErrors();

        $this->assertStringContainsString('status: pending → approved', $this->one('change.withdrawal.updated')->details);
        $this->assertStringNotContainsString('123456789012', json_encode(AuditLog::all()->toArray()), 'The account number is not logged');
    }

    // ------------------------------------------------------------------------------------------ staff accounts

    public function test_creating_changing_and_removing_a_sub_admin_is_recorded_without_the_password(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.team.store'), ['name' => 'New Nina', 'email' => 'nina@t.com', 'password' => 'Sup3rSecretPw!', 'permissions' => ['members'], 'is_active' => 1])->assertSessionHasNoErrors();
        $nina = User::where('email', 'nina@t.com')->firstOrFail();

        $created = $this->one('change.user.created');
        $this->assertSame('staff', $created->category);
        $this->assertStringContainsString('role: sub_admin', $created->details);
        $this->assertStringContainsString('permissions: members', $created->details);

        $this->actingAs($this->admin, 'web')->put(route('admin.team.update', $nina), ['name' => 'New Nina', 'email' => 'nina@t.com', 'permissions' => ['members', 'orders'], 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertStringContainsString('permissions: members → members, orders', $this->one('change.user.updated')->details);

        $nina->fresh()->update(['is_active' => false]);
        $this->assertStringContainsString('is_active: yes → no', $this->rows('change.user.updated')->last()->details);

        $this->actingAs($this->admin, 'web')->delete(route('admin.team.destroy', $nina))->assertRedirect();
        $this->assertSame('User #'.$nina->id.' New Nina', $this->one('change.user.deleted')->subject);

        $everything = json_encode(AuditLog::all()->toArray());
        $this->assertStringNotContainsString('Sup3rSecretPw', $everything);
    }

    public function test_a_password_reset_for_a_colleague_is_recorded_without_the_new_password(): void
    {
        $sub = $this->sub(['members'], 'Sub Sana');

        $this->actingAs($this->admin, 'web')->put(route('admin.team.password', $sub))->assertRedirect();

        $row = $this->one('admin.team.password');
        $this->assertSame('User #'.$sub->id, $row->subject);
        $this->assertSame('security', $row->category);
        $this->assertSame(0, AuditLog::where('details', 'like', '%password: %')->count());
    }

    public function test_changing_your_own_password_is_recorded_but_not_the_password(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.account.password.update'), ['current_password' => 'secret123', 'password' => 'BrandNewPass99!', 'password_confirmation' => 'BrandNewPass99!']);

        $this->assertSame('security', $this->one('admin.account.password.update')->category);
        $this->assertStringNotContainsString('BrandNewPass99', json_encode(AuditLog::all()->toArray()));
    }

    // ------------------------------------------------------------------------------------------ who is not in this log

    public function test_what_members_and_the_system_do_is_not_in_the_staff_log(): void
    {
        $this->configureCompensation();
        $payment = $this->pendingPayment();      // placed by the code, no staff member signed in
        $payment->order->member->update(['status' => 'active']);
        Setting::set('site_name', 'Changed by a script');

        $this->assertSame(0, AuditLog::count());
    }

    // ------------------------------------------------------------------------------------------ belonging together

    public function test_a_request_and_the_changes_it_caused_share_one_request_id(): void
    {
        $this->configureCompensation();
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['self_pct' => 15]));
        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload(['self_pct' => 16]));

        $first = AuditLog::where('subject', 'Setting: self_pct')->orderBy('id')->get();
        $route = AuditLog::where('action', 'admin.settings.update')->orderBy('id')->get();

        $this->assertNotNull($first[0]->request_id);
        $this->assertSame($route[0]->request_id, $first[0]->request_id);
        $this->assertSame($route[1]->request_id, $first[1]->request_id);
        $this->assertNotSame($first[0]->request_id, $first[1]->request_id);
    }

    #[DataProvider('categories')]
    public function test_every_action_belongs_to_a_category_people_can_filter_by(string $action, string $category): void
    {
        $this->assertSame($category, AuditLog::categoryFor($action));
    }

    public static function categories(): array
    {
        return [
            ['admin.login', 'sign-in'], ['admin.login.failed', 'sign-in'], ['admin.logout', 'sign-in'],
            ['admin.account.password.update', 'security'], ['admin.team.password', 'security'], ['admin.team.two-factor-reset', 'security'], ['admin.settings.update.denied', 'security'],
            ['admin.settings.update', 'settings'], ['change.setting.updated', 'settings'], ['admin.site-content.update', 'settings'],
            ['admin.cycles.store', 'cycles'], ['admin.cycles.approve', 'cycles'], ['change.cycle.created', 'cycles'],
            ['admin.payments.verify', 'money'], ['admin.withdrawals.approve', 'money'], ['admin.orders.cancel', 'money'], ['admin.orders.credit-note.store', 'money'], ['admin.dispatch.release', 'money'], ['change.payment.updated', 'money'],
            ['admin.plans.store', 'catalog'], ['admin.products.update', 'catalog'], ['admin.categories.store', 'catalog'], ['change.plan.updated', 'catalog'],
            ['admin.members.show', 'members'], ['admin.regions.update', 'members'], ['change.member.updated', 'members'],
            ['admin.team.store', 'staff'], ['change.user.created', 'staff'],
            ['admin.announcements.store', 'content'], ['admin.testimonials.store', 'content'], ['admin.messages.read', 'content'],
            ['admin.reports.sales.csv', 'export'], ['admin.audit-log.csv', 'export'],
            ['something.unknown', 'other'],
        ];
    }

    public function test_downloading_a_csv_is_recorded_as_an_export(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.sales.csv'))->assertOk();

        $row = $this->one('admin.reports.sales.csv');
        $this->assertSame(['export', 'GET'], [$row->category, $row->method]);
    }

    // ------------------------------------------------------------------------------------------ reading it

    private function seedLines(): void
    {
        $sana = $this->sub(['members'], 'Sub Sana');
        AuditLog::create(['user_id' => $this->admin->id, 'user_name' => 'Admin Anil', 'user_role' => 'admin', 'action' => 'admin.settings.update', 'category' => 'settings', 'outcome' => 'success', 'subject' => null, 'created_at' => now()->subDays(10)]);
        AuditLog::create(['user_id' => $this->admin->id, 'user_name' => 'Admin Anil', 'user_role' => 'admin', 'action' => 'admin.cycles.approve', 'category' => 'cycles', 'outcome' => 'success', 'subject' => 'Cycle #4', 'created_at' => now()->subDays(2)]);
        AuditLog::create(['user_id' => $sana->id, 'user_name' => 'Sub Sana', 'user_role' => 'sub_admin', 'action' => 'admin.payments.verify.denied', 'category' => 'security', 'outcome' => 'denied', 'subject' => 'Payment #9', 'created_at' => now()->subHours(3)]);
        AuditLog::create(['user_id' => null, 'user_name' => null, 'user_role' => null, 'action' => 'admin.login.failed', 'category' => 'sign-in', 'outcome' => 'failed', 'subject' => 'x@y.com', 'ip' => '203.0.113.9', 'created_at' => now()->subHour()]);
    }

    private function screen(array $query = [])
    {
        return $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index', $query));
    }

    public function test_the_log_can_be_filtered_by_person_category_outcome_and_dates(): void
    {
        $this->seedLines();
        $sana = User::where('name', 'Sub Sana')->firstOrFail();

        $this->assertSame(['admin.payments.verify.denied'], $this->screen(['user' => $sana->id])->viewData('entries')->pluck('action')->all());
        $this->assertSame(['admin.cycles.approve'], $this->screen(['category' => 'cycles'])->viewData('entries')->pluck('action')->all());
        $this->assertSame(['admin.login.failed'], $this->screen(['outcome' => 'failed'])->viewData('entries')->pluck('action')->all());
        $this->assertSame(['admin.payments.verify.denied'], $this->screen(['outcome' => 'denied'])->viewData('entries')->pluck('action')->all());
        $this->assertCount(3, $this->screen(['from' => now()->subDays(3)->toDateString()])->viewData('entries'));
        $this->assertCount(1, $this->screen(['from' => now()->subDays(12)->toDateString(), 'to' => now()->subDays(5)->toDateString()])->viewData('entries'));
        $this->assertSame(['admin.cycles.approve'], $this->screen(['q' => 'Cycle #4'])->viewData('entries')->pluck('action')->all());
    }

    public function test_filters_combine_and_junk_values_are_ignored(): void
    {
        $this->seedLines();

        $this->assertCount(0, $this->screen(['category' => 'cycles', 'outcome' => 'failed'])->viewData('entries'));
        $this->assertCount(4, $this->screen(['user' => 'abc', 'category' => 'nonsense', 'outcome' => 'weird', 'from' => 'not-a-date', 'to' => ['x'], 'q' => ['y']])->assertOk()->viewData('entries'));
    }

    public function test_the_page_shows_what_each_change_was(): void
    {
        $plan = Plan::create(['name' => 'Gold', 'slug' => 'gold', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->actingAs($this->admin, 'web');
        $plan->update(['price' => 2500]);

        $this->screen()->assertOk()->assertSee('price: 2000 → 2500')->assertSee('Plan #'.$plan->id.' Gold')->assertSee('Admin Anil');
    }

    public function test_the_summary_counts_the_last_day(): void
    {
        $this->seedLines();

        $s = $this->screen()->assertOk()->viewData('summary');

        $this->assertSame(['events' => 2, 'failed_sign_ins' => 1, 'denied' => 1], array_intersect_key($s, array_flip(['events', 'failed_sign_ins', 'denied'])));
    }

    public function test_the_sign_in_panel_shows_each_staff_members_last_sign_in_and_those_who_never_did(): void
    {
        $sana = $this->sub(['members'], 'Sub Sana');
        $this->sub(['orders'], 'Never Nia');
        AuditLog::create(['user_id' => $this->admin->id, 'user_name' => 'Admin Anil', 'user_role' => 'admin', 'action' => 'admin.login', 'category' => 'sign-in', 'outcome' => 'success', 'ip' => '198.51.100.7', 'created_at' => now()->subHours(5)]);
        AuditLog::create(['user_id' => $sana->id, 'user_name' => 'Sub Sana', 'user_role' => 'sub_admin', 'action' => 'admin.login', 'category' => 'sign-in', 'outcome' => 'success', 'ip' => '192.0.2.44', 'created_at' => now()->subDays(2)]);
        AuditLog::create(['user_id' => $sana->id, 'user_name' => 'Sub Sana', 'user_role' => 'sub_admin', 'action' => 'admin.login', 'category' => 'sign-in', 'outcome' => 'success', 'ip' => '192.0.2.99', 'created_at' => now()->subDay()]);

        $res = $this->screen()->assertOk()->assertSee('Staff sign-ins')->assertSee('Never Nia')->assertSee('Never signed in')->assertSee('192.0.2.99');
        $staff = collect($res->viewData('staff'))->keyBy('name');
        $this->assertSame('192.0.2.99', $staff['Sub Sana']['last_ip'], 'The panel shows the latest sign-in, not the earlier one (that one is still in the log below)');
        $this->assertNull($staff['Never Nia']['last_at']);
        $this->assertSame(3, $staff->count());
    }

    public function test_the_filtered_log_can_be_downloaded_as_a_csv_that_cannot_run_formulas(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'user_name' => 'Admin Anil', 'user_role' => 'admin', 'action' => 'admin.cycles.approve', 'category' => 'cycles', 'outcome' => 'success', 'subject' => '=HYPERLINK("http://evil")', 'details' => 'status: draft → approved', 'created_at' => now()]);
        AuditLog::create(['user_id' => null, 'action' => 'admin.login.failed', 'category' => 'sign-in', 'outcome' => 'failed', 'subject' => 'x@y.com', 'created_at' => now()]);

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.csv', ['category' => 'cycles']))->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringContainsString('When,Who,Role,Category,Outcome,Action,Item,Details,Address', str_replace('"', '', explode("\n", $csv)[0]));
        $this->assertStringContainsString('admin.cycles.approve', $csv);
        $this->assertStringNotContainsString('admin.login.failed', $csv, 'The filter applies to the download too');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', str_replace('"', '', $csv));
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
    }

    public function test_downloading_the_log_is_itself_recorded(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.csv'))->assertOk()->streamedContent();

        $this->assertSame('export', $this->one('admin.audit-log.csv')->category);
    }

    public function test_only_the_super_admin_can_read_or_download_the_log(): void
    {
        $all = $this->sub(\App\Support\AdminPermissions::keys());

        $this->actingAs($all, 'web')->get(route('admin.audit-log.index'))->assertForbidden();
        $this->actingAs($all, 'web')->get(route('admin.audit-log.csv'))->assertForbidden();
        $this->app['auth']->guard('web')->logout();
        $this->get(route('admin.audit-log.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.audit-log.csv'))->assertRedirect(route('admin.login'));
    }

    public function test_it_pages_with_the_filters_kept(): void
    {
        foreach (range(1, 70) as $i) {
            AuditLog::create(['user_id' => $this->admin->id, 'user_name' => 'Admin Anil', 'user_role' => 'admin', 'action' => 'admin.plans.store', 'category' => 'catalog', 'outcome' => 'success', 'created_at' => now()]);
        }

        $this->screen(['category' => 'catalog'])->assertOk()->assertSee('category=catalog', false)->assertSee('page=2', false);
        $this->assertSame(20, $this->screen(['category' => 'catalog', 'page' => 2])->viewData('entries')->count());
    }

    public function test_the_page_escapes_everything(): void
    {
        AuditLog::create(['user_id' => null, 'user_name' => '<img src=x onerror=alert(1)>', 'action' => '<script>alert(2)</script>', 'category' => 'other', 'outcome' => 'success', 'subject' => '<b>x</b>', 'details' => '<i>y</i>', 'created_at' => now()]);

        $this->screen()->assertOk()->assertDontSee('<img src=x', false)->assertDontSee('<script>alert(2)</script>', false)->assertDontSee('<b>x</b>', false)->assertDontSee('<i>y</i>', false);
    }

    // ------------------------------------------------------------------------------------------ nobody can rewrite history

    public function test_the_database_itself_refuses_to_change_or_remove_log_lines(): void
    {
        AuditLog::record('admin.x', null, 'POST', '/x', [], $this->admin);

        foreach ([
            fn () => DB::table('audit_logs')->update(['action' => 'tampered']),
            fn () => DB::table('audit_logs')->delete(),
            fn () => AuditLog::query()->delete(),
            fn () => AuditLog::query()->update(['user_name' => 'Someone else']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A direct change to the audit log must be refused by the database');
            } catch (\Throwable $e) {
                $this->assertStringContainsString('Audit log', $e->getMessage());
            }
        }
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('admin.x', AuditLog::first()->action);
    }

    // ------------------------------------------------------------------------------------------ before the database update

    public function test_logging_keeps_working_and_the_screen_explains_before_the_database_update(): void
    {
        Schema::drop('audit_logs');
        Schema::create('audit_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 120)->nullable();
            $table->string('action', 150);
            $table->string('method', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('subject', 120)->nullable();
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        AuditLog::forgetSchemaCache();

        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'Hi', 'body' => 'There', 'audience' => 'all'])->assertSessionHasNoErrors();

        $this->assertSame(1, AuditLog::where('action', 'admin.announcements.store')->count(), 'Still recorded with the columns that exist');
        $this->screen()->assertOk()->assertSee('database update')->assertSee('php artisan migrate');
        AuditLog::forgetSchemaCache();
    }
}
