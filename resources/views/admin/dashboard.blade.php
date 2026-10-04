@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1 fw-bold text-dark">Platform Operations &amp; Command Center</h2>
            <p class="text-secondary small mb-0">
                Live snapshot of membership activity, pending financial actions, and compensation cycles.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.cycles.index') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-play-circle me-1"></i>Run Cycle
            </a>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-layers me-1"></i>Plans
            </a>
        </div>
    </div>

    {{-- Operations Pipeline --}}
    <div class="card border mb-4">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="small fw-semibold text-secondary">
                    <i class="bi bi-diagram-3-fill text-primary me-1"></i>Administrative Financial Flow (Deliberate 5-Step Order)
                </div>
                <span class="badge bg-light text-secondary border d-none d-md-inline" style="font-size: .7rem;">Nothing runs without your verification</span>
            </div>
            <div class="d-flex align-items-center gap-2 overflow-auto py-1">
                {{-- Step 1 --}}
                <div class="d-flex align-items-center gap-2 bg-light-subtle border rounded-3 px-3 py-2 flex-shrink-0">
                    <span class="badge rounded-circle bg-secondary text-white" style="width:20px;height:20px;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;">1</span>
                    <span class="small fw-medium">Member Pays</span>
                </div>
                <i class="bi bi-arrow-right text-muted flex-shrink-0"></i>

                {{-- Step 2 --}}
                <a href="{{ route('admin.payments.index') }}" class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 flex-shrink-0 text-decoration-none {{ $pendingPayments > 0 ? 'bg-warning-subtle border-warning-subtle text-warning-emphasis' : 'bg-light-subtle text-dark' }}">
                    <span class="badge rounded-circle {{ $pendingPayments > 0 ? 'bg-warning text-dark' : 'bg-secondary text-white' }}" style="width:20px;height:20px;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;">2</span>
                    <span class="small fw-semibold">Verify Payment</span>
                    @if ($pendingPayments > 0)
                        <span class="badge bg-danger rounded-pill">{{ $pendingPayments }}</span>
                    @endif
                </a>
                <i class="bi bi-arrow-right text-muted flex-shrink-0"></i>

                {{-- Step 3 --}}
                <a href="{{ route('admin.cycles.index') }}" class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 flex-shrink-0 text-decoration-none {{ $unsettledOrders > 0 ? 'bg-primary-subtle border-primary-subtle text-primary' : 'bg-light-subtle text-dark' }}">
                    <span class="badge rounded-circle {{ $unsettledOrders > 0 ? 'bg-primary text-white' : 'bg-secondary text-white' }}" style="width:20px;height:20px;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;">3</span>
                    <span class="small fw-semibold">Approve Cycle</span>
                    @if ($unsettledOrders > 0)
                        <span class="badge bg-primary rounded-pill">{{ $unsettledOrders }}</span>
                    @endif
                </a>
                <i class="bi bi-arrow-right text-muted flex-shrink-0"></i>

                {{-- Step 4 --}}
                <a href="{{ route('admin.withdrawals.index') }}" class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 flex-shrink-0 text-decoration-none {{ $pendingWithdrawals > 0 ? 'bg-danger-subtle border-danger-subtle text-danger' : 'bg-light-subtle text-dark' }}">
                    <span class="badge rounded-circle {{ $pendingWithdrawals > 0 ? 'bg-danger text-white' : 'bg-secondary text-white' }}" style="width:20px;height:20px;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;">4</span>
                    <span class="small fw-semibold">Review Withdrawal</span>
                    @if ($pendingWithdrawals > 0)
                        <span class="badge bg-danger rounded-pill">{{ $pendingWithdrawals }}</span>
                    @endif
                </a>
                <i class="bi bi-arrow-right text-muted flex-shrink-0"></i>

                {{-- Step 5 --}}
                <a href="{{ route('admin.dispatch.index') }}" class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 flex-shrink-0 text-decoration-none {{ $withdrawalsToProcess > 0 ? 'bg-success-subtle border-success-subtle text-success' : 'bg-light-subtle text-dark' }}">
                    <span class="badge rounded-circle {{ $withdrawalsToProcess > 0 ? 'bg-success text-white' : 'bg-secondary text-white' }}" style="width:20px;height:20px;font-size:.7rem;display:inline-flex;align-items:center;justify-content:center;">5</span>
                    <span class="small fw-semibold">Dispatch Payout</span>
                    @if ($withdrawalsToProcess > 0)
                        <span class="badge bg-success rounded-pill">{{ $withdrawalsToProcess }}</span>
                    @endif
                </a>
            </div>
        </div>
    </div>

    {{-- Urgent Actions / Attention Queue Strip --}}
    <div class="row g-3 mb-4">
        {{-- Payments --}}
        <div class="col-6 col-lg-3">
            <div class="card border h-100 hover-lift {{ $pendingPayments > 0 ? 'border-warning-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon {{ $pendingPayments > 0 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-secondary' }}">
                            <i class="bi bi-credit-card-2-front"></i>
                        </div>
                        @if ($pendingPayments > 0)
                            <span class="badge bg-warning text-dark rounded-pill">Action needed</span>
                        @else
                            <span class="badge bg-light text-success border"><i class="bi bi-check-lg"></i> Clear</span>
                        @endif
                    </div>
                    <div class="text-secondary small">Payments to Verify</div>
                    <div class="fs-4 fw-bold {{ $pendingPayments > 0 ? 'text-warning-emphasis' : 'text-dark' }}">{{ $pendingPayments }}</div>
                    <a href="{{ route('admin.payments.index') }}" class="small fw-medium link-primary text-decoration-none mt-1 d-inline-block">
                        Review submissions &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Unsettled Orders --}}
        <div class="col-6 col-lg-3">
            <div class="card border h-100 hover-lift {{ $unsettledOrders > 0 ? 'border-primary-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon {{ $unsettledOrders > 0 ? 'bg-primary-subtle text-primary' : 'bg-light text-secondary' }}">
                            <i class="bi bi-bag-check"></i>
                        </div>
                        @if ($unsettledOrders > 0)
                            <span class="badge bg-primary text-white rounded-pill">Ready for cycle</span>
                        @else
                            <span class="badge bg-light text-success border"><i class="bi bi-check-lg"></i> Settled</span>
                        @endif
                    </div>
                    <div class="text-secondary small">Orders Awaiting Cycle</div>
                    <div class="fs-4 fw-bold {{ $unsettledOrders > 0 ? 'text-primary' : 'text-dark' }}">{{ $unsettledOrders }}</div>
                    <a href="{{ route('admin.cycles.index') }}" class="small fw-medium link-primary text-decoration-none mt-1 d-inline-block">
                        Open cycle manager &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Pending Withdrawals --}}
        <div class="col-6 col-lg-3">
            <div class="card border h-100 hover-lift {{ $pendingWithdrawals > 0 ? 'border-danger-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon {{ $pendingWithdrawals > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-secondary' }}">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        @if ($pendingWithdrawals > 0)
                            <span class="badge bg-danger text-white rounded-pill">Review needed</span>
                        @else
                            <span class="badge bg-light text-success border"><i class="bi bi-check-lg"></i> None</span>
                        @endif
                    </div>
                    <div class="text-secondary small">Pending Withdrawals</div>
                    <div class="fs-4 fw-bold {{ $pendingWithdrawals > 0 ? 'text-danger' : 'text-dark' }}">{{ $pendingWithdrawals }}</div>
                    <a href="{{ route('admin.withdrawals.index') }}" class="small fw-medium link-primary text-decoration-none mt-1 d-inline-block">
                        Approve requests &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Payout Dispatch --}}
        <div class="col-6 col-lg-3">
            <div class="card border h-100 hover-lift {{ $withdrawalsToProcess > 0 ? 'border-success-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon {{ $withdrawalsToProcess > 0 ? 'bg-success-subtle text-success' : 'bg-light text-secondary' }}">
                            <i class="bi bi-truck"></i>
                        </div>
                        @if ($withdrawalsToProcess > 0)
                            <span class="badge bg-success text-white rounded-pill">Ready to dispatch</span>
                        @else
                            <span class="badge bg-light text-secondary border">Queue idle</span>
                        @endif
                    </div>
                    <div class="text-secondary small">Ready for Payout Batch</div>
                    <div class="fs-4 fw-bold {{ $withdrawalsToProcess > 0 ? 'text-success' : 'text-dark' }}">{{ $withdrawalsToProcess }}</div>
                    <a href="{{ route('admin.dispatch.index') }}" class="small fw-medium link-primary text-decoration-none mt-1 d-inline-block">
                        Generate payout batch &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Performance & Financial Metrics Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Total Platform Members</div>
                        <div class="fs-4 fw-bold lh-1 mt-1">{{ number_format($memberCount) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">{{ number_format($activeMemberCount) }} active (made &ge;1 purchase)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="text-secondary small">Commissions Posted Today</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-success">₹{{ number_format($todayIncome, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Credits posted via cycles</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="text-secondary small">Commissions This Month</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-info-emphasis">₹{{ number_format($monthIncome, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">All compensation streams combined</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tables Row 1: Recent Orders & Payments Verification Queue --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-primary"></i>Recent Plan Purchases</h2>
                        <div class="text-body-tertiary small">Latest orders placed by network partners</div>
                    </div>
                    <a href="{{ route('admin.orders.index') }}" class="small link-primary text-decoration-none">View all &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th>Plan Bundle</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentOrders as $order)
                                @php
                                    $statusColor = match ($order->status) {
                                        'settled' => 'success',
                                        'paid' => 'info',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark">{{ $order->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $order->member->member_code }}</div>
                                    </td>
                                    <td>{{ $order->plan->name }}</td>
                                    <td class="text-end fw-semibold">₹{{ number_format($order->amount, 0) }}</td>
                                    <td class="text-center pe-3">
                                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} rounded-pill text-capitalize">
                                            {{ str_replace('_', ' ', $order->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-4">No recent orders.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card-2-front me-2 text-warning"></i>Payments Awaiting Verification</h2>
                        <div class="text-body-tertiary small">Bank/UPI receipts requiring manual confirmation</div>
                    </div>
                    <a href="{{ route('admin.payments.index') }}" class="small link-primary text-decoration-none">Review queue &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th>Order</th>
                                <th>Method</th>
                                <th class="pe-3">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentPayments as $payment)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-medium text-dark">{{ $payment->order->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $payment->order->member->member_code }}</div>
                                    </td>
                                    <td class="font-monospace small text-primary">{{ $payment->order->order_code }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-capitalize">
                                            {{ str_replace('_', ' ', $payment->method) }}
                                        </span>
                                    </td>
                                    <td class="text-body-tertiary small pe-3">{{ $payment->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-tertiary py-4">
                                        <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                                        All payments are currently verified.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Tables Row 2: Recent Withdrawals & Core Navigation Shortcuts --}}
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bank me-2 text-success"></i>Recent Withdrawal Activity</h2>
                        <div class="text-body-tertiary small">Latest member requests &amp; dispatched batches</div>
                    </div>
                    <a href="{{ route('admin.withdrawals.index') }}" class="small link-primary text-decoration-none">Manage payouts &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="pe-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentWithdrawals as $w)
                                @php
                                    $statusColor = match ($w->status) {
                                        'paid' => 'success',
                                        'approved' => 'info',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-medium text-dark">{{ $w->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $w->member->member_code }}</div>
                                    </td>
                                    <td class="text-end fw-semibold">₹{{ number_format($w->amount, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} rounded-pill text-capitalize">
                                            {{ $w->status }}
                                        </span>
                                    </td>
                                    <td class="text-body-tertiary small pe-3">{{ $w->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-4">No recent withdrawals.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-compass me-2 text-primary"></i>Core Administration Modules</h2>
                    <div class="text-body-tertiary small">Quick shortcuts to platform configuration &amp; reporting</div>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-between gap-2">
                    <a href="{{ route('admin.plans.index') }}" class="d-flex align-items-center justify-content-between p-3 bg-white border rounded-3 text-decoration-none hover-lift">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="stat-icon bg-primary-subtle text-primary flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;">
                                <i class="bi bi-layers"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark small mb-0.5">Plans &amp; BV Matrix</div>
                                <div class="text-body-tertiary" style="font-size: .75rem;">Configure product bundles, cost caps &amp; BV payouts</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-secondary small ms-2 flex-shrink-0"></i>
                    </a>

                    <a href="{{ route('admin.reports.levels') }}" class="d-flex align-items-center justify-content-between p-3 bg-white border rounded-3 text-decoration-none hover-lift">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="stat-icon bg-success-subtle text-success flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;">
                                <i class="bi bi-bar-chart-steps"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark small mb-0.5">Level-Wise Distribution</div>
                                <div class="text-body-tertiary" style="font-size: .75rem;">Generation tree breakdown &amp; cascade analysis</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-secondary small ms-2 flex-shrink-0"></i>
                    </a>

                    <a href="{{ route('admin.settings.edit') }}" class="d-flex align-items-center justify-content-between p-3 bg-white border rounded-3 text-decoration-none hover-lift">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="stat-icon bg-warning-subtle text-warning-emphasis flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark small mb-0.5">Compensation Rules</div>
                                <div class="text-body-tertiary" style="font-size: .75rem;">Live percentages, caps &amp; level commission math</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-secondary small ms-2 flex-shrink-0"></i>
                    </a>

                    <a href="{{ route('admin.site-content.edit') }}" class="d-flex align-items-center justify-content-between p-3 bg-white border rounded-3 text-decoration-none hover-lift">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="stat-icon bg-info-subtle text-info-emphasis flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark small mb-0.5">Website Content</div>
                                <div class="text-body-tertiary" style="font-size: .75rem;">Edit public homepage banners, copy &amp; FAQs</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-secondary small ms-2 flex-shrink-0"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
