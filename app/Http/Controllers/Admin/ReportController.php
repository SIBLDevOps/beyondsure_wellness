<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function overview(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->businessOverview($from, $to);

        return view('admin.reports.overview', compact('data', 'from', 'to'));
    }

    public function overviewTrendCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->businessOverview($from, $to);

        $trendRows = $data['trend_all'] ?? $data['trend'];
        $rows = $trendRows->map(fn ($r) => [$r->month, $r->orders, $r->revenue, $r->bv, round($r->avg_order_value, 2), $r->growth_pct ?? '']);

        return $this->reports->csv($rows, ['Month', 'Orders', 'Revenue', 'BV', 'Avg order value', 'Growth % vs prev month']);
    }

    public function sales(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->salesAndProducts($from, $to);

        return view('admin.reports.sales', compact('data', 'from', 'to'));
    }

    public function salesCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->salesAndProducts($from, $to);

        $planRows = $data['by_plan_all'] ?? $data['by_plan'];
        $rows = $planRows->map(fn ($r) => [$r->plan->name, $r->orders, $r->revenue, round($r->avg_order_value, 2), $r->gst_collected, $r->taxable_value, $r->bv, $r->cost, $r->margin, $r->margin_pct]);

        return $this->reports->csv($rows, ['Plan', 'Orders', 'Revenue (incl. GST)', 'Avg order value', 'GST collected', 'Taxable value', 'BV', 'Cost', 'Margin', 'Margin %']);
    }

    public function salesTopBuyersCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->salesAndProducts($from, $to);

        $buyerRows = $data['top_buyers_all'] ?? $data['top_buyers'];
        $rows = $buyerRows->map(fn ($r) => [$r->member->name, $r->member->member_code, $r->orders, $r->total_spent]);

        return $this->reports->csv($rows, ['Member', 'Code', 'Orders', 'Total spent']);
    }

    public function salesPurchasesCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $purchases = $this->reports->memberPurchases($from, $to);

        $rows = $purchases->map(fn ($p) => [
            $p['order_code'], $p['date']->format('Y-m-d H:i'),
            $p['member']->name ?? '', $p['member']->member_code ?? '',
            $p['plan']->name ?? '', $p['products'],
            $p['amount'], $p['gst'], $p['taxable_value'], $p['bv'], $p['status'],
        ]);

        return $this->reports->csv($rows, ['Order code', 'Date', 'Member', 'Code', 'Plan', 'Products', 'MRP (incl. GST)', 'GST', 'Taxable value', 'BV', 'Status']);
    }

    public function payouts(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->payouts($from, $to);

        return view('admin.reports.payouts', compact('data', 'from', 'to'));
    }

    public function payoutsCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->payouts($from, $to);

        $cycles = $data['cycles_all'] ?? $data['cycles'];
        $rows = $cycles->map(fn ($c) => [
            $c->period_start->format('Y-m-d'), $c->period_end->format('Y-m-d'), $c->status,
            $c->pool_bv, $c->total_paid, $c->pool_bv > 0 ? round($c->total_paid / $c->pool_bv * 100, 1) : 0,
        ]);

        return $this->reports->csv($rows, ['Period start', 'Period end', 'Status', 'Pool BV', 'Total paid', 'Paid %']);
    }

    public function payoutsByTypeCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->payouts($from, $to);

        $rows = $data['by_type']->map(fn ($r) => [$r->type, $r->entries, $r->total]);

        return $this->reports->csv($rows, ['Type', 'Entries', 'Total']);
    }

    public function payoutsTopEarnersCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->payouts($from, $to);

        $earners = $data['top_earners_all'] ?? $data['top_earners'];
        $rows = $earners->map(fn ($r) => [$r->member->name ?? '—', $r->member->member_code ?? '', $r->total]);

        return $this->reports->csv($rows, ['Member', 'Code', 'Total earned']);
    }

    public function levels(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->levelIncomeDistribution($from, $to);
        $data['earners'] = $this->reports->memberIncomeBreakdownPaginated($from, $to);

        return view('admin.reports.levels', compact('data', 'from', 'to'));
    }

    public function levelsCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->levelIncomeDistribution($from, $to);

        $rows = $data['level_rows']->map(fn ($r) => [
            "Level {$r['level']}",
            $r['label'],
            "{$r['matching_rate']}%",
            $r['sponsor_rate'] ? "{$r['sponsor_rate']}%" : '—',
            $r['sponsor_paid'],
            $r['matching_paid'],
            $r['total_paid'],
            $r['unique_earners'],
            $r['entries_count'],
            "{$r['share_pct']}%",
        ]);

        return $this->reports->csv($rows, [
            'Level', 'Generation / Role', 'Matching Rate %', 'Sponsor Rate %',
            'Sponsor Income Paid (₹)', 'Matching Cascade Paid (₹)',
            'Total Income Distributed (₹)', 'Unique Earners', 'Entries Count', 'Share %',
        ]);
    }

    public function levelsEarnersCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $rows = $this->reports->memberIncomeBreakdown($from, $to);

        $csvRows = $rows->map(fn ($r) => [
            $r['member']->name ?? '—', $r['member']->member_code ?? '', $r['depth'],
            $r['self_income'], $r['sponsor_income'], $r['matching_income'], $r['rank_income'], $r['total_income'], $r['entries'],
        ]);

        return $this->reports->csv($csvRows, ['Member', 'Code', 'Tree Depth', 'Self Income', 'Sponsor Income', 'Matching Income', 'Rank Income', 'Total Income', 'Entries']);
    }

    public function pending(): View
    {
        $data = $this->reports->pendingAndCash();

        return view('admin.reports.pending', compact('data'));
    }

    public function pendingCsv(): StreamedResponse
    {
        $data = $this->reports->pendingAndCash();

        $payments = $data['pending_payments_all'] ?? $data['pending_payments'];
        $rows = $payments->map(fn ($p) => [$p->order->order_code, $p->order->member->name, $p->method, $p->reference, $p->created_at->format('Y-m-d H:i')]);

        return $this->reports->csv($rows, ['Order', 'Member', 'Method', 'Reference', 'Submitted']);
    }

    public function pendingWithdrawalsCsv(): StreamedResponse
    {
        $data = $this->reports->pendingAndCash();

        $withdrawals = $data['withdrawals_awaiting_dispatch_all'] ?? $data['withdrawals_awaiting_dispatch'];
        $rows = $withdrawals->map(fn ($w) => [$w->member->name, $w->member->member_code, $w->amount, $w->created_at->format('Y-m-d')]);

        return $this->reports->csv($rows, ['Member', 'Code', 'Amount', 'Approved']);
    }

    public function pendingUnsettledCsv(): StreamedResponse
    {
        $data = $this->reports->pendingAndCash();

        $orders = $data['unsettled_orders_all'] ?? $data['unsettled_orders'];
        $rows = $orders->map(fn ($o) => [$o->order_code, $o->member->name, $o->plan->name, $o->amount, $o->bv, $o->created_at->format('Y-m-d')]);

        return $this->reports->csv($rows, ['Order', 'Member', 'Plan', 'Amount', 'BV', 'Paid on']);
    }

    public function compliance(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->complianceSummary($from, $to);

        return view('admin.reports.compliance', compact('data', 'from', 'to'));
    }

    public function complianceCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->complianceSummary($from, $to);

        $byMonth = $data['by_month_all'] ?? $data['by_month'];
        $rows = $byMonth->map(fn ($r) => [$r->month, $r->distributed, $r->withdrawn]);

        return $this->reports->csv($rows, ['Month', 'BV/Income distributed', 'Withdrawn']);
    }

    public function complianceOverpaidCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->complianceSummary($from, $to);

        $cycles = $data['overpaid_cycles_all'] ?? $data['overpaid_cycles'];
        $rows = $cycles->map(fn ($c) => [$c->period_start->format('Y-m-d'), $c->period_end->format('Y-m-d'), $c->pool_bv, $c->total_paid]);

        return $this->reports->csv($rows, ['Period start', 'Period end', 'Pool BV', 'Total paid']);
    }

    public function complianceCostCapCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->complianceSummary($from, $to);

        $breaches = $data['cost_cap_breaches_all'] ?? $data['cost_cap_breaches'];
        $rows = $breaches->map(fn ($p) => [$p->name, $p->costForQty()]);

        return $this->reports->csv($rows, ['Plan', 'Cost']);
    }

    public function repurchase(Request $request): View
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->repurchaseReport($from, $to);

        return view('admin.reports.repurchase', compact('data', 'from', 'to'));
    }

    public function repurchaseCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->repurchaseReport($from, $to);

        $byPlan = $data['by_plan_all'] ?? $data['by_plan'];
        $rows = $byPlan->map(fn ($row) => [
            $row->plan->name ?? '—',
            $row->orders,
            $row->revenue,
            $row->bv,
            $row->avg_value,
            $row->share_pct.'%',
        ]);

        return $this->reports->csv($rows, ['Plan', 'Repurchase Orders', 'Revenue', 'BV', 'Avg Order Value', 'Share %']);
    }

    public function repurchaseOrdersCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->repurchaseReport($from, $to);

        $orders = $data['orders_all'] ?? $data['orders'];
        $rows = $orders->map(fn ($p) => [
            $p['date'] ? $p['date']->format('Y-m-d H:i') : '',
            $p['order_code'],
            $p['member']->name ?? '',
            $p['member']->member_code ?? '',
            $p['plan']->name ?? '',
            $p['products'],
            $p['amount'],
            $p['gst'],
            $p['taxable_value'],
            $p['bv'],
            $p['status'],
        ]);

        return $this->reports->csv($rows, ['Date', 'Order code', 'Member', 'Member code', 'Plan', 'Products', 'MRP', 'GST', 'Taxable value', 'BV', 'Status']);
    }

    public function repurchaseBuyersCsv(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = $this->reports->range($request->from, $request->to);
        $data = $this->reports->repurchaseReport($from, $to);

        $buyers = $data['top_buyers_all'] ?? $data['top_buyers'];
        $rows = $buyers->map(fn ($b) => [
            $b->member->name ?? '',
            $b->member->member_code ?? '',
            $b->repurchase_orders,
            $b->total_spent,
            $b->total_bv,
            $b->first_order_date ? $b->first_order_date->format('Y-m-d') : '—',
            $b->latest_order_date ? $b->latest_order_date->format('Y-m-d') : '—',
        ]);

        return $this->reports->csv($rows, ['Member', 'Member code', 'Repeat Orders', 'Total Spent', 'Total BV', 'First Order Date', 'Latest Repurchase Date']);
    }
}
