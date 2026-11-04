<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Everything staff change is written down: who, what, when and from where. The log is read-only. */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::create(['name' => 'Admin Anil', 'email' => 'anil@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function sub(array $perms, string $name = 'Sub Sana'): User
    {
        return User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).random_int(1, 999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);
    }

    private function pendingPayment(): \App\Models\Payment
    {
        $region = Region::where('code', 'MAIN')->firstOrFail();
        $cat = Category::firstOrCreate(['slug' => 'w'], ['name' => 'W', 'is_free' => false]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S'.random_int(1, 99999), 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $plan = Plan::create(['name' => 'P'.random_int(1, 99999), 'slug' => 'p'.random_int(1, 99999), 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $region->id]);
        $plan->products()->attach($p->id, ['qty' => 1]);
        $m = Member::create(['member_code' => 'AU'.random_int(1, 99999), 'name' => 'Audit Buyer', 'phone' => '93'.random_int(10000000, 99999999), 'status' => 'inactive', 'region_id' => $region->id]);

        return app(PaymentService::class)->submit(app(OrderService::class)->place($m, $plan), 'upi', 'UTR'.random_int(1, 999999));
    }

    public function test_a_staff_action_is_recorded_with_who_what_and_where(): void
    {
        $payment = $this->pendingPayment();

        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasNoErrors();

        // (The changes it caused are logged too, just before this line, so look the line up by what it is.)
        $row = AuditLog::where('action', 'admin.payments.verify')->firstOrFail();
        $this->assertSame($this->admin->id, $row->user_id);
        $this->assertSame('Admin Anil', $row->user_name);
        $this->assertSame('admin.payments.verify', $row->action);
        $this->assertSame('PUT', $row->method);
        $this->assertSame('Payment #'.$payment->id, $row->subject);
        $this->assertSame('127.0.0.1', $row->ip);
        $this->assertNotNull($row->created_at);
    }

    public function test_only_changes_are_recorded_not_page_views(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index'))->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_refused_request_changes_nothing_and_is_recorded_as_denied_not_as_an_action(): void
    {
        // (This used to expect no line at all. Now a refused attempt IS written down, but as "denied", never as the action.)
        $payment = $this->pendingPayment();
        $this->actingAs($this->sub(['members']), 'web')->put(route('admin.payments.verify', $payment))->assertForbidden();

        $this->assertSame(['admin.payments.verify.denied'], AuditLog::pluck('action')->all());
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'admin.payments.verify')->count());
    }

    public function test_what_was_typed_is_never_stored_only_the_names_of_the_fields(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'TOPSECRETTITLE', 'body' => 'TOPSECRETBODY', 'audience' => 'all']);
        $this->actingAs($this->admin, 'web')->put(route('admin.account.password.update'), ['current_password' => 'secret123', 'password' => 'BrandNewPass99!', 'password_confirmation' => 'BrandNewPass99!']);

        $everything = AuditLog::all()->map(fn ($r) => json_encode($r->getAttributes()))->implode(' ');

        $this->assertStringContainsString('title', $everything);
        $this->assertStringContainsString('password', $everything);
        foreach (['TOPSECRETTITLE', 'TOPSECRETBODY', 'BrandNewPass99!', 'secret123'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything, "{$secret} must never be logged");
        }
        $this->assertStringNotContainsString('_token', $everything);
    }

    public function test_sign_ins_and_failed_attempts_are_recorded(): void
    {
        $this->post(route('admin.login.submit'), ['email' => 'anil@t.com', 'password' => 'wrong-pass']);
        $this->post(route('admin.login.submit'), ['email' => 'anil@t.com', 'password' => 'secret123']);

        $this->assertSame(['admin.login.failed', 'admin.login'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('anil@t.com', AuditLog::orderBy('id')->first()->subject);
        $this->assertStringNotContainsString('wrong-pass', json_encode(AuditLog::all()->toArray()));
        $this->assertSame($this->admin->id, AuditLog::orderBy('id')->get()[1]->user_id);
    }

    public function test_member_and_public_requests_are_not_part_of_the_staff_log(): void
    {
        $this->post(route('contact.store'), ['name' => 'V', 'email' => 'v@x.com', 'message' => 'hi']);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_problem_with_the_log_never_stops_the_action(): void
    {
        $payment = $this->pendingPayment();
        Schema::drop('audit_logs');

        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasNoErrors();

        $this->assertSame('verified', $payment->fresh()->status);
    }

    // ------------------------------------------------------------------------------------------ reading and protecting the log

    public function test_the_super_admin_reads_the_log_newest_first_with_filters(): void
    {
        AuditLog::record('admin.plans.store', 'Plan #1', 'POST', '/admin/plans', ['name'], $this->admin);
        AuditLog::record('admin.payments.verify', 'Payment #9', 'PUT', '/admin/payments/9/verify', [], $this->sub([], 'Sub Sana'));

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index'))->assertOk();
        $this->assertSame(['admin.payments.verify', 'admin.plans.store'], $res->viewData('entries')->pluck('action')->all());
        $res->assertSee('Payment #9')->assertSee('Sub Sana');

        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index', ['q' => 'plans']))->assertOk()->assertSee('Plan #1')->assertDontSee('Payment #9');
        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index', ['q' => 'Sana']))->assertOk()->assertSee('Payment #9')->assertDontSee('Plan #1');
        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index', ['q' => ['x']]))->assertOk();
    }

    public function test_it_pages_through_a_long_log(): void
    {
        foreach (range(1, 120) as $i) {
            AuditLog::record('admin.test.'.$i, null, 'POST', '/x', [], $this->admin);
        }

        $this->assertSame(50, $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index'))->viewData('entries')->count());
        $this->assertSame(20, $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index', ['page' => 3]))->viewData('entries')->count());
    }

    public function test_sub_admins_cannot_read_it_even_with_every_permission(): void
    {
        $all = $this->sub(\App\Support\AdminPermissions::keys());

        $this->actingAs($all, 'web')->get(route('admin.audit-log.index'))->assertForbidden();
    }

    public function test_the_log_cannot_be_edited_or_deleted(): void
    {
        $row = AuditLog::record('admin.x', null, 'POST', '/x', [], $this->admin);

        try {
            $row->update(['action' => 'changed']);
            $this->fail('Updating a log row must be refused');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot be changed', $e->getMessage());
        }
        try {
            $row->delete();
            $this->fail('Deleting a log row must be refused');
        } catch (\LogicException) {
            $this->assertSame(1, AuditLog::count());
        }
        $this->actingAs($this->admin, 'web')->delete('/admin/audit-log')->assertStatus(405);
        $this->assertSame('admin.x', $row->fresh()->action);
    }

    public function test_what_is_shown_is_escaped(): void
    {
        AuditLog::record('admin.x', '<script>alert(1)</script>', 'POST', '/x', ['<b>f</b>'], $this->sub([], '<img src=x onerror=alert(2)>'));

        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
    }

    public function test_the_menu_link_is_only_for_the_super_admin(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee(route('admin.audit-log.index'), false);
        $this->actingAs($this->sub(['members']), 'web')->get(route('admin.dashboard'))->assertDontSee(route('admin.audit-log.index'), false);
        $this->assertNotNull(Order::query());
    }
}
