@extends('layouts.admin')

@section('title', 'Payouts')

@section('content')
    @include('partials.reports-nav')

    <p class="text-secondary mb-4" style="max-width: 680px;">
        How compensation actually moved: which cycles paid what share of their pool, which income type
        (Self/Sponsor/Matching/Rank) makes up the bulk of payouts, and how concentrated earnings are among
        top members.
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border"><div class="card-body">
                <div class="text-secondary small">Top-10% earner concentration</div>
                <div class="fs-3 fw-bold">{{ $data['top10_concentration'] }}%</div>
                <div class="text-body-tertiary small">Share of all income going to the top 10% of earners — high values can flag over-reliance on a few members.</div>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card border"><div class="card-body">
                <div class="text-secondary small">Wallet liability</div>
                <div class="fs-3 fw-bold">₹{{ number_format($data['wallet_liability'], 0) }}</div>
                <div class="text-body-tertiary small">Total earned minus total withdrawn — what's still sitting in member wallets, all-time.</div>
            </div></div>
        </div>
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-success"></i>Cycles — Pool / Paid / Paid %</h2>
                <div class="text-body-tertiary small">"Paid %" should almost always be under 100% — showing {{ $data['cycles']->firstItem() ?? 0 }}–{{ $data['cycles']->lastItem() ?? 0 }} of {{ $data['cycles']->total() }} cycles</div>
            </div>
            <a href="{{ route('admin.reports.payouts.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Period</th>
                        <th>Status</th>
                        <th class="text-end">Pool</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Paid %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['cycles'] as $c)
                        @php $pct = $c->pool_bv > 0 ? round($c->total_paid / $c->pool_bv * 100, 1) : 0; @endphp
                        <tr>
                            <td class="fw-medium">{{ $c->period_start->format('d M') }} – {{ $c->period_end->format('d M Y') }}</td>
                            <td>
                                <span class="badge rounded-pill bg-{{ $c->status === 'approved' ? 'success' : 'warning' }}-subtle text-{{ $c->status === 'approved' ? 'success' : 'warning-emphasis' }} text-capitalize">{{ $c->status }}</span>
                            </td>
                            <td class="text-end">₹{{ number_format($c->pool_bv, 0) }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($c->total_paid, 0) }}</td>
                            <td class="text-end">
                                <span class="{{ $pct > 100 ? 'text-danger fw-semibold' : 'fw-medium' }}">{{ $pct }}%</span>
                                @if ($pct > 100) <span class="badge bg-danger-subtle text-danger ms-1">over pool</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-tertiary py-4">No cycles in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['cycles']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['cycles']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-layers me-2 text-primary"></i>Income by Compensation Type</h2>
                <div class="text-body-tertiary small">Which compensation category is driving payouts this period</div>
            </div>
            <a href="{{ route('admin.reports.payouts.by-type.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        @php
            $typeColors = ['self' => 'success', 'sponsor' => 'info', 'matching' => 'primary', 'rank' => 'warning'];
            $maxType = max($data['by_type']->max('total'), 1);
            $sumTypeTotal = max($data['by_type']->sum('total'), 1);
        @endphp
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Compensation Type</th>
                        <th class="text-end">Ledger Entries</th>
                        <th class="text-end">Total Paid</th>
                        <th class="text-end">Share %</th>
                        <th style="min-width: 180px;">Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['by_type'] as $row)
                        @php
                            $c = $typeColors[$row->type] ?? 'secondary';
                            $sharePct = round($row->total / $sumTypeTotal * 100, 1);
                        @endphp
                        <tr>
                            <td>
                                <span class="badge rounded-pill bg-{{ $c }}-subtle text-{{ $c === 'warning' || $c === 'info' ? $c.'-emphasis' : $c }} text-capitalize">{{ $row->type }}</span>
                            </td>
                            <td class="text-end">{{ number_format($row->entries) }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($row->total, 0) }}</td>
                            <td class="text-end text-secondary small">{{ $sharePct }}%</td>
                            <td>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-{{ $c }}" style="width: {{ max(2, $row->total / $maxType * 100) }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-tertiary py-4">No income posted in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-award me-2 text-warning"></i>Top Earners</h2>
                <div class="text-body-tertiary small">Ranked by total income this period, across all types — showing {{ $data['top_earners']->firstItem() ?? 0 }}–{{ $data['top_earners']->lastItem() ?? 0 }} of {{ $data['top_earners']->total() }} earners</div>
            </div>
            <a href="{{ route('admin.reports.payouts.top-earners.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th class="text-end">Total Earned</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['top_earners'] as $i => $row)
                        <tr>
                            <td class="text-secondary small">{{ ($data['top_earners']->firstItem() ?? 1) + $i }}</td>
                            <td class="fw-medium">
                                @if ($row->member)
                                    <a href="{{ route('admin.members.show', $row->member) }}" class="link-dark text-decoration-none">{{ $row->member->name }}</a>
                                    <span class="font-monospace text-body-tertiary small ms-1">({{ $row->member->member_code }})</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-success">₹{{ number_format($row->total, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-tertiary py-4">No earners in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['top_earners']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['top_earners']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
