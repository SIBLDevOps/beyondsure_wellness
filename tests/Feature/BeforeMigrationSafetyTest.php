<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Order;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Until the newest database update is run, the existing screens keep working and the new ones explain what to do. */
class BeforeMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $m = Member::create(['member_code' => 'BM1', 'name' => 'Old Buyer', 'phone' => '9100000001', 'status' => 'active', 'region_id' => Region::where('code', 'MAIN')->value('id')]);
        $plan = \App\Models\Plan::create(['name' => 'P', 'slug' => 'p', 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $m->region_id]);
        Order::create(['order_code' => 'ORD-OLD', 'member_id' => $m->id, 'plan_id' => $plan->id, 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => 'cancelled']);

        Schema::drop('credit_notes');
        Schema::drop('credit_note_sequences');
        Schema::drop('audit_logs');
        Schema::table('users', fn ($t) => $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }

    public function test_the_orders_list_and_the_dashboard_still_open(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk()->assertSee('ORD-OLD');
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertOk();
    }

    public function test_signing_in_and_changing_things_still_work_without_the_audit_table(): void
    {
        $this->post(route('admin.login.submit'), ['email' => 'a@t.com', 'password' => 'secret123'])->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->admin, 'web')->put(route('admin.account.password.update'), ['current_password' => 'secret123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertSessionHasNoErrors();
    }

    public function test_the_gst_report_shows_invoices_even_without_the_credit_note_table(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst'))->assertOk();
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst.csv'))->assertOk();
    }

    public function test_the_audit_log_and_two_factor_pages_explain_the_missing_update(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.audit-log.index'))->assertOk()->assertSee('database update')->assertSee('php artisan migrate');
        $this->actingAs($this->admin, 'web')->get(route('admin.account.two-factor'))->assertOk()->assertSee('database update')->assertSee('php artisan migrate');
        $this->actingAs($this->admin, 'web')->post(route('admin.account.two-factor.enable'))->assertRedirect()->assertSessionHas('error');
    }

    public function test_issuing_a_credit_note_says_what_is_missing_instead_of_crashing(): void
    {
        $order = Order::first();
        $order->update(['status' => 'paid']);

        $this->actingAs($this->admin, 'web')->post(route('admin.orders.credit-note.store', $order), ['reason' => 'x', 'refund_method' => 'cash'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('paid', $order->fresh()->status, 'Nothing was changed');
    }
}
