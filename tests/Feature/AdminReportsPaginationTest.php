<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminReportsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Setting::set('split_cost_pct', 40);
        Setting::set('split_pool_pct', 40);
        Setting::set('split_company_pct', 20);
        Setting::set('self_pct', 10);
        Setting::set('sponsor_pct', 20);
        Setting::set('matching_pct', 10);
        Setting::set('cascade_depth', 7);
    }

    public function test_sales_report_passes_paginated_collections_to_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.sales'));

        $response->assertStatus(200);
        $data = $response->viewData('data');

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['by_plan']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['top_buyers']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['purchases']);
        $this->assertArrayHasKey('by_plan_all', $data);
        $this->assertArrayHasKey('top_buyers_all', $data);
    }

    public function test_payouts_report_passes_paginated_collections_to_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.payouts'));

        $response->assertStatus(200);
        $data = $response->viewData('data');

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['cycles']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['top_earners']);
        $this->assertArrayHasKey('cycles_all', $data);
        $this->assertArrayHasKey('top_earners_all', $data);
    }

    public function test_pending_report_passes_paginated_collections_to_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.pending'));

        $response->assertStatus(200);
        $data = $response->viewData('data');

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['pending_payments']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['withdrawals_awaiting_dispatch']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['unsettled_orders']);
        $this->assertArrayHasKey('pending_payments_all', $data);
        $this->assertArrayHasKey('withdrawals_awaiting_dispatch_all', $data);
        $this->assertArrayHasKey('unsettled_orders_all', $data);
    }

    public function test_compliance_report_passes_paginated_collections_to_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.compliance'));

        $response->assertStatus(200);
        $data = $response->viewData('data');

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['by_month']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['overpaid_cycles']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['cost_cap_breaches']);
        $this->assertArrayHasKey('by_month_all', $data);
        $this->assertArrayHasKey('overpaid_cycles_all', $data);
        $this->assertArrayHasKey('cost_cap_breaches_all', $data);
    }

    public function test_overview_report_passes_paginated_trend_to_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.overview'));

        $response->assertStatus(200);
        $data = $response->viewData('data');

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['trend']);
        $this->assertArrayHasKey('trend_all', $data);
    }

    public function test_repurchase_report_passes_paginated_collections_to_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.repurchase'));

        $response->assertStatus(200);
        $data = $response->viewData('data');

        $this->assertInstanceOf(LengthAwarePaginator::class, $data['orders']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['top_buyers']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $data['by_plan']);
        $this->assertArrayHasKey('orders_all', $data);
        $this->assertArrayHasKey('top_buyers_all', $data);
        $this->assertArrayHasKey('by_plan_all', $data);
        $this->assertArrayHasKey('repurchase_revenue', $data);
        $this->assertArrayHasKey('repurchase_ratio', $data);
    }

    public function test_csv_exports_stream_unpaginated_data(): void
    {
        $salesCsv = $this->actingAs($this->admin)->get(route('admin.reports.sales.csv'));
        $salesCsv->assertStatus(200);
        $salesCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $buyersCsv = $this->actingAs($this->admin)->get(route('admin.reports.sales.top-buyers.csv'));
        $buyersCsv->assertStatus(200);
        $buyersCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $trendCsv = $this->actingAs($this->admin)->get(route('admin.reports.overview.trend.csv'));
        $trendCsv->assertStatus(200);
        $trendCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $pendingCsv = $this->actingAs($this->admin)->get(route('admin.reports.pending.csv'));
        $pendingCsv->assertStatus(200);
        $pendingCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $repurchaseCsv = $this->actingAs($this->admin)->get(route('admin.reports.repurchase.csv'));
        $repurchaseCsv->assertStatus(200);
        $repurchaseCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $repurchaseOrdersCsv = $this->actingAs($this->admin)->get(route('admin.reports.repurchase.orders.csv'));
        $repurchaseOrdersCsv->assertStatus(200);
        $repurchaseOrdersCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $repurchaseBuyersCsv = $this->actingAs($this->admin)->get(route('admin.reports.repurchase.buyers.csv'));
        $repurchaseBuyersCsv->assertStatus(200);
        $repurchaseBuyersCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
