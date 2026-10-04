@extends('layouts.admin')

@section('title', 'Level-wise income distribution')

@section('content')
    @include('partials.reports-nav')

    {{-- Executive Context & Compensation Framework Header --}}
    <div class="card border mb-4 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="stat-icon bg-primary text-white rounded-3 p-2 flex-shrink-0" style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-diagram-3-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h2 class="h6 mb-0 fw-bold">How Level-Wise Income is Distributed in BeyondSure</h2>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">Analyst View</span>
                        </div>
                        <p class="text-secondary small mb-2 mt-1" style="max-width: 820px;">
                            Compensation is calculated strictly from a plan's <strong>Business Volume (BV)</strong>, independent of physical product costs.
                            Revenue flows through four synchronized streams with real-time circuit-breaker solvency protection.
                        </p>

                        <div class="d-flex flex-wrap gap-2 pt-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2">
                                <i class="bi bi-person-check me-1"></i>Buyer Self: {{ $data['rates']['self_pct'] }}%
                            </span>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1 px-2">
                                <i class="bi bi-person-plus me-1"></i>Level 1 Sponsor: {{ $data['rates']['sponsor_pct'] }}%
                            </span>
                            <span class="badge border py-1 px-2" style="background:#ede9fe; color:#6d28d9; border-color:#ddd6fe !important;">
                                <i class="bi bi-stack me-1"></i>Cascade: {{ $data['rates']['max_cascade_pct'] }}% ({{ $data['rates']['cascade_depth'] }} Levels)
                            </span>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-1 px-2">
                                <i class="bi bi-trophy me-1"></i>Rank Pool: {{ $data['rates']['rank_pool_pct'] }}%
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#governanceNotes" aria-expanded="false">
                        <i class="bi bi-info-circle me-1"></i>Upline Governance Rules
                    </button>
                </div>
            </div>

            <div class="collapse mt-3 pt-3 border-top" id="governanceNotes">
                <div class="p-3 bg-light rounded-2 small text-secondary">
                    <div class="fw-semibold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i>Depth &amp; Cascade Integrity Rules:</div>
                    <p class="mb-1">
                        Level depth here is measured from each member upward through <strong>their own</strong> sponsor chain — never
                        from the admin account downward. A member who joins at generation 8, 20, or deeper still earns exactly the
                        same {{ $data['rates']['cascade_depth'] }} levels, counted from themselves. The "Tree Depth" column in the
                        <strong>User-wise</strong> tab below is shown for context only — it never gates who gets paid.
                    </p>
                    <div class="text-body-tertiary" style="font-size: .75rem;">
                        Circuit Breaker: Layer-wise payouts are capped at {{ $data['rates']['breaker_pct'] }}% of cycle new BV. Individual weekly cap: ₹{{ number_format($data['rates']['weekly_cap'], 0) }} · Monthly cap: ₹{{ number_format($data['rates']['monthly_cap'], 0) }}.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4 Analyst Key Financial KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border h-100 shadow-sm hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-medium">Total level &amp; cascade paid</span>
                        <div class="stat-icon bg-primary-subtle text-primary" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-cash-coin"></i></div>
                    </div>
                    <div class="fs-4 fw-bold text-primary">₹{{ number_format($data['summary']['total_level_income'], 2) }}</div>
                    <div class="text-body-tertiary small mt-1" style="font-size: .72rem;">Sponsor + matching cascade</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border h-100 shadow-sm hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-medium">Direct sponsor (Level 1)</span>
                        <div class="stat-icon bg-info-subtle text-info-emphasis" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-person-check"></i></div>
                    </div>
                    <div class="fs-4 fw-bold text-info-emphasis">₹{{ number_format($data['summary']['total_sponsor'], 2) }}</div>
                    <div class="text-body-tertiary small mt-1" style="font-size: .72rem;">{{ $data['rates']['sponsor_pct'] }}% of order BV</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border h-100 shadow-sm hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-medium">Matching cascade paid</span>
                        <div class="stat-icon" style="width:32px;height:32px;font-size:.9rem;background:#ede9fe;color:#6d28d9;"><i class="bi bi-diagram-3"></i></div>
                    </div>
                    <div class="fs-4 fw-bold" style="color: #6d28d9;">₹{{ number_format($data['summary']['total_matching'], 2) }}</div>
                    <div class="text-body-tertiary small mt-1" style="font-size: .72rem;">{{ $data['rates']['matching_pct'] }}% per level matched</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border h-100 shadow-sm hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-medium">Active cascade depth</span>
                        <div class="stat-icon bg-dark-subtle text-dark" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-layers"></i></div>
                    </div>
                    <div class="fs-4 fw-bold text-dark">{{ $data['rates']['cascade_depth'] }} Levels</div>
                    <div class="text-body-tertiary small mt-1" style="font-size: .72rem;">Max {{ $data['rates']['max_cascade_pct'] }}% BV payout · {{ $data['summary']['effective_matching_rate'] }}% effective rate</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Analyst Analysis Container (Tabs) --}}
    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom pt-2 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="analysisTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-medium" id="level-wise-tab" data-bs-toggle="tab" data-bs-target="#level-wise-pane" type="button" role="tab">
                        <i class="bi bi-bar-chart-steps me-1"></i>Level-wise Matrix
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-medium" id="user-wise-tab" data-bs-toggle="tab" data-bs-target="#user-wise-pane" type="button" role="tab">
                        <i class="bi bi-people me-1"></i>User-wise Earners Ledger
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-medium" id="plan-matrix-tab" data-bs-toggle="tab" data-bs-target="#plan-matrix-pane" type="button" role="tab">
                        <i class="bi bi-table me-1"></i>Plan Payout Matrix
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            {{-- Tab 1: Level-Wise Breakdown --}}
            <div class="tab-pane fade show active" id="level-wise-pane" role="tabpanel">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h2 class="h6 mb-0 fw-semibold">Actual level-wise income distribution</h2>
                            <span class="text-body-tertiary small">Distribution recorded in cycle ledger entries between {{ $from->format('d M Y') }} and {{ $to->format('d M Y') }}</span>
                        </div>
                        <a href="{{ route('admin.reports.levels.csv', request()->query()) }}" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th class="ps-3">Level</th>
                                    <th>Relationship / Generation</th>
                                    <th>Rate (%)</th>
                                    <th class="text-end">Direct Sponsor</th>
                                    <th class="text-end">Matching Cascade</th>
                                    <th class="text-end">Total Distributed</th>
                                    <th class="text-center">Earners</th>
                                    <th class="text-center">Entries</th>
                                    <th class="text-end pe-3" style="min-width: 130px;">Share of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data['level_rows'] as $row)
                                    <tr class="{{ $row['is_active_depth'] ? '' : 'table-warning bg-opacity-25' }}">
                                        <td class="ps-3">
                                            <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                                                Level {{ $row['level'] }}
                                            </span>
                                            @unless ($row['is_active_depth'])
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" title="Paid out while cascade depth was configured deeper">
                                                    Beyond current depth
                                                </span>
                                            @endunless
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $row['label'] }}</div>
                                            <div class="text-body-tertiary small" style="font-size: .72rem;">
                                                @if ($row['level'] === 1)
                                                    Level 1 (Direct Referral &amp; 1st Match)
                                                @else
                                                    Generation {{ $row['level'] }} binary placement ancestor
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @if ($row['level'] === 1)
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">{{ $row['sponsor_rate'] }}% Sponsor</span>
                                                <span class="badge border" style="background:#ede9fe; color:#6d28d9; border-color:#ddd6fe !important;">{{ $row['matching_rate'] }}% Match</span>
                                            @else
                                                <span class="badge border" style="background:#ede9fe; color:#6d28d9; border-color:#ddd6fe !important;">{{ $row['matching_rate'] }}% Match</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-info-emphasis fw-medium">
                                            ₹{{ number_format($row['sponsor_paid'], 2) }}
                                        </td>
                                        <td class="text-end fw-medium" style="color: #6d28d9;">
                                            ₹{{ number_format($row['matching_paid'], 2) }}
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            ₹{{ number_format($row['total_paid'], 2) }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">{{ $row['unique_earners'] }}</span>
                                        </td>
                                        <td class="text-center text-secondary small">
                                            {{ $row['entries_count'] }}
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ $row['share_pct'] }}%"></div>
                                                </div>
                                                <span class="small fw-semibold text-secondary">{{ $row['share_pct'] }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-body-tertiary py-5">No level income posted in this date range.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td colspan="3" class="ps-3 py-3">Total Level-Wise Distribution</td>
                                    <td class="text-end text-info-emphasis py-3">₹{{ number_format($data['summary']['total_sponsor'], 2) }}</td>
                                    <td class="text-end py-3" style="color: #6d28d9;">₹{{ number_format($data['summary']['total_matching'], 2) }}</td>
                                    <td class="text-end text-primary py-3 fs-6">₹{{ number_format($data['summary']['total_level_income'], 2) }}</td>
                                    <td colspan="2" class="py-3"></td>
                                    <td class="text-end pe-3 py-3">{{ $data['summary']['total_distributed'] > 0 ? round(($data['summary']['total_level_income'] / $data['summary']['total_distributed']) * 100, 1) : 0 }}%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Tab 2: User-Wise Earners Ledger --}}
            <div class="tab-pane fade" id="user-wise-pane" role="tabpanel">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h2 class="h6 mb-0 fw-semibold">User-wise income breakdown</h2>
                            <span class="text-body-tertiary small">
                                Showing {{ $data['earners']->firstItem() ?? 0 }}–{{ $data['earners']->lastItem() ?? 0 }} of {{ $data['earners']->total() }} earner accounts with income in selected range
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm" style="max-width: 240px;">
                                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                                <input class="form-control" placeholder="Filter by member or code…" data-table-filter="#earnersTable">
                            </div>
                            <a href="{{ route('admin.reports.levels.earners.csv', request()->query()) }}" class="btn btn-sm btn-outline-success text-nowrap">
                                <i class="bi bi-download me-1"></i>Export CSV
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="earnersTable" data-sortable>
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th class="ps-3" data-sort-key="text">Member</th>
                                    <th class="text-center">Tree Depth</th>
                                    <th class="text-end" data-sort-key="number">Self</th>
                                    <th class="text-end" data-sort-key="number">Sponsor</th>
                                    <th class="text-end" data-sort-key="number">Matching</th>
                                    <th class="text-end" data-sort-key="number">Rank</th>
                                    <th class="text-end" data-sort-key="number">Total Income</th>
                                    <th class="text-center pe-3">Entries</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data['earners'] as $row)
                                    <tr>
                                        <td class="ps-3 fw-medium">
                                            @if ($row['member'])
                                                <a href="{{ route('admin.members.levels', $row['member']) }}" class="link-dark text-decoration-none fw-semibold">
                                                    {{ $row['member']->name }}
                                                </a>
                                                <span class="font-monospace text-body-tertiary small d-block">{{ $row['member']->member_code }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border" title="Generations below root — context only">Gen {{ $row['depth'] }}</span>
                                        </td>
                                        <td class="text-end text-success" data-sort-value="{{ $row['self_income'] }}">₹{{ number_format($row['self_income'], 2) }}</td>
                                        <td class="text-end text-info-emphasis" data-sort-value="{{ $row['sponsor_income'] }}">₹{{ number_format($row['sponsor_income'], 2) }}</td>
                                        <td class="text-end" style="color: #6d28d9;" data-sort-value="{{ $row['matching_income'] }}">₹{{ number_format($row['matching_income'], 2) }}</td>
                                        <td class="text-end text-warning-emphasis" data-sort-value="{{ $row['rank_income'] }}">₹{{ number_format($row['rank_income'], 2) }}</td>
                                        <td class="text-end fw-bold text-primary" data-sort-value="{{ $row['total_income'] }}">₹{{ number_format($row['total_income'], 2) }}</td>
                                        <td class="text-center pe-3 text-secondary small">{{ $row['entries'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-body-tertiary py-5">No earners in this date range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($data['earners']->hasPages())
                    <div class="card-footer bg-white border-top py-3">
                        {{ $data['earners']->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>

            {{-- Tab 3: Plan Payout Matrix Simulation --}}
            <div class="tab-pane fade" id="plan-matrix-pane" role="tabpanel">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Plan-by-plan level distribution matrix</h2>
                            <span class="text-body-tertiary small">Exact rupee payouts distributed per level when a plan is purchased and matched across the tree</span>
                        </div>
                        <div class="d-flex gap-3">
                            <a href="{{ route('admin.settings.edit') }}" class="small link-primary text-decoration-none"><i class="bi bi-sliders me-1"></i>Configure level %</a>
                            <a href="{{ route('admin.plans.index') }}" class="small link-primary text-decoration-none">Manage plans &rarr;</a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle text-center mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th class="text-start ps-3">Plan Bundle</th>
                                    <th>Price</th>
                                    <th>BV Pool</th>
                                    <th>Buyer Self ({{ $data['rates']['self_pct'] }}%)</th>
                                    <th>Sponsor L1 ({{ $data['rates']['sponsor_pct'] }}%)</th>
                                    @for ($i = 1; $i <= $data['rates']['cascade_depth']; $i++)
                                        <th class="text-nowrap" style="color: #6d28d9;">L{{ $i }} ({{ $data['rates']['level_pcts'][$i] ?? $data['rates']['matching_pct'] }}%)</th>
                                    @endfor
                                    <th class="table-primary text-primary">Max Cascade ({{ $data['rates']['max_cascade_pct'] }}%)</th>
                                    <th class="table-success text-success pe-3">Total Payout ({{ $data['rates']['self_pct'] + $data['rates']['sponsor_pct'] + $data['rates']['max_cascade_pct'] }}%)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                @foreach ($data['plans'] as $p)
                                    <tr>
                                        <td class="text-start ps-3 fw-bold text-dark">
                                            {{ $p['plan']->name }}
                                        </td>
                                        <td class="text-body-secondary">₹{{ number_format($p['plan']->price, 0) }}</td>
                                        <td class="fw-semibold text-primary">₹{{ number_format($p['plan']->bv, 0) }}</td>
                                        <td class="text-success fw-medium">₹{{ number_format($p['self_amount'], 0) }}</td>
                                        <td class="text-info-emphasis fw-medium">₹{{ number_format($p['sponsor_amount'], 0) }}</td>
                                        @for ($i = 1; $i <= $data['rates']['cascade_depth']; $i++)
                                            <td style="color: #6d28d9;">₹{{ number_format($p['level_amounts'][$i] ?? $p['matching_per_level'], 0) }}</td>
                                        @endfor
                                        <td class="table-primary fw-bold text-primary">₹{{ number_format($p['total_cascade'], 0) }}</td>
                                        <td class="table-success fw-bold text-success pe-3">₹{{ number_format($p['max_payout'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 bg-light rounded-2 mt-3 small text-body-secondary">
                        <i class="bi bi-info-circle me-1"></i>
                        Direct Sponsor pays once on order settlement. Matching cascade pays each qualified ancestor in the placement branch (up to depth {{ $data['rates']['cascade_depth'] }}) each time volume matches left/right.
                        Payouts are capped at ₹{{ number_format($data['rates']['monthly_cap'], 0) }}/mo and ₹{{ number_format($data['rates']['weekly_cap'], 0) }}/wk per partner.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection