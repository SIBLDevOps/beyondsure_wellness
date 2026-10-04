@extends('layouts.admin')

@section('title', 'Member Profile — ' . $member->name)

@section('content')
    @php
        $leftBv = (float) ($member->legTotal?->left_bv ?? 0);
        $rightBv = (float) ($member->legTotal?->right_bv ?? 0);
        $matchedBv = (float) ($member->legTotal?->matched_bv ?? 0);
        $isActiveMonth = $member->isActiveThisMonth();
        $incomeTypes = [
            'self' => ['label' => 'Self Purchase Income', 'badge' => 'success', 'icon' => 'bi-person-check'],
            'sponsor' => ['label' => 'Direct Sponsor Income (L1)', 'badge' => 'info', 'icon' => 'bi-person-plus'],
            'matching' => ['label' => 'Binary Matching Cascade', 'badge' => 'primary', 'icon' => 'bi-diagram-3'],
            'rank' => ['label' => 'Rank Pool Royalty', 'badge' => 'warning', 'icon' => 'bi-award'],
        ];
    @endphp

    {{-- Top Member Identity & Module Navigation Bar --}}
    <div class="card border mb-4">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar-circle bg-primary fs-6">{{ strtoupper(substr($member->name, 0, 2)) }}</span>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h2 class="h5 mb-0 fw-bold">{{ $member->name }}</h2>
                            <span class="badge bg-primary-subtle text-primary font-monospace">{{ $member->member_code }}</span>
                            <span class="badge rounded-pill text-capitalize {{ $member->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                {{ $member->status }}
                            </span>
                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis text-capitalize">
                                <i class="bi bi-award me-1"></i>{{ $member->rank ?: 'none' }}
                            </span>
                            @if ($isActiveMonth)
                                <span class="badge rounded-pill bg-success text-white"><i class="bi bi-check-circle me-1"></i>Active This Month</span>
                            @endif
                        </div>
                        <div class="text-secondary small mt-1">
                            <i class="bi bi-telephone me-1"></i>{{ $member->phone }}
                            @if ($member->email)
                                <span class="mx-2">·</span><i class="bi bi-envelope me-1"></i>{{ $member->email }}
                            @endif
                            <span class="mx-2">·</span>Joined {{ $member->created_at?->format('d M Y') }}
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.members.show', $member) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-person-lines-fill me-1"></i>Overview &amp; Profile
                    </a>
                    <a href="{{ route('admin.members.network', $member) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-diagram-3 me-1"></i>Full network
                    </a>
                    <a href="{{ route('admin.members.levels', $member) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-bar-chart-steps me-1"></i>Level illustration
                    </a>
                    <a href="{{ route('admin.members.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>All Members
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- 4 KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary small">Wallet balance</span>
                        <span class="stat-icon bg-success-subtle text-success" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-wallet2"></i></span>
                    </div>
                    <div class="fs-4 fw-bold text-success">₹{{ number_format($walletBalance, 2) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">Available ledger balance</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary small">Total Income Earned</span>
                        <span class="stat-icon bg-primary-subtle text-primary" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-graph-up-arrow"></i></span>
                    </div>
                    <div class="fs-4 fw-bold text-primary">₹{{ number_format($totalEarned ?? 0, 2) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">All compensation streams</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary small">Personal Purchases &amp; BV</span>
                        <span class="stat-icon bg-info-subtle text-info-emphasis" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-bag-check"></i></span>
                    </div>
                    <div class="fs-4 fw-bold">₹{{ number_format($totalSpent ?? 0, 0) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">Personal BV: <strong>₹{{ number_format($personalBv ?? 0, 0) }}</strong></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-secondary small">Binary Team &amp; Matched BV</span>
                        <span class="stat-icon bg-warning-subtle text-warning-emphasis" style="width:32px;height:32px;font-size:.9rem;"><i class="bi bi-diagram-3"></i></span>
                    </div>
                    <div class="fs-4 fw-bold">₹{{ number_format($matchedBv, 0) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">L: ₹{{ number_format($leftBv, 0) }} · R: ₹{{ number_format($rightBv, 0) }} ({{ $subtreeSize ?? 0 }} downline)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Left Column: Member Details & Upline Table --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Account, Upline &amp; Payout Details</h2>
                    <span class="badge bg-light text-secondary border">ID #{{ $member->id }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal" style="width: 38%;">Member Code</th>
                                <td class="pe-3 py-2 font-monospace fw-semibold">{{ $member->member_code }}</td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal">Phone / Email</th>
                                <td class="pe-3 py-2">{{ $member->phone }} {{ $member->email ? '· '.$member->email : '' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal">Account Status</th>
                                <td class="pe-3 py-2 text-capitalize">
                                    <span class="badge rounded-pill {{ $member->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $member->status }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal">Current Rank</th>
                                <td class="pe-3 py-2 text-capitalize fw-medium">{{ $member->rank }}</td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal">Sponsor (Direct Introducer)</th>
                                <td class="pe-3 py-2">
                                    @if ($member->sponsor)
                                        <a href="{{ route('admin.members.show', $member->sponsor) }}" class="fw-medium text-decoration-none">{{ $member->sponsor->name }}</a>
                                        <span class="font-monospace text-body-tertiary small">({{ $member->sponsor->member_code }})</span>
                                    @else
                                        <span class="text-body-tertiary">— (Root Member)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal">Binary Placement Parent</th>
                                <td class="pe-3 py-2">
                                    @if ($member->placementParent)
                                        <a href="{{ route('admin.members.network', $member->placementParent) }}" class="fw-medium text-decoration-none">{{ $member->placementParent->name }}</a>
                                        <span class="badge {{ $member->position === 'left' ? 'bg-info-subtle text-info-emphasis' : 'bg-success-subtle text-success' }} text-uppercase ms-1">
                                            {{ $member->position }} Leg
                                        </span>
                                    @else
                                        <span class="text-body-tertiary">— {{ $member->position ? "({$member->position})" : '' }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal">Bank / Payout KYC</th>
                                <td class="pe-3 py-2">
                                    @if ($member->hasBankDetails())
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Configured</span>
                                        <span class="small text-secondary ms-1">{{ $member->bank_name }} ({{ $member->bank_ifsc }})</span>
                                    @elseif ($member->upi_id)
                                        <span class="badge bg-info-subtle text-info-emphasis">UPI: {{ $member->upi_id }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Not provided yet</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal border-bottom-0">Wallet Balance</th>
                                <td class="pe-3 py-2 fw-bold text-success border-bottom-0">₹{{ number_format($walletBalance, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column: Income Breakdown by Compensation Stream --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-success"></i>Income Breakdown by Stream</h2>
                    <a href="{{ route('admin.members.levels', $member) }}" class="small text-primary text-decoration-none fw-medium">
                        Level-wise report &rarr;
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th class="ps-3">Income Stream</th>
                                    <th class="text-center">Entries</th>
                                    <th class="text-end">Total Earned</th>
                                    <th class="text-end pe-3">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($incomeTypes as $key => $meta)
                                    @php
                                        $stat = ($incomeByType ?? collect())->get($key);
                                        $amt = (float) ($stat->total ?? 0);
                                        $cnt = (int) ($stat->entries ?? 0);
                                        $share = ($totalEarned ?? 0) > 0 ? round(($amt / $totalEarned) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge bg-{{ $meta['badge'] }}-subtle text-{{ $meta['badge'] }} me-2">
                                                <i class="bi {{ $meta['icon'] }}"></i>
                                            </span>
                                            <span class="fw-medium small">{{ $meta['label'] }}</span>
                                        </td>
                                        <td class="text-center small text-secondary">{{ $cnt }}</td>
                                        <td class="text-end fw-semibold">₹{{ number_format($amt, 2) }}</td>
                                        <td class="text-end pe-3 small text-secondary">{{ $share }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td class="ps-3">Total Gross Earnings</td>
                                    <td class="text-center">{{ ($incomeByType ?? collect())->sum('entries') }}</td>
                                    <td class="text-end text-primary">₹{{ number_format($totalEarned ?? 0, 2) }}</td>
                                    <td class="text-end pe-3">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Recent Orders Table --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Recent orders</h2>
                    <span class="badge bg-light text-secondary border">{{ $orders->count() }} shown</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Order Code</th>
                                <th>Bundle / Plan</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">BV</th>
                                <th class="text-center">Status</th>
                                <th class="pe-3 text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td class="ps-3 font-monospace small">{{ $order->order_code }}</td>
                                    <td class="fw-medium">{{ $order->plan->name }}</td>
                                    <td class="text-end">₹{{ number_format($order->amount, 0) }}</td>
                                    <td class="text-end text-primary fw-medium">₹{{ number_format($order->bv, 0) }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill text-capitalize {{ in_array($order->status, ['paid', 'settled']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end text-body-tertiary small">{{ $order->created_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-body-tertiary py-4 text-center">No orders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Ledger Entries Table --}}
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Recent Ledger &amp; Payout Statement</h2>
                    <span class="badge bg-light text-secondary border">Latest {{ ($recentLedger ?? collect())->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Type</th>
                                <th>Description / Source</th>
                                <th class="text-end">Amount</th>
                                <th class="pe-3 text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLedger ?? [] as $entry)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge rounded-pill text-capitalize {{ $entry->amount >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                            {{ $entry->type }}
                                        </span>
                                    </td>
                                    <td class="small">
                                        <div>{{ $entry->description }}</div>
                                        @if ($entry->sourceMember && $entry->source_member_id !== $member->id)
                                            <div class="text-body-tertiary" style="font-size: .7rem;">
                                                Source: {{ $entry->sourceMember->name }} ({{ $entry->sourceMember->member_code }})
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold {{ $entry->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $entry->amount >= 0 ? '+' : '' }}₹{{ number_format($entry->amount, 2) }}
                                    </td>
                                    <td class="pe-3 text-end text-body-tertiary small">{{ $entry->created_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-body-tertiary py-4 text-center">No ledger entries recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection