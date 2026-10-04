@extends('layouts.admin')

@section('title', 'Repurchase report')

@section('content')
    @include('partials.reports-nav')

    <p class="text-secondary mb-4" style="max-width: 720px;">
        Tracking repeat customer purchases across the network. A purchase is classified as a repurchase whenever the member
        had at least one prior paid order. Compensation (Self, Sponsor, Matching) applies identical rates to both new business and repurchases.
    </p>

    {{-- Top KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body p-3">
                    <div class="text-secondary small fw-medium">Repurchase Revenue</div>
                    <div class="fs-4 fw-bold text-success mt-1">₹{{ number_format($data['repurchase_revenue'], 0) }}</div>
                    <div class="text-body-tertiary small mt-1">
                        {{ $data['repurchase_count'] }} repeat orders (Avg: ₹{{ number_format($data['avg_repurchase_value'], 0) }})
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body p-3">
                    <div class="text-secondary small fw-medium">Repurchase Ratio</div>
                    <div class="fs-4 fw-bold text-primary mt-1">{{ $data['repurchase_ratio'] }}%</div>
                    <div class="text-body-tertiary small mt-1">
                        of ₹{{ number_format($data['total_revenue_all'], 0) }} total store revenue
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body p-3">
                    <div class="text-secondary small fw-medium">Repurchase BV</div>
                    <div class="fs-4 fw-bold text-info-emphasis mt-1">₹{{ number_format($data['repurchase_bv'], 0) }}</div>
                    <div class="text-body-tertiary small mt-1">
                        Taxable: ₹{{ number_format($data['repurchase_taxable'], 0) }} · GST: ₹{{ number_format($data['repurchase_gst'], 0) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body p-3">
                    <div class="text-secondary small fw-medium">Active Repeat Buyers</div>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ $data['unique_repeat_buyers'] }}</div>
                    <div class="text-body-tertiary small mt-1">
                        Members who reordered in this date range
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 1. Repurchase Orders Table --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Repurchase Orders Log</h2>
                <div class="text-body-tertiary small">
                    Every repeat order with product breakdown &amp; GST — showing {{ $data['orders']->firstItem() ?? 0 }}–{{ $data['orders']->lastItem() ?? 0 }} of {{ $data['orders']->total() }} orders
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input class="form-control" placeholder="Filter orders or members…" data-table-filter="#repurchaseOrdersTable">
                </div>
                <a href="{{ route('admin.reports.repurchase.orders.csv', request()->query()) }}" class="btn btn-sm btn-outline-success text-nowrap">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="repurchaseOrdersTable" data-sortable>
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
                    @forelse ($data['orders'] as $p)
                        <tr>
                            <td class="text-body-tertiary small">{{ $p['date'] ? $p['date']->format('d M Y') : '—' }}</td>
                            <td class="font-monospace small fw-semibold">{{ $p['order_code'] }}</td>
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
                        <tr><td colspan="8" class="text-center text-body-tertiary py-4">No repurchase orders in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['orders']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['orders']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    {{-- 2. Repurchase by Plan & Top Repeat Buyers in Grid --}}
    <div class="row g-4">
        {{-- Left: Repurchase by Plan --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-success"></i>Repurchase by Plan</h2>
                        <div class="text-body-tertiary small">
                            Which bundles are repurchased most — showing {{ $data['by_plan']->firstItem() ?? 0 }}–{{ $data['by_plan']->lastItem() ?? 0 }} of {{ $data['by_plan']->total() }}
                        </div>
                    </div>
                    <a href="{{ route('admin.reports.repurchase.csv', request()->query()) }}" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i>CSV
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-sortable>
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th data-sort-key="text">Plan</th>
                                <th data-sort-key="number" class="text-end">Orders</th>
                                <th data-sort-key="number" class="text-end">Revenue</th>
                                <th data-sort-key="number" class="text-end">BV</th>
                                <th data-sort-key="number" class="text-end">Share %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['by_plan'] as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row->plan->name ?? '—' }}</td>
                                    <td class="text-end">{{ $row->orders }}</td>
                                    <td class="text-end fw-semibold" data-sort-value="{{ $row->revenue }}">₹{{ number_format($row->revenue, 0) }}</td>
                                    <td class="text-end text-secondary" data-sort-value="{{ $row->bv }}">₹{{ number_format($row->bv, 0) }}</td>
                                    <td class="text-end" data-sort-value="{{ $row->share_pct }}">
                                        <span class="badge rounded-pill bg-success-subtle text-success">{{ $row->share_pct }}%</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-tertiary py-4">No plan sales in this range.</td></tr>
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
        </div>

        {{-- Right: Top Repeat Buyers --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-trophy me-2 text-warning"></i>Top Repeat Buyers</h2>
                        <div class="text-body-tertiary small">
                            Members with most repurchases — showing {{ $data['top_buyers']->firstItem() ?? 0 }}–{{ $data['top_buyers']->lastItem() ?? 0 }} of {{ $data['top_buyers']->total() }}
                        </div>
                    </div>
                    <a href="{{ route('admin.reports.repurchase.buyers.csv', request()->query()) }}" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i>CSV
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th class="text-end">Repeat Orders</th>
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
                                    <td class="text-end fw-semibold">{{ $row->repurchase_orders }}</td>
                                    <td class="text-end fw-semibold text-success">₹{{ number_format($row->total_spent, 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-4">No repeat buyers in this range.</td></tr>
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
        </div>
    </div>
@endsection
