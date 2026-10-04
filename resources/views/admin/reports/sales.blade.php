@extends('layouts.admin')

@section('title', 'Sales & products')

@section('content')
    @include('partials.reports-nav')

    <p class="text-secondary mb-4" style="max-width: 680px;">
        Which plan bundles are actually selling, what margin each one leaves after cost of goods, and who's
        buying the most — useful for spotting an underperforming bundle or your most valuable customers.
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border"><div class="card-body">
                <div class="text-secondary small">Total revenue</div>
                <div class="fs-3 fw-bold">₹{{ number_format($data['total_revenue'], 0) }}</div>
                <div class="text-body-tertiary small">MRP collected, incl. GST</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border"><div class="card-body">
                <div class="text-secondary small">GST collected</div>
                <div class="fs-3 fw-bold text-warning-emphasis">₹{{ number_format($data['total_gst_collected'], 0) }}</div>
                <div class="text-body-tertiary small">A liability to remit, not company revenue</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border"><div class="card-body">
                <div class="text-secondary small">Taxable value</div>
                <div class="fs-3 fw-bold">₹{{ number_format($data['total_taxable_value'], 0) }}</div>
                <div class="text-body-tertiary small">Revenue with GST backed out</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border"><div class="card-body">
                <div class="text-secondary small">Total margin</div>
                <div class="fs-3 fw-bold {{ $data['total_margin'] >= 0 ? '' : 'text-danger' }}">₹{{ number_format($data['total_margin'], 0) }}</div>
                <div class="text-body-tertiary small">Taxable value minus cost of goods</div>
            </div></div>
        </div>
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-success"></i>Revenue &amp; Margin by Plan</h2>
                <div class="text-body-tertiary small">Ranked by revenue. Margin = taxable value (GST excluded) − (bundle cost × orders) — showing {{ $data['by_plan']->firstItem() ?? 0 }}–{{ $data['by_plan']->lastItem() ?? 0 }} of {{ $data['by_plan']->total() }} plans</div>
            </div>
            <a href="{{ route('admin.reports.sales.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th data-sort-key="text">Plan</th>
                        <th data-sort-key="number" class="text-end">Orders</th>
                        <th data-sort-key="number" class="text-end">Revenue</th>
                        <th data-sort-key="number" class="text-end">Avg Order Value</th>
                        <th data-sort-key="number" class="text-end">GST Collected</th>
                        <th data-sort-key="number" class="text-end">Taxable Value</th>
                        <th data-sort-key="number" class="text-end">BV</th>
                        <th data-sort-key="number" class="text-end">Cost</th>
                        <th data-sort-key="number" class="text-end">Margin</th>
                        <th data-sort-key="number" class="text-end">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['by_plan'] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->plan->name }}</td>
                            <td class="text-end">{{ $row->orders }}</td>
                            <td class="text-end fw-semibold" data-sort-value="{{ $row->revenue }}">₹{{ number_format($row->revenue, 0) }}</td>
                            <td class="text-end text-secondary">₹{{ number_format($row->avg_order_value, 0) }}</td>
                            <td class="text-end text-warning-emphasis">₹{{ number_format($row->gst_collected, 0) }}</td>
                            <td class="text-end text-secondary">₹{{ number_format($row->taxable_value, 0) }}</td>
                            <td class="text-end text-secondary">₹{{ number_format($row->bv, 0) }}</td>
                            <td class="text-end text-secondary">₹{{ number_format($row->cost, 0) }}</td>
                            <td class="text-end fw-semibold {{ $row->margin >= 0 ? 'text-success' : 'text-danger' }}" data-sort-value="{{ $row->margin }}">₹{{ number_format($row->margin, 0) }}</td>
                            <td class="text-end" data-sort-value="{{ $row->margin_pct }}">
                                <span class="badge rounded-pill bg-{{ $row->margin_pct >= 40 ? 'success' : ($row->margin_pct >= 20 ? 'warning' : 'danger') }}-subtle text-{{ $row->margin_pct >= 40 ? 'success' : ($row->margin_pct >= 20 ? 'warning-emphasis' : 'danger') }}">
                                    {{ $row->margin_pct }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-body-tertiary py-4">No sales in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['by_plan']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['by_plan']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-trophy me-2 text-warning"></i>Top Buyers</h2>
                <div class="text-body-tertiary small">Members who have spent the most in this date range — showing {{ $data['top_buyers']->firstItem() ?? 0 }}–{{ $data['top_buyers']->lastItem() ?? 0 }} of {{ $data['top_buyers']->total() }} buyers</div>
            </div>
            <a href="{{ route('admin.reports.sales.top-buyers.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['top_buyers'] as $i => $row)
                        <tr>
                            <td class="text-secondary small">{{ ($data['top_buyers']->firstItem() ?? 1) + $i }}</td>
                            <td class="fw-medium">
                                @if ($row->member)
                                    <a href="{{ route('admin.members.show', $row->member) }}" class="link-dark text-decoration-none">{{ $row->member->name }}</a>
                                    <span class="font-monospace text-body-tertiary small ms-1">({{ $row->member->member_code }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">{{ $row->orders }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($row->total_spent, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-4">No buyers in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['top_buyers']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['top_buyers']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-primary"></i>Member Purchases — Who Bought What</h2>
                <div class="text-body-tertiary small">
                    Every settled order, the exact products it contains, and its GST breakup —
                    showing {{ $data['purchases']->firstItem() ?? 0 }}–{{ $data['purchases']->lastItem() ?? 0 }} of {{ $data['purchases']->total() }} orders in this range
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input class="form-control" placeholder="Filter by member, plan, product…" data-table-filter="#purchasesTable">
                </div>
                <a href="{{ route('admin.reports.sales.purchases.csv', request()->query()) }}" class="btn btn-sm btn-outline-success text-nowrap"><i class="bi bi-download me-1"></i>Export CSV</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="purchasesTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th data-sort-key="text">Date</th>
                        <th>Order Code</th>
                        <th data-sort-key="text">Member</th>
                        <th data-sort-key="text">Plan</th>
                        <th>Products Purchased</th>
                        <th class="text-end" data-sort-key="number">MRP</th>
                        <th class="text-end" data-sort-key="number">GST</th>
                        <th class="text-end" data-sort-key="number">BV</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['purchases'] as $p)
                        <tr>
                            <td class="text-body-tertiary small">{{ $p['date']->format('d M Y') }}</td>
                            <td class="font-monospace small">{{ $p['order_code'] }}</td>
                            <td class="fw-medium">
                                @if ($p['member'])
                                    <a href="{{ route('admin.members.show', $p['member']) }}" class="link-dark text-decoration-none">{{ $p['member']->name }}</a>
                                    <span class="font-monospace text-body-tertiary small d-block">{{ $p['member']->member_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $p['plan']->name ?? '—' }}</td>
                            <td class="small text-secondary">{{ $p['products'] ?: '—' }}</td>
                            <td class="text-end fw-semibold" data-sort-value="{{ $p['amount'] }}">₹{{ number_format($p['amount'], 0) }}</td>
                            <td class="text-end text-warning-emphasis" data-sort-value="{{ $p['gst'] }}">₹{{ number_format($p['gst'], 0) }}</td>
                            <td class="text-end text-success" data-sort-value="{{ $p['bv'] }}">₹{{ number_format($p['bv'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-tertiary py-4">No purchases in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['purchases']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['purchases']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
