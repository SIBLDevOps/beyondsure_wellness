@extends('layouts.admin')

@section('title', 'Payments')

@section('content')
    <p class="text-secondary mb-3" style="max-width: 680px;">
        Manual bank transfer / UPI / cash payments need your review before the order counts as paid.
        <strong>Razorpay</strong> payments are verified automatically by the gateway and never need action here
        — they show up already verified.
    </p>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card me-2 text-warning"></i>Payment Submissions &amp; Verification Queue</h2>
                <div class="text-body-tertiary small">
                    Showing {{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }} payments
                </div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 280px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control" placeholder="Filter payments on this page…" data-table-filter="#paymentsTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="paymentsTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Order Code</th>
                        <th data-sort-key="text">Member</th>
                        <th class="text-end" data-sort-key="number">Amount</th>
                        <th data-sort-key="text">Method</th>
                        <th>Reference / UTR</th>
                        <th class="text-center" data-sort-key="text">Status</th>
                        <th>Submitted</th>
                        <th class="text-end pe-3">Verification Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $statusColor = match ($payment->status) {
                                'verified' => 'success',
                                'rejected' => 'danger',
                                default => 'warning',
                            };
                        @endphp
                        <tr>
                            <td class="ps-3 font-monospace small fw-semibold">{{ $payment->order->order_code }}</td>
                            <td>
                                <a href="{{ route('admin.members.show', $payment->order->member) }}" class="fw-medium text-dark text-decoration-none d-block">
                                    {{ $payment->order->member->name }}
                                </a>
                                <span class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $payment->order->member->member_code }}</span>
                            </td>
                            <td class="text-end fw-semibold" data-sort-value="{{ $payment->order->amount ?? 0 }}">
                                ₹{{ number_format($payment->order->amount ?? 0, 0) }}
                            </td>
                            <td class="text-capitalize">
                                <span class="badge bg-light text-dark border">
                                    {{ str_replace('_', ' ', $payment->method) }}
                                </span>
                                @if ($payment->method === 'razorpay')
                                    <span class="badge bg-primary-subtle text-primary ms-1">auto</span>
                                @endif
                            </td>
                            <td class="font-monospace small">{{ $payment->reference ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-{{ $statusColor }}-subtle text-{{ $statusColor }} text-capitalize">
                                    {{ $payment->status }}
                                </span>
                            </td>
                            <td class="text-body-tertiary small">{{ $payment->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end pe-3">
                                @if ($payment->status === 'pending')
                                    <div class="d-inline-flex gap-1 justify-content-end">
                                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Verify</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-body-tertiary small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-tertiary py-5">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $payments->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection