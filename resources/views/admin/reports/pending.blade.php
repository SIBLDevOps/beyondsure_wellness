@extends('layouts.admin')

@section('title', 'Pending & cash')

@section('content')
    @include('partials.reports-nav')

    <p class="text-secondary mb-4" style="max-width: 680px;">
        Everything currently waiting on an admin action: payments to verify, withdrawals to dispatch, and paid
        orders not yet settled into a cycle. Nothing here moves money by itself.
    </p>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card-2-front me-2 text-warning"></i>Payments Awaiting Verification</h2>
                <div class="text-body-tertiary small">
                    Manual bank transfer / UPI / cash submissions — go to
                    <a href="{{ route('admin.payments.index') }}" class="link-primary fw-medium text-decoration-none">Payments</a> to verify or reject
                    (showing {{ $data['pending_payments']->firstItem() ?? 0 }}–{{ $data['pending_payments']->lastItem() ?? 0 }} of {{ $data['pending_payments']->total() }}).
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @foreach ($data['aging_buckets'] as $bucket => $count)
                    <span class="badge rounded-pill {{ str_starts_with($bucket, '8+') ? 'bg-danger-subtle text-danger' : (str_starts_with($bucket, '3-7') ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary') }}">
                        {{ $bucket }}: {{ $count }}
                    </span>
                @endforeach
                <a href="{{ route('admin.reports.pending.csv') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Order</th>
                        <th>Member</th>
                        <th>Method</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['pending_payments'] as $p)
                        <tr>
                            <td class="font-monospace small fw-semibold">{{ $p->order->order_code }}</td>
                            <td class="fw-medium">{{ $p->order->member->name }}</td>
                            <td><span class="badge bg-light text-secondary border text-capitalize">{{ str_replace('_', ' ', $p->method) }}</span></td>
                            <td class="text-body-tertiary small">{{ $p->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-4">Nothing pending.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['pending_payments']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['pending_payments']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-send-check me-2 text-info"></i>Withdrawals Awaiting Dispatch</h2>
                <div class="text-body-tertiary small">
                    Approved but not yet paid — bundle these into a
                    <a href="{{ route('admin.dispatch.index') }}" class="link-primary fw-medium text-decoration-none">dispatch batch</a> to pay them out together
                    (showing {{ $data['withdrawals_awaiting_dispatch']->firstItem() ?? 0 }}–{{ $data['withdrawals_awaiting_dispatch']->lastItem() ?? 0 }} of {{ $data['withdrawals_awaiting_dispatch']->total() }}).
                </div>
            </div>
            <a href="{{ route('admin.reports.pending.withdrawals.csv') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Member</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['withdrawals_awaiting_dispatch'] as $w)
                        <tr>
                            <td class="fw-medium">{{ $w->member->name }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($w->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-body-tertiary py-4">None.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['withdrawals_awaiting_dispatch']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['withdrawals_awaiting_dispatch']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-primary"></i>Paid Orders Not Yet in a Cycle</h2>
                <div class="text-body-tertiary small">
                    Verified payments waiting to be settled — approve a
                    <a href="{{ route('admin.cycles.index') }}" class="link-primary fw-medium text-decoration-none">cycle</a> to post their compensation
                    (showing {{ $data['unsettled_orders']->firstItem() ?? 0 }}–{{ $data['unsettled_orders']->lastItem() ?? 0 }} of {{ $data['unsettled_orders']->total() }}).
                </div>
            </div>
            <a href="{{ route('admin.reports.pending.unsettled.csv') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Order</th>
                        <th>Member</th>
                        <th>Plan</th>
                        <th class="text-end">BV</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['unsettled_orders'] as $o)
                        <tr>
                            <td class="font-monospace small fw-semibold">{{ $o->order_code }}</td>
                            <td class="fw-medium">{{ $o->member->name }}</td>
                            <td>{{ $o->plan->name }}</td>
                            <td class="text-end fw-medium">₹{{ number_format($o->bv, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-4">None.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($data['unsettled_orders']->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $data['unsettled_orders']->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
