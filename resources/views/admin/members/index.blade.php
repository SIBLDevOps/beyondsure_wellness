@extends('layouts.admin')

@section('title', 'Members Directory')

@section('content')
    @php
        $avatarColors = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];
        $rankColors = [
            'none' => 'secondary',
            'silver' => 'secondary',
            'gold' => 'warning',
            'platinum' => 'info',
            'diamond' => 'primary',
        ];
        $stats = $stats ?? [
            'total' => $members->total(),
            'active' => 0,
            'inactive' => 0,
            'active_this_month' => 0,
            'ranked' => 0,
        ];
    @endphp

    {{-- Top KPI Summary Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Total Members</div>
                        <div class="fs-4 fw-bold lh-1 mt-1">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-person-check-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Active Status</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-success">{{ number_format($stats['active']) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">{{ number_format($stats['inactive']) }} inactive</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-bag-check-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Active This Month</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-info-emphasis">{{ number_format($stats['active_this_month']) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Ordered in {{ now()->format('M Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-award-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Ranked Achievers</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-warning-emphasis">{{ number_format($stats['ranked']) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Silver &amp; above</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="card border mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.members.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label small text-secondary mb-1">Search Member</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input name="search" value="{{ request('search') }}" placeholder="Name, member code or phone…" class="form-control">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-secondary mb-1">Account Status</label>
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-secondary mb-1">Rank</label>
                    <select name="rank" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Ranks</option>
                        @foreach (['none', 'silver', 'gold', 'platinum', 'diamond'] as $r)
                            <option value="{{ $r }}" @selected(request('rank') === $r)>{{ ucfirst($r) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-secondary mb-1">Monthly Activity</label>
                    <select name="activity" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Members</option>
                        <option value="active_month" @selected(request('activity') === 'active_month')>Ordered This Month</option>
                        <option value="inactive_month" @selected(request('activity') === 'inactive_month')>No Order This Month</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 col-lg-3 d-flex gap-2 justify-content-md-end">
                    <button class="btn btn-sm btn-primary px-3" type="submit">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if (request()->hasAny(['search', 'status', 'rank', 'activity']))
                        <a href="{{ route('admin.members.index') }}" class="btn btn-sm btn-outline-secondary">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Professional Members Table --}}
    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Registered Members &amp; Network Overview</h2>
                <div class="text-body-tertiary small">
                    Showing {{ $members->firstItem() ?? 0 }}–{{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 small text-secondary">
                <span class="d-inline-flex align-items-center gap-1"><span class="rounded-circle bg-success d-inline-block" style="width:8px;height:8px;"></span> Active this month</span>
                <span class="text-body-tertiary">|</span>
                <span class="d-inline-flex align-items-center gap-1"><span class="rounded-circle bg-secondary d-inline-block" style="width:8px;height:8px;"></span> No order this month</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Member</th>
                        <th data-sort-key="text">Contact</th>
                        <th data-sort-key="text">Sponsor &amp; Binary Placement</th>
                        <th data-sort-key="text">Status &amp; Rank</th>
                        <th class="text-center" data-sort-key="number">Directs</th>
                        <th data-sort-key="number">Binary Leg Volume (BV)</th>
                        <th class="text-end" data-sort-key="number">Personal BV</th>
                        <th class="text-end" data-sort-key="number">Wallet Balance</th>
                        <th class="text-end pe-3">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $color = $avatarColors[$member->id % count($avatarColors)];
                            $rankColor = $rankColors[strtolower($member->rank ?? 'none')] ?? 'secondary';
                            $leftBv = (float) ($member->legTotal?->left_bv ?? 0);
                            $rightBv = (float) ($member->legTotal?->right_bv ?? 0);
                            $matchedBv = (float) ($member->legTotal?->matched_bv ?? 0);
                            $personalBv = (float) ($member->personal_bv ?? 0);
                            $walletBal = (float) ($member->wallet_balance ?? 0);
                            $isActiveMonth = (bool) ($member->active_this_month ?? false);
                        @endphp
                        <tr>
                            {{-- Member Identity --}}
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="position-relative">
                                        <span class="avatar-circle avatar-circle-sm bg-{{ $color }}">
                                            {{ strtoupper(substr($member->name, 0, 2)) }}
                                        </span>
                                        <span class="position-absolute bottom-0 end-0 rounded-circle border border-white {{ $isActiveMonth ? 'bg-success' : 'bg-secondary' }}"
                                              style="width: 10px; height: 10px;"
                                              title="{{ $isActiveMonth ? 'Active this month' : 'No order this month' }}"></span>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.members.show', $member) }}" class="fw-semibold text-dark text-decoration-none d-block">
                                            {{ $member->name }}
                                        </a>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="font-monospace text-primary fw-medium" style="font-size: .75rem;">{{ $member->member_code }}</span>
                                            <span class="text-body-tertiary" style="font-size: .7rem;">· {{ $member->created_at?->format('d M Y') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Contact --}}
                            <td>
                                <div class="small fw-medium">{{ $member->phone }}</div>
                                @if ($member->email)
                                    <div class="text-body-tertiary text-truncate" style="font-size: .72rem; max-width: 150px;">{{ $member->email }}</div>
                                @endif
                            </td>

                            {{-- Sponsor & Placement --}}
                            <td>
                                <div class="small">
                                    <span class="text-body-tertiary">Sponsor:</span>
                                    @if ($member->sponsor)
                                        <a href="{{ route('admin.members.show', $member->sponsor) }}" class="text-decoration-none fw-medium text-dark">
                                            {{ $member->sponsor->name }}
                                        </a>
                                        <span class="font-monospace text-body-tertiary" style="font-size: .7rem;">({{ $member->sponsor->member_code }})</span>
                                    @else
                                        <span class="badge bg-dark-subtle text-dark" style="font-size: .68rem;">Root</span>
                                    @endif
                                </div>
                                <div class="small mt-1">
                                    <span class="text-body-tertiary">Placement:</span>
                                    @if ($member->placementParent)
                                        <span class="text-secondary">{{ $member->placementParent->name }}</span>
                                        <span class="badge {{ $member->position === 'left' ? 'bg-info-subtle text-info-emphasis' : 'bg-success-subtle text-success' }} text-uppercase" style="font-size: .65rem;">
                                            {{ $member->position }}
                                        </span>
                                    @else
                                        <span class="text-body-tertiary">—</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Status & Rank --}}
                            <td>
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    <span class="badge rounded-pill text-capitalize {{ $member->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $member->status }}
                                    </span>
                                    <span class="badge rounded-pill text-capitalize bg-{{ $rankColor }}-subtle text-{{ $rankColor }}">
                                        <i class="bi bi-award me-1"></i>{{ $member->rank ?: 'none' }}
                                    </span>
                                </div>
                                <div class="mt-1" style="font-size: .7rem;">
                                    @if ($isActiveMonth)
                                        <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Active this month</span>
                                    @else
                                        <span class="text-body-tertiary">No order this month</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Direct Referrals --}}
                            <td class="text-center" data-sort-value="{{ $member->sponsored_members_count ?? 0 }}">
                                <span class="badge bg-light text-dark border px-2 py-1 fw-semibold">
                                    {{ $member->sponsored_members_count ?? 0 }}
                                </span>
                            </td>

                            {{-- Binary Leg Volume --}}
                            <td data-sort-value="{{ $leftBv + $rightBv }}">
                                <div class="d-flex align-items-center gap-2 small">
                                    <span class="badge bg-info-subtle text-info-emphasis fw-medium" title="Left Leg BV">
                                        L: ₹{{ number_format($leftBv, 0) }}
                                    </span>
                                    <span class="badge bg-success-subtle text-success fw-medium" title="Right Leg BV">
                                        R: ₹{{ number_format($rightBv, 0) }}
                                    </span>
                                </div>
                                <div class="text-body-tertiary mt-1" style="font-size: .7rem;">
                                    Matched: <strong class="text-dark">₹{{ number_format($matchedBv, 0) }}</strong>
                                </div>
                            </td>

                            {{-- Personal BV & Orders --}}
                            <td class="text-end" data-sort-value="{{ $personalBv }}">
                                <div class="fw-semibold">₹{{ number_format($personalBv, 0) }}</div>
                                <div class="text-body-tertiary" style="font-size: .7rem;">
                                    {{ $member->paid_orders_count ?? 0 }} {{ Str::plural('order', $member->paid_orders_count ?? 0) }}
                                </div>
                            </td>

                            {{-- Wallet Balance --}}
                            <td class="text-end" data-sort-value="{{ $walletBal }}">
                                <span class="fw-bold {{ $walletBal > 0 ? 'text-success' : 'text-secondary' }}">
                                    ₹{{ number_format($walletBal, 2) }}
                                </span>
                            </td>

                            {{-- Quick Actions --}}
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.members.show', $member) }}" class="btn btn-outline-secondary" title="View Member Profile & Statement">
                                        <i class="bi bi-person-lines-fill"></i> <span class="d-none d-xl-inline">Profile</span>
                                    </a>
                                    <a href="{{ route('admin.members.network', $member) }}" class="btn btn-outline-primary" title="View Binary Genealogy Tree & Network">
                                        <i class="bi bi-diagram-3"></i> <span class="d-none d-xl-inline">Tree</span>
                                    </a>
                                    <a href="{{ route('admin.members.levels', $member) }}" class="btn btn-outline-primary" title="View Level-Wise Income Breakdown">
                                        <i class="bi bi-bar-chart-steps"></i> <span class="d-none d-xl-inline">Levels</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-body-tertiary py-5">
                                <i class="bi bi-people fs-2 d-block mb-2 opacity-50"></i>
                                No members match your search or filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($members->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $members->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection