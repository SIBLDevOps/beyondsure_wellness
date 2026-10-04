@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
    <p class="text-secondary mb-3" style="max-width: 680px;">
        Every plan purchase, in order. <strong>pending_payment</strong> → member hasn't paid yet.
        <strong>paid</strong> → payment verified, waiting for a
        <a href="{{ route('admin.cycles.index') }}" class="link-primary fw-medium text-decoration-none">cycle</a> to post
        compensation. <strong>settled</strong> → compensation has been paid out for this order.
    </p>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-primary"></i>Member Orders Ledger</h2>
                <div class="text-body-tertiary small">
                    Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
                </div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 280px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control" placeholder="Filter orders on this page…" data-table-filter="#ordersTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="ordersTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Order Code</th>
                        <th data-sort-key="text">Member</th>
                        <th data-sort-key="text">Plan Bundle</th>
                        <th class="text-end" data-sort-key="number">Amount</th>
                        <th class="text-end" data-sort-key="number">BV</th>
                        <th>Payment</th>
                        <th class="text-center" data-sort-key="text">Order Status</th>
                        <th data-sort-key="text">Date</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $statusColor = match ($order->status) {
                                'settled' => 'success',
                                'paid' => 'info',
                                'cancelled' => 'danger',
                                default => 'warning',
                            };
                        @endphp
                        <tr>
                            <td class="ps-3 font-monospace small fw-semibold">{{ $order->order_code }}</td>
                            <td>
                                <a href="{{ route('admin.members.show', $order->member) }}" class="fw-medium text-dark text-decoration-none d-block">
                                    {{ $order->member->name }}
                                </a>
                                <span class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $order->member->member_code }}</span>
                            </td>
                            <td class="fw-medium">{{ $order->plan->name }}</td>
                            <td class="text-end fw-semibold" data-sort-value="{{ $order->amount }}">
                                ₹{{ number_format($order->amount, 0) }}
                                <div class="text-body-tertiary fw-normal" style="font-size: .7rem;">GST ₹{{ number_format($order->gstAmount(), 0) }}</div>
                            </td>
                            <td class="text-end text-primary fw-medium" data-sort-value="{{ $order->bv }}">₹{{ number_format($order->bv, 0) }}</td>
                            <td>
                                @if ($order->payment)
                                    <span class="badge bg-light text-secondary border text-capitalize">{{ $order->payment->status }}</span>
                                @else
                                    <span class="text-body-tertiary small">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </td>
                            <td class="text-body-tertiary small">{{ $order->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                @if ($order->status === 'pending_payment')
                                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                                    </form>
                                @else
                                    <span class="text-body-tertiary small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-body-tertiary py-5">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $orders->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection