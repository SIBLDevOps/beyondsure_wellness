<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportMobileNumberTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '9811122233';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('gst_pct', 12);
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $member = Member::create(['member_code' => 'MOB001', 'name' => 'Mobile Person', 'phone' => self::PHONE, 'status' => 'active']);
        $plan = Plan::create(['name' => 'Plan', 'slug' => 'plan', 'price' => 1000, 'bv' => 400, 'is_active' => true]);

        // A first order (settled) and a repeat order (still unsettled) so the repurchase and pending reports have rows.
        Order::create(['order_code' => 'ORD-A', 'member_id' => $member->id, 'plan_id' => $plan->id, 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => 'settled']);
        Order::create(['order_code' => 'ORD-B', 'member_id' => $member->id, 'plan_id' => $plan->id, 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => 'paid', 'is_repurchase' => true]);
        LedgerEntry::create(['member_id' => $member->id, 'type' => 'self', 'amount' => 40, 'description' => 'self']);
    }

    public function test_every_report_page_that_lists_members_shows_their_mobile_number(): void
    {
        foreach (['sales', 'repurchase', 'payouts', 'levels', 'pending'] as $report) {
            $this->actingAs($this->admin, 'web')->get(route("admin.reports.$report"))
                ->assertOk()->assertSee(self::PHONE);
        }
    }

    public function test_member_csv_exports_have_a_mobile_column_with_the_number(): void
    {
        $csvs = [
            'admin.reports.sales.top-buyers.csv', 'admin.reports.sales.purchases.csv',
            'admin.reports.payouts.top-earners.csv', 'admin.reports.levels.earners.csv',
            'admin.reports.repurchase.orders.csv', 'admin.reports.repurchase.buyers.csv',
            'admin.reports.pending.csv', 'admin.reports.pending.withdrawals.csv', 'admin.reports.pending.unsettled.csv',
        ];

        foreach ($csvs as $route) {
            $body = $this->actingAs($this->admin, 'web')->get(route($route))->assertOk()->streamedContent();
            $this->assertStringContainsString('Mobile', $body, "$route has no Mobile column");
            if (! in_array($route, ['admin.reports.pending.csv', 'admin.reports.pending.withdrawals.csv'], true)) {
                $this->assertStringContainsString(self::PHONE, $body, "$route is missing the number");
            }
        }
    }
}
