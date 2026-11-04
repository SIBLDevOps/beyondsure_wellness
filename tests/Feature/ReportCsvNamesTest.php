<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Every report download is named after what it is: company, class of report, which report, region, period and date. */
class ReportCsvNamesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-01 10:00:00');
        Setting::set('site_name', 'Acme Wellness+');
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fileName(string $route, array $query = []): string
    {
        $res = $this->actingAs($this->admin, 'web')->get(route($route, $query))->assertOk();
        $this->assertMatchesRegularExpression('/filename="?([^";]+)"?/', $res->headers->get('Content-Disposition'));
        preg_match('/filename="?([^";]+)"?/', $res->headers->get('Content-Disposition'), $m);

        return $m[1];
    }

    public function test_each_download_is_named_by_class_and_report(): void
    {
        $expected = [
            'admin.reports.overview.trend.csv' => 'acme-wellness-sales-overview-trend-20261101.csv',
            'admin.reports.sales.csv' => 'acme-wellness-sales-by-plan-20261101.csv',
            'admin.reports.sales.top-buyers.csv' => 'acme-wellness-sales-top-buyers-20261101.csv',
            'admin.reports.sales.purchases.csv' => 'acme-wellness-sales-purchases-20261101.csv',
            'admin.reports.repurchase.csv' => 'acme-wellness-sales-repurchase-20261101.csv',
            'admin.reports.repurchase.orders.csv' => 'acme-wellness-sales-repurchase-orders-20261101.csv',
            'admin.reports.repurchase.buyers.csv' => 'acme-wellness-sales-repurchase-buyers-20261101.csv',
            'admin.reports.payouts.csv' => 'acme-wellness-payouts-by-cycle-20261101.csv',
            'admin.reports.payouts.by-type.csv' => 'acme-wellness-payouts-by-type-20261101.csv',
            'admin.reports.payouts.top-earners.csv' => 'acme-wellness-payouts-top-earners-20261101.csv',
            'admin.reports.levels.csv' => 'acme-wellness-payouts-level-income-20261101.csv',
            'admin.reports.levels.earners.csv' => 'acme-wellness-payouts-level-earners-20261101.csv',
            'admin.reports.cascade.csv' => 'acme-wellness-payouts-cascade-7th-level-20261101.csv',
            'admin.reports.pending.csv' => 'acme-wellness-compliance-pending-cash-20261101.csv',
            'admin.reports.pending.withdrawals.csv' => 'acme-wellness-compliance-pending-withdrawals-20261101.csv',
            'admin.reports.pending.unsettled.csv' => 'acme-wellness-compliance-unsettled-orders-20261101.csv',
            'admin.reports.compliance.csv' => 'acme-wellness-compliance-distribution-20261101.csv',
            'admin.reports.compliance.overpaid.csv' => 'acme-wellness-compliance-overpaid-cycles-20261101.csv',
            'admin.reports.compliance.cost-cap.csv' => 'acme-wellness-compliance-cost-cap-20261101.csv',
        ];

        foreach ($expected as $route => $name) {
            $this->assertSame($name, $this->fileName($route), $route);
        }
    }

    public function test_the_gst_download_carries_the_month_and_the_class_tax(): void
    {
        $this->assertSame('acme-wellness-tax-gst-sales-2026-10.csv', $this->fileName('admin.reports.gst.csv', ['month' => '2026-10']));
        $this->assertSame('acme-wellness-tax-gst-sales-2026-11.csv', $this->fileName('admin.reports.gst.csv'), 'The current month when none is given');
    }

    public function test_a_region_filter_is_part_of_the_name(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);

        $this->assertSame('acme-wellness-sales-by-plan-gujarat-20261101.csv', $this->fileName('admin.reports.sales.csv', ['region' => $gj->id]));
        $this->assertSame('acme-wellness-tax-gst-sales-gujarat-2026-10.csv', $this->fileName('admin.reports.gst.csv', ['region' => $gj->id, 'month' => '2026-10']));
    }

    public function test_a_brand_name_with_odd_characters_still_gives_a_safe_file_name(): void
    {
        Setting::set('site_name', '../Évil "Co" & Sons/ <b>');

        $name = $this->fileName('admin.reports.sales.csv');

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+\.csv$/', $name);
        $this->assertStringNotContainsString('..', $name);
    }

    public function test_no_report_download_can_be_added_without_a_classified_name(): void
    {
        $csvRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()->filter(fn ($n) => str_starts_with($n, 'admin.reports.') && str_ends_with($n, '.csv'));

        $this->assertGreaterThanOrEqual(19, $csvRoutes->count());
        foreach ($csvRoutes as $route) {
            $this->assertNotNull(\App\Support\ReportNames::for($route), "{$route} has no classified name: add it to ReportNames");
        }
    }

    public function test_the_report_tabs_are_grouped_under_the_same_class_names(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.sales'))->assertOk()
            ->assertSee('Sales')->assertSee('Payouts')->assertSee('Compliance')->assertSee('Tax');
    }
}
