@extends('layouts.admin')

@section('title', 'Compliance / audit summary')

@section('content')
    @include('partials.reports-nav')

    <p class="text-secondary mb-4" style="max-width: 680px;">
        A single screen you can hand to an auditor: proves the ledger reconciles, flags any cycle that ever
        paid more than it should have, and lists any plan that's ever run over its cost-cap target.
    </p>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-shield-check me-2 text-success"></i>Distributed vs. Withdrawn, by Month</h2>
                <div class="text-body-tertiary small">Reconciled directly against the ledger, not recalculated from orders — showing {{ $data['by_month']->firstItem() ?? 0 }}–{{ $data['by_month']->lastItem() ?? 0 }} of {{ $data['by_month']->total() }} months</div>
            </div>
            <a href="{{ route('admin.reports.compliance.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Distributed</th>
                        <th class="text-end">Withdrawn</th>
                        <th class="text-end">Net Retained in Wallets</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['by_month'] as $row)
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $row->month }}</td>
                            <td class="text-end fw-medium text-success">₹{{ number_format($row->distributed, 0) }}</td>
                            <td class="text-end fw-medium text-secondary">₹{{ number_format($row->withdrawn, 0) }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($row->distributed - $row->withdrawn, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-4">No ledger activity in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['by_month']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['by_month']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-exclamation-octagon me-2 text-primary"></i>Overpaid Cycles (Should Always Be Zero)</h2>
                <div class="text-body-tertiary small">Cycles where total compensation paid exceeded the available BV pool — showing {{ $data['overpaid_cycles']->firstItem() ?? 0 }}–{{ $data['overpaid_cycles']->lastItem() ?? 0 }} of {{ $data['overpaid_cycles']->total() }} cycles</div>
            </div>
            <a href="{{ route('admin.reports.compliance.overpaid.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        @if ($data['overpaid_cycles']->isEmpty())
            <div class="card-body">
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>No cycle has ever paid out more than its pool.</div>
            </div>
        @else
            <div class="card-body pb-2">
                <div class="text-danger fw-medium small"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $data['overpaid_cycles']->total() }} cycle(s) paid more than their pool — flag for review.</div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th>Period</th>
                            <th class="text-end">Pool BV</th>
                            <th class="text-end">Total Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['overpaid_cycles'] as $c)
                            <tr>
                                <td class="fw-medium">{{ $c->period_start->format('d M') }} – {{ $c->period_end->format('d M Y') }}</td>
                                <td class="text-end">₹{{ number_format($c->pool_bv, 0) }}</td>
                                <td class="text-end text-danger fw-semibold">₹{{ number_format($c->total_paid, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($data['overpaid_cycles']->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $data['overpaid_cycles']->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-warning"></i>Plan Bundles Exceeding the Cost Cap</h2>
                <div class="text-body-tertiary small">Active plans whose product cost exceeds the target cost-of-goods cap — showing {{ $data['cost_cap_breaches']->firstItem() ?? 0 }}–{{ $data['cost_cap_breaches']->lastItem() ?? 0 }} of {{ $data['cost_cap_breaches']->total() }} plans</div>
            </div>
            <a href="{{ route('admin.reports.compliance.cost-cap.csv', request()->query()) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        @if ($data['cost_cap_breaches']->isEmpty())
            <div class="card-body">
                <div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>No active plan currently exceeds its cost-cap target.</div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th>Plan</th>
                            <th class="text-end">Bundle Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['cost_cap_breaches'] as $plan)
                            <tr>
                                <td class="fw-semibold">{{ $plan->name }}</td>
                                <td class="text-end text-danger fw-semibold">₹{{ number_format($plan->costForQty(), 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($data['cost_cap_breaches']->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $data['cost_cap_breaches']->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection
