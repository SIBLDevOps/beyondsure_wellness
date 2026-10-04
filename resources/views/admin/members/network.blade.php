@extends('layouts.admin')

@section('title', 'Network — ' . $member->name)

@section('content')
    @php
        $leftBv = (float) ($legTotal->left_bv ?? 0);
        $rightBv = (float) ($legTotal->right_bv ?? 0);
        $matchedBv = (float) ($legTotal->matched_bv ?? 0);
        $totalSourcedIncome = (float) $sourcedLedger->sum('amount');
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
                        </div>
                        <div class="text-secondary small mt-1">
                            Binary Genealogy Tree, Upline Lineage, and Subtree Performance Report
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.members.show', $member) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-person-lines-fill me-1"></i>Overview &amp; Profile
                    </a>
                    <a href="{{ route('admin.members.network', $member) }}" class="btn btn-sm btn-primary">
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

    {{-- 4 Network KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-diagram-3-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Subtree size</div>
                        <div class="fs-4 fw-bold lh-1 mt-1">{{ $subtreeSize }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Total nodes in subtree</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-person-check-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Active this month</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-success">{{ $activeCount }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Ordered in {{ now()->format('M Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-arrows-expand"></i></div>
                    <div>
                        <div class="text-secondary small">Binary Leg BV (L / R)</div>
                        <div class="fs-5 fw-bold lh-1 mt-1">₹{{ number_format($leftBv, 0) }} / ₹{{ number_format($rightBv, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Matched: ₹{{ number_format($matchedBv, 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-coin"></i></div>
                    <div>
                        <div class="text-secondary small">Subtree Sourced Income</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-primary">₹{{ number_format($totalSourcedIncome, 2) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">From {{ $sourcedLedger->count() }} entries</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Upline Lineage Breadcrumb Cards (Sponsor Chain vs Placement Chain) --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-person-plus text-info me-1"></i>Sponsor chain</h2>
                        <span class="badge bg-info-subtle text-info-emphasis">{{ count($sponsorChain) }} Upline</span>
                    </div>
                    <p class="text-body-tertiary small mb-2">Who personally introduced whom, regardless of tree placement.</p>
                    <div class="d-flex align-items-center flex-wrap gap-1 pt-1">
                        <span class="badge bg-primary text-white py-1 px-2">
                            {{ $member->name }} (this member)
                        </span>
                        @foreach ($sponsorChain as $s)
                            <i class="bi bi-arrow-right text-body-tertiary small"></i>
                            <a href="{{ route('admin.members.network', $s) }}" class="badge bg-light text-dark border text-decoration-none py-1 px-2">
                                ↑ {{ $s->name }} <span class="font-monospace text-secondary">({{ $s->member_code }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-diagram-2 text-primary me-1"></i>Placement chain</h2>
                        <span class="badge bg-primary-subtle text-primary">{{ count($placementChain) }} Ancestors</span>
                    </div>
                    <p class="text-body-tertiary small mb-2">Binary-tree ancestry (may differ from sponsor chain).</p>
                    <div class="d-flex align-items-center flex-wrap gap-1 pt-1">
                        <span class="badge bg-primary text-white py-1 px-2">
                            {{ $member->name }} (this member)
                        </span>
                        @foreach ($placementChain as $p)
                            <i class="bi bi-arrow-right text-body-tertiary small"></i>
                            <a href="{{ route('admin.members.network', $p) }}" class="badge bg-light text-dark border text-decoration-none py-1 px-2">
                                ↑ {{ $p->name }} <span class="font-monospace text-secondary">({{ $p->member_code }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Visual Binary Genealogy Tree + Leg Balance + Per-Generation Breakdown --}}
    @include('admin.partials.downline-core')

    {{-- Subtree Orders & Subtree Sourced Income Tables --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Orders placed by anyone in this subtree</h2>
                        <div class="text-body-tertiary small">Latest 50 bundle purchases across this downline</div>
                    </div>
                    <span class="badge bg-light text-secondary border">{{ $subtreeOrders->count() }} orders</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" data-sortable>
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3" data-sort-key="text">Member</th>
                                <th data-sort-key="text">Plan</th>
                                <th class="text-end" data-sort-key="number">Amount</th>
                                <th class="text-end" data-sort-key="number">BV</th>
                                <th class="text-center">Status</th>
                                <th class="pe-3 text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subtreeOrders as $order)
                                <tr>
                                    <td class="ps-3">
                                        <a href="{{ route('admin.members.show', $order->member) }}" class="fw-medium text-dark text-decoration-none">
                                            {{ $order->member->name }}
                                        </a>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $order->member->member_code }}</div>
                                    </td>
                                    <td>{{ $order->plan->name }}</td>
                                    <td class="text-end" data-sort-value="{{ $order->amount }}">₹{{ number_format($order->amount, 0) }}</td>
                                    <td class="text-end text-primary fw-medium" data-sort-value="{{ $order->bv }}">₹{{ number_format($order->bv, 0) }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill text-capitalize {{ in_array($order->status, ['paid', 'settled']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end text-body-tertiary small">{{ $order->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-tertiary py-4">No orders in this subtree yet.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($subtreeOrders->isNotEmpty())
                            <tfoot class="table-light fw-semibold small">
                                <tr>
                                    <td colspan="2" class="ps-3">Total (Shown Orders)</td>
                                    <td class="text-end">₹{{ number_format($subtreeOrders->sum('amount'), 0) }}</td>
                                    <td class="text-end text-primary">₹{{ number_format($subtreeOrders->sum('bv'), 0) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-success"></i>Income this member earned from this subtree</h2>
                        <div class="text-body-tertiary small">Sponsor, matching, and self payouts credited to {{ $member->name }}</div>
                    </div>
                    <span class="badge bg-success-subtle text-success">₹{{ number_format($totalSourcedIncome, 2) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" data-sortable>
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3" data-sort-key="text">Source member</th>
                                <th data-sort-key="text">Type</th>
                                <th>Description</th>
                                <th class="text-end" data-sort-key="number">Amount</th>
                                <th class="pe-3 text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sourcedLedger as $entry)
                                <tr>
                                    <td class="ps-3">
                                        @if ($entry->sourceMember)
                                            <a href="{{ route('admin.members.show', $entry->sourceMember) }}" class="fw-medium text-dark text-decoration-none">
                                                {{ $entry->sourceMember->name }}
                                            </a>
                                            <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $entry->sourceMember->member_code }}</div>
                                        @else
                                            <span class="text-body-tertiary">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill text-capitalize bg-primary-subtle text-primary">
                                            {{ $entry->type }}
                                        </span>
                                    </td>
                                    <td class="small text-secondary">{{ $entry->description }}</td>
                                    <td class="text-end fw-semibold text-success" data-sort-value="{{ $entry->amount }}">₹{{ number_format($entry->amount, 2) }}</td>
                                    <td class="pe-3 text-end text-body-tertiary small">{{ $entry->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-tertiary py-4">No income sourced from this subtree yet.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($sourcedLedger->isNotEmpty())
                            <tfoot class="table-light fw-semibold small">
                                <tr>
                                    <td colspan="3" class="ps-3">Total Sourced Income</td>
                                    <td class="text-end text-success">₹{{ number_format($totalSourcedIncome, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection