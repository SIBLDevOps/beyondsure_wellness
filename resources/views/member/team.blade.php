@extends('layouts.member')

@section('title', 'My Direct Team')

@section('content')
    @include('member.partials.network-nav')

    <p class="text-secondary mb-3">
        Your direct sponsored members (personally introduced by you). Every direct referral's verified purchase earns you Direct Sponsor Income (L1) and contributes BV to your binary leg.
    </p>

    {{-- Summary Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="text-secondary small">Direct Sponsored Members</div>
                        <div class="fs-4 fw-bold">{{ $directs->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-person-check"></i></div>
                    <div>
                        <div class="text-secondary small">Active This Month</div>
                        <div class="fs-4 fw-bold text-success">{{ $directs->where('active_this_month', true)->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-stack"></i></div>
                    <div>
                        <div class="text-secondary small">Total Direct Personal BV</div>
                        <div class="fs-4 fw-bold text-primary">₹{{ number_format($directs->sum('personal_bv'), 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-people-fill me-2 text-success"></i>Direct Referrals Directory</h2>
                <div class="text-body-tertiary small">Members who registered using your sponsor code</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 210px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-secondary"></i></span>
                    <input type="text" class="form-control" placeholder="Filter directs…" data-table-filter="#directTeamTable">
                </div>
                <span class="badge bg-light text-secondary border">{{ $directs->count() }} directs</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="directTeamTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Member</th>
                        <th>Placement Leg</th>
                        <th>Account Status</th>
                        <th>Monthly Activity</th>
                        <th class="text-center">Their Directs</th>
                        <th class="text-end">Personal BV</th>
                        <th class="text-end pe-3">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($directs as $d)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $d->name }}</div>
                                <div class="font-monospace text-secondary small">{{ $d->member_code }}</div>
                            </td>
                            <td>
                                @if ($d->position)
                                    <span class="badge {{ $d->position === 'left' ? 'bg-info-subtle text-info-emphasis' : 'bg-success-subtle text-success' }} text-uppercase">
                                        {{ $d->position }} Leg
                                    </span>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge rounded-pill {{ $d->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">
                                    {{ $d->status }}
                                </span>
                            </td>
                            <td>
                                @if ($d->active_this_month ?? false)
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Ordered this month</span>
                                @else
                                    <span class="text-body-tertiary small">No order this month</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $d->sponsored_members_count ?? 0 }}</span>
                            </td>
                            <td class="text-end fw-medium">₹{{ number_format($d->personal_bv ?? 0, 0) }}</td>
                            <td class="text-end text-body-tertiary small pe-3">{{ $d->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-tertiary py-4">No direct sponsored members yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
