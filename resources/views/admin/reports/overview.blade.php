@extends('layouts.admin')

@section('title', 'Business overview')

@section('content')
    @include('partials.reports-nav')

    @php
        $inr = fn ($n, $d = 0) => ($n < 0 ? '−' : '') . '₹' . number_format(abs($n), $d);
        $profit = $data['net_profit'];
        $isProfit = $profit >= 0;
        $scopeName = request()->integer('region') ? \App\Models\Region::whereKey(request()->integer('region'))->value('name') : null;
        $taxable = max($data['taxable_revenue'], 0.01);
        $targets = $data['target_split'];
    @endphp

    {{-- 1. The answer first: profit or loss --}}
    <div class="card border mb-4" style="border-color: var(--bs-{{ $isProfit ? 'success' : 'danger' }}-border-subtle) !important; background: var(--bs-{{ $isProfit ? 'success' : 'danger' }}-bg-subtle);">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="small fw-semibold text-{{ $isProfit ? 'success' : 'danger' }}-emphasis text-uppercase" style="letter-spacing:.06em;">
                    {{ $isProfit ? 'Profit' : 'Loss' }} · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · {{ $scopeName ?? 'All regions' }}
                </div>
                <div class="display-6 fw-bold text-{{ $isProfit ? 'success' : 'danger' }}-emphasis">{{ $inr($profit) }}</div>
                <div class="small text-body-secondary">
                    @if ($data['order_count'] === 0)
                        No paid sales in this period.
                    @else
                        {{ $isProfit ? 'The business kept' : 'The business lost' }} {{ abs($data['net_margin_pct']) }}% of its sales (excluding GST) after product cost and member income.
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap text-center">
                @foreach ([['Sales', $data['taxable_revenue'], null], ['Product cost', $data['cost_of_goods'], '−'], ['Member income', $data['paid_out'], '−'], [$isProfit ? 'Profit' : 'Loss', $profit, '=']] as [$label, $value, $op])
                    @if ($op)<span class="fs-4 fw-light text-body-secondary">{{ $op }}</span>@endif
                    <div class="px-3 py-2 rounded-3 {{ $op === '=' ? 'bg-body bg-opacity-50 border' : '' }}" style="min-width: 110px;">
                        <div class="small text-secondary text-nowrap">{{ $label }}</div>
                        <div class="fw-bold fs-5 text-nowrap">{{ $inr($value) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- 2. Profit & loss statement --}}
        <div class="col-lg-7">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Profit &amp; loss statement</h2>
                    <div class="text-body-tertiary small">Where every rupee a customer paid went, in order.</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="ps-3">Customers paid <span class="text-secondary small">({{ $data['order_count'] }} paid orders)</span></td>
                                <td class="text-end pe-3 fw-semibold">{{ $inr($data['revenue']) }}</td>
                            </tr>
                            <tr class="text-secondary">
                                <td class="ps-4">− GST collected for the government</td>
                                <td class="text-end pe-3">{{ $inr(-$data['gst_collected']) }}</td>
                            </tr>
                            <tr class="table-light fw-semibold">
                                <td class="ps-3">= Sales (excluding GST)</td>
                                <td class="text-end pe-3">{{ $inr($data['taxable_revenue']) }}</td>
                            </tr>
                            <tr class="text-secondary">
                                <td class="ps-4">− Cost of the products sold</td>
                                <td class="text-end pe-3">{{ $inr(-$data['cost_of_goods']) }}</td>
                            </tr>
                            <tr class="table-light fw-semibold">
                                <td class="ps-3">= Gross profit <span class="text-secondary small fw-normal">({{ $data['taxable_revenue'] > 0 ? round($data['gross_profit'] / $taxable * 100, 1) : 0 }}%)</span></td>
                                <td class="text-end pe-3">{{ $inr($data['gross_profit']) }}</td>
                            </tr>
                            @foreach (['self' => 'Self income (own purchases)', 'sponsor' => 'Sponsor income (direct referrals)', 'matching' => 'Matching income (levels)', 'rank' => 'Rank pool'] as $type => $label)
                                <tr class="text-secondary">
                                    <td class="ps-4">− {{ $label }}</td>
                                    <td class="text-end pe-3">{{ $inr(-($data['income_by_type'][$type] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                            <tr class="fw-bold {{ $isProfit ? 'table-success' : 'table-danger' }}">
                                <td class="ps-3">= Net {{ $isProfit ? 'profit' : 'loss' }} <span class="fw-normal small">({{ $data['net_margin_pct'] }}% of sales)</span></td>
                                <td class="text-end pe-3 fs-5">{{ $inr($profit) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent small text-body-tertiary">
                    Member income is what cycles paid <em>in this period</em>, so it can include income earned on sales from earlier periods. Running costs such as rent, staff and ads are not tracked here.
                </div>
            </div>
        </div>

        {{-- 3. Target vs actual --}}
        <div class="col-lg-5">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-pie-chart me-2 text-success"></i>Target vs. actual</h2>
                    <div class="text-body-tertiary small">Out of every ₹100 of sales (excluding GST).</div>
                </div>
                <div class="card-body">
                    <div class="split-bar mb-1" title="Actual">
                        <div class="bg-warning" style="width: {{ min(100, max(0, $data['cost_pct'])) }}%"></div>
                        <div class="bg-primary" style="width: {{ min(100, max(0, $data['income_pct'])) }}%"></div>
                        <div class="{{ $isProfit ? 'bg-success' : 'bg-danger' }}" style="width: {{ min(100, max(0, $data['net_margin_pct'])) }}%"></div>
                    </div>
                    <div class="small text-secondary mb-3">Actual split</div>
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light text-secondary"><tr><th>Bucket</th><th class="text-end">Target</th><th class="text-end">Actual</th><th class="text-end">Status</th></tr></thead>
                        <tbody>
                            @php
                                $rows = [
                                    ['Product cost', 'bg-warning', $targets['cost'], $data['cost_pct'], false],
                                    ['Member income', 'bg-primary', $targets['pool'], $data['income_pct'], false],
                                    ['Company profit', $isProfit ? 'bg-success' : 'bg-danger', $targets['company'], $data['net_margin_pct'], true],
                                ];
                            @endphp
                            @foreach ($rows as [$name, $dot, $target, $actual, $higherIsBetter])
                                @php $ok = $data['order_count'] === 0 ? null : ($higherIsBetter ? $actual >= $target : $actual <= $target); @endphp
                                <tr>
                                    <td><span class="badge rounded-pill {{ $dot }} me-1">&nbsp;</span>{{ $name }}</td>
                                    <td class="text-end">{{ $target }}%</td>
                                    <td class="text-end fw-semibold">{{ $actual }}%</td>
                                    <td class="text-end">@if ($ok === null)<span class="text-body-tertiary">—</span>@elseif ($ok)<span class="badge bg-success-subtle text-success-emphasis">On target</span>@else<span class="badge bg-danger-subtle text-danger-emphasis">{{ $higherIsBetter ? 'Below' : 'Over' }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="small text-body-tertiary mt-3">Targets come from <a href="{{ route('admin.settings.edit') }}" class="text-decoration-none">Settings</a>. Effective matching rate so far: <strong>{{ $data['effective_matching_rate'] }}%</strong> of BV.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Compare regions, with profit --}}
    @if ($byRegion)
        <div class="card border mb-4">
            <div class="card-header bg-light-subtle border-bottom py-3">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-geo-alt me-2 text-primary"></i>Profit by region</h2>
                <div class="text-body-tertiary small">Same period, one row per region. Click a name to open every report for that region.</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-3">Region</th><th class="text-end">Members</th><th class="text-end">Paid orders</th>
                            <th class="text-end">Sales (excl. GST)</th><th class="text-end">Product cost</th><th class="text-end">Member income</th>
                            <th class="text-end">Profit / loss</th><th class="text-end pe-3">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byRegion as $row)
                            <tr>
                                <td class="ps-3 fw-semibold"><a href="{{ request()->fullUrlWithQuery(['region' => $row['region']->id]) }}" class="text-decoration-none">{{ $row['region']->name }}</a>
                                    @unless ($row['region']->is_active)<span class="badge bg-secondary-subtle text-secondary ms-1">Closed</span>@endunless</td>
                                <td class="text-end">{{ number_format($row['members']) }} <span class="text-body-tertiary small">(+{{ $row['new_members'] }})</span></td>
                                <td class="text-end">{{ number_format($row['orders']) }}</td>
                                <td class="text-end">{{ $inr($row['taxable']) }}</td>
                                <td class="text-end">{{ $inr($row['cost']) }}</td>
                                <td class="text-end">{{ $inr($row['paid_out']) }}</td>
                                <td class="text-end fw-bold text-{{ $row['profit'] >= 0 ? 'success' : 'danger' }}">{{ $inr($row['profit']) }}</td>
                                <td class="text-end pe-3"><span class="badge bg-{{ $row['profit'] >= 0 ? 'success' : 'danger' }}-subtle text-{{ $row['profit'] >= 0 ? 'success' : 'danger' }}-emphasis">{{ $row['margin_pct'] }}%</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                    @php
                        $tTax = $byRegion->sum('taxable'); $tProfit = $byRegion->sum('profit');
                    @endphp
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td class="ps-3">All regions</td>
                            <td class="text-end">{{ number_format($byRegion->sum('members')) }}</td>
                            <td class="text-end">{{ number_format($byRegion->sum('orders')) }}</td>
                            <td class="text-end">{{ $inr($tTax) }}</td>
                            <td class="text-end">{{ $inr($byRegion->sum('cost')) }}</td>
                            <td class="text-end">{{ $inr($byRegion->sum('paid_out')) }}</td>
                            <td class="text-end text-{{ $tProfit >= 0 ? 'success' : 'danger' }}">{{ $inr($tProfit) }}</td>
                            <td class="text-end pe-3">{{ $tTax > 0 ? round($tProfit / $tTax * 100, 1) : 0 }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- 5. New vs repurchase --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-info"></i>New business vs. repurchase</h2>
            <div class="text-body-tertiary small">Classification only. Self, sponsor and matching pay the same rate either way.</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="border rounded-3 p-3 h-100"><div class="text-secondary small">New business</div><div class="fs-4 fw-bold text-success">{{ $data['new_business_count'] }} orders</div><div class="text-body-tertiary small">{{ $inr($data['new_business_revenue']) }}</div></div></div>
                <div class="col-md-4"><div class="border rounded-3 p-3 h-100"><div class="text-secondary small">Repurchase</div><div class="fs-4 fw-bold text-info-emphasis">{{ $data['repurchase_count'] }} orders</div><div class="text-body-tertiary small">{{ $inr($data['repurchase_revenue']) }}</div></div></div>
                <div class="col-md-4"><div class="border rounded-3 p-3 h-100"><div class="text-secondary small">Repurchase ratio</div><div class="fs-4 fw-bold">{{ $data['repurchase_ratio'] }}%</div><div class="text-body-tertiary small">Repurchase revenue ÷ total revenue</div></div></div>
            </div>
        </div>
    </div>

    {{-- 6. Trend --}}
    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Revenue &amp; BV trend</h2>
                <div class="text-body-tertiary small">Month by month — showing {{ $data['trend']->firstItem() ?? 0 }}–{{ $data['trend']->lastItem() ?? 0 }} of {{ $data['trend']->total() }} months</div>
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
                        <th data-sort-key="number" class="text-end">Avg order</th>
                        <th style="min-width: 150px;">Volume</th>
                        <th class="text-end">Growth</th>
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
                            <td><div class="progress" style="height: 6px;"><div class="progress-bar bg-success" style="width: {{ max(2, $row->revenue / $maxRevenue * 100) }}%"></div></div></td>
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
            <div class="card-footer bg-white border-top py-3">{{ $data['trend']->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
