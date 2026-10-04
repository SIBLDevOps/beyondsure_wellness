@extends('layouts.admin')

@section('title', 'Business overview')

@section('content')
    @include('partials.reports-nav')

    <p class="text-secondary mb-4" style="max-width: 680px;">
        The headline numbers for the selected date range: how much came in, how much went out as member
        compensation, and whether the actual cost/pool/company split is tracking the targets set in
        <a href="{{ route('admin.settings.edit') }}" class="link-primary fw-medium text-decoration-none">Settings</a>.
    </p>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Revenue', 'value' => '₹' . number_format($data['revenue'], 0), 'sub' => $data['order_count'] . ' orders, incl. GST', 'icon' => 'bi-cash-stack', 'color' => 'success'],
            ['label' => 'GST collected', 'value' => '₹' . number_format($data['gst_collected'], 0), 'sub' => $data['gst_pct'] . '% — owed to govt., not margin', 'icon' => 'bi-receipt-cutoff', 'color' => 'danger'],
            ['label' => 'Taxable revenue', 'value' => '₹' . number_format($data['taxable_revenue'], 0), 'sub' => 'Revenue with GST backed out', 'icon' => 'bi-cash', 'color' => 'secondary'],
            ['label' => 'BV (pool basis)', 'value' => '₹' . number_format($data['bv'], 0), 'sub' => 'Compensation basis', 'icon' => 'bi-stack', 'color' => 'primary'],
            ['label' => 'Paid out', 'value' => '₹' . number_format($data['paid_out'], 0), 'sub' => 'Self+Sponsor+Match+Rank', 'icon' => 'bi-people', 'color' => 'warning'],
            ['label' => 'Payout %', 'value' => $data['payout_pct'] . '%', 'sub' => 'Paid out ÷ revenue', 'icon' => 'bi-percent', 'color' => 'info'],
            ['label' => 'Net margin', 'value' => '₹' . number_format($data['net_margin'], 0), 'sub' => 'Taxable revenue − paid out', 'icon' => 'bi-graph-up', 'color' => $data['net_margin'] >= 0 ? 'success' : 'danger'],
            ['label' => 'Effective matching rate', 'value' => $data['effective_matching_rate'] . '%', 'sub' => 'Matching paid ÷ BV (breaker: ' . $data['breaker_pct'] . '% per layer-event, not this total)', 'icon' => 'bi-shield-check', 'color' => 'info'],
        ] as $card)
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card border h-100">
                    <div class="card-body p-3">
                        <div class="stat-icon bg-{{ $card['color'] }}-subtle text-{{ $card['color'] }} mb-2" style="width:34px;height:34px;font-size:1rem;">
                            <i class="bi {{ $card['icon'] }}"></i>
                        </div>
                        <div class="text-secondary" style="font-size: .75rem;">{{ $card['label'] }}</div>
                        <div class="fs-5 fw-bold">{{ $card['value'] }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">{{ $card['sub'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-body-tertiary small mb-4" style="font-size: .8rem; max-width: 720px;">
        <i class="bi bi-info-circle me-1"></i>
        The circuit breaker caps matching paid on <strong>each individual layer's own event</strong> at {{ $data['breaker_pct'] }}% of
        that layer's new BV — it is not a ceiling on the company-wide total above. One purchase can make several ancestors
        newly-matched at once, and each of them runs their own capped cascade, so the aggregate ratio across the whole
        network can land above {{ $data['breaker_pct'] }}% even though no single event ever did. Treat this number as a
        trend to watch, not a value that's mathematically guaranteed to stay under the breaker.
    </p>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-pie-chart me-2 text-success"></i>Target Split vs. Net Margin</h2>
            <div class="text-body-tertiary small">Every ₹1 of taxable revenue (GST already removed) is meant to divide this way — cost of goods, the compensation pool, and company margin.</div>
        </div>
        <div class="card-body">
            <div class="split-bar mb-3" style="max-width: 560px;">
                <div class="bg-warning" style="width: {{ $data['target_split']['cost'] }}%"></div>
                <div class="bg-primary" style="width: {{ $data['target_split']['pool'] }}%"></div>
                <div class="bg-secondary" style="width: {{ $data['target_split']['company'] }}%"></div>
            </div>
            <div class="table-responsive" style="max-width: 560px;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th>Bucket</th>
                            <th class="text-end">Target Share</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <tr>
                            <td class="fw-medium"><span class="badge rounded-pill bg-warning me-2">&nbsp;</span>Cost of Goods</td>
                            <td class="text-end fw-semibold">{{ $data['target_split']['cost'] }}%</td>
                            <td class="text-secondary">Product manufacturing &amp; fulfillment cap</td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><span class="badge rounded-pill bg-primary me-2">&nbsp;</span>Compensation Pool</td>
                            <td class="text-end fw-semibold">{{ $data['target_split']['pool'] }}%</td>
                            <td class="text-secondary">Member payouts (Self, Level, Matching, Rank)</td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><span class="badge rounded-pill bg-secondary me-2">&nbsp;</span>Company Margin</td>
                            <td class="text-end fw-semibold">{{ $data['target_split']['company'] }}%</td>
                            <td class="text-secondary">Operations &amp; net retained share</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-info"></i>New Business vs. Repurchase</h2>
            <div class="text-body-tertiary small">Classification only — Self, Sponsor and Matching pay the exact same rate either way.</div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-secondary small">New business</div>
                        <div class="fs-4 fw-bold text-success">{{ $data['new_business_count'] }} orders</div>
                        <div class="text-body-tertiary small">₹{{ number_format($data['new_business_revenue'], 0) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-secondary small">Repurchase</div>
                        <div class="fs-4 fw-bold text-info-emphasis">{{ $data['repurchase_count'] }} orders</div>
                        <div class="text-body-tertiary small">₹{{ number_format($data['repurchase_revenue'], 0) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-secondary small">Repurchase ratio</div>
                        <div class="fs-4 fw-bold text-dark">{{ $data['repurchase_ratio'] }}%</div>
                        <div class="text-body-tertiary small">Repurchase revenue ÷ total revenue</div>
                    </div>
                </div>
            </div>
            <div class="small text-body-tertiary border-top pt-2" style="font-size: .8rem;">
                <i class="bi bi-info-circle me-1"></i>
                "Repurchase" means a member had at least one prior <em>paid</em> order before this one — it never affects payout
                math. This ratio naturally climbs over time as more members complete their first order, so a high number here
                reflects business maturity, not necessarily loyalty — don't quote it as a standalone retention metric.
            </div>
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Revenue &amp; BV Trend</h2>
                <div class="text-body-tertiary small">Revenue vs. BV each month, order volume, average order size, and growth vs. the prior month — showing {{ $data['trend']->firstItem() ?? 0 }}–{{ $data['trend']->lastItem() ?? 0 }} of {{ $data['trend']->total() }} months</div>
            </div>
            <a href="{{ route('admin.reports.overview.trend.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th data-sort-key="text">Month</th>
                        <th data-sort-key="number" class="text-end">Orders</th>
                        <th data-sort-key="number" class="text-end">Revenue</th>
                        <th data-sort-key="number" class="text-end">BV</th>
                        <th data-sort-key="number" class="text-end">Avg Order Value</th>
                        <th style="min-width: 150px;">Volume Visual</th>
                        <th class="text-end">Growth vs. Prior Month</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxRevenue = max($data['trend']->max('revenue'), 1); @endphp
                    @forelse ($data['trend'] as $row)
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $row->month }}</td>
                            <td class="text-end">{{ $row->orders }}</td>
                            <td class="text-end fw-semibold" data-sort-value="{{ $row->revenue }}">₹{{ number_format($row->revenue, 0) }}</td>
                            <td class="text-end text-secondary">₹{{ number_format($row->bv, 0) }}</td>
                            <td class="text-end text-secondary">₹{{ number_format($row->avg_order_value, 0) }}</td>
                            <td>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ max(2, $row->revenue / $maxRevenue * 100) }}%"></div>
                                </div>
                            </td>
                            <td class="text-end">
                                @if ($row->growth_pct === null)
                                    <span class="text-body-tertiary">—</span>
                                @else
                                    <span class="badge rounded-pill bg-{{ $row->growth_pct >= 0 ? 'success' : 'danger' }}-subtle text-{{ $row->growth_pct >= 0 ? 'success' : 'danger' }}">
                                        <i class="bi bi-arrow-{{ $row->growth_pct >= 0 ? 'up' : 'down' }}"></i> {{ abs($row->growth_pct) }}%
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-tertiary py-4">No sales in the last 6 months.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['trend']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['trend']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
