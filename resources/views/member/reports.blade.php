@extends('layouts.member')

@section('title', 'My earnings report')

@section('content')
    <p class="text-secondary mb-4">Your income broken down by compensation stream, your 6-month earnings statement, and your binary team's contribution.</p>

    {{-- Top KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="text-secondary small">Total earned</div>
                        <div class="fs-5 fw-bold text-success">₹{{ number_format($totalEarned, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="text-secondary small">Wallet balance</div>
                        <div class="fs-5 fw-bold">₹{{ number_format($walletBalance, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="text-secondary small">Total withdrawn</div>
                        <div class="fs-5 fw-bold">₹{{ number_format($totalWithdrawn, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-bag-check"></i></div>
                    <div>
                        <div class="text-secondary small">My purchases</div>
                        <div class="fs-5 fw-bold">₹{{ number_format($totalSpent, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size:.72rem;">{{ $orderCount }} orders</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Income by Type Professional Table --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0">Income by type</h2>
                        <div class="text-body-tertiary small">All-time earnings across each compensation stream</div>
                    </div>
                    <span class="badge bg-success-subtle text-success">₹{{ number_format($totalEarned, 0) }}</span>
                </div>
                <div class="table-responsive">
                    @php
                        $typeColors = ['self' => 'success', 'sponsor' => 'info', 'matching' => 'primary', 'rank' => 'warning'];
                        $maxType = max($byType->max('total'), 1);
                    @endphp
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Income Stream</th>
                                <th class="text-center">Entries</th>
                                <th>Share</th>
                                <th class="text-end pe-3">Total Earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($byType as $row)
                                @php $pct = $totalEarned > 0 ? round(($row->total / $totalEarned) * 100, 1) : 0; @endphp
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-{{ $typeColors[$row->type] ?? 'secondary' }}-subtle text-{{ $typeColors[$row->type] ?? 'secondary' }} text-capitalize">
                                            {{ $row->type }}
                                        </span>
                                    </td>
                                    <td class="text-center small text-secondary">{{ $row->entries }}</td>
                                    <td style="width: 32%;">
                                        <div class="progress" style="height:.45rem;">
                                            <div class="progress-bar bg-{{ $typeColors[$row->type] ?? 'secondary' }}" style="width: {{ max(4, $row->total / $maxType * 100) }}%"></div>
                                        </div>
                                        <div class="text-body-tertiary" style="font-size: .68rem;">{{ $pct }}% of total</div>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold">₹{{ number_format($row->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-tertiary py-4 small">
                                        No income yet — earnings will appear here once a cycle you're part of is approved.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 6-Month Income Trend Professional Table --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 mb-0">6-month income trend</h2>
                    <div class="text-body-tertiary small">Total income earned each month across all streams</div>
                </div>
                <div class="table-responsive">
                    @php $maxTrend = max($trend->max('total'), 1); @endphp
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Month</th>
                                <th style="width: 45%;">Volume Bar</th>
                                <th class="text-end pe-3">Monthly Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($trend as $row)
                                <tr>
                                    <td class="ps-3 fw-medium">{{ $row->month }}</td>
                                    <td>
                                        <div class="progress" style="height:.5rem;">
                                            <div class="progress-bar bg-success" style="width: {{ max(4, $row->total / $maxTrend * 100) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold text-success">₹{{ number_format($row->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-body-tertiary py-4 small">
                                        No income in the last 6 months.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- My Team at a Glance Table --}}
    <div class="card border">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h2 class="h6 mb-0">My team, at a glance</h2>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('member.downline.index') }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-diagram-3 me-1"></i>See full downline
                </a>
                <a href="{{ route('member.levels.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-bar-chart-steps me-1"></i>Level report
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Total Subtree Members</th>
                        <th>Active Direct Referrals</th>
                        <th>Left Binary Leg BV</th>
                        <th>Right Binary Leg BV</th>
                        <th class="pe-3">Matched BV So Far</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ps-3 fs-5 fw-bold">{{ $subtreeSize }}</td>
                        <td class="fs-5 fw-bold text-success">{{ $activeDirects }}</td>
                        <td class="fs-5 fw-bold text-info-emphasis">₹{{ number_format($legTotal->left_bv ?? 0, 0) }}</td>
                        <td class="fs-5 fw-bold text-success">₹{{ number_format($legTotal->right_bv ?? 0, 0) }}</td>
                        <td class="pe-3 fs-5 fw-bold text-primary">₹{{ number_format($legTotal->matched_bv ?? 0, 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
