@extends('layouts.admin')

@section('title', 'Refunds')

@section('content')
    @if ($setupNeeded ?? false)
        @include('partials.setup-needed', ['what' => 'Refunds'])
    @else
        <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
            <div style="max-width: 640px;">
                <h2 class="h5 fw-bold mb-1">Refunds</h2>
                <p class="text-secondary small mb-0">
                    Every paid order that was cancelled and refunded, with its credit note and how the money went back.
                    To refund an order, open <a href="{{ route('admin.orders.index') }}" class="link-primary">Orders</a> and press <strong>Refund</strong> on a paid order.
                    An order whose income was already paid out in a cycle cannot be refunded here.
                </p>
            </div>
            <div class="border rounded-3 px-3 py-2 bg-body text-end">
                <div class="small text-secondary">Total refunded</div>
                <div class="fw-bold fs-5">₹{{ number_format($total, 2) }}</div>
            </div>
        </div>

        <div class="card border">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr><th class="ps-3">Date</th><th>Credit note</th><th>Customer</th><th>Order</th><th class="text-end">Amount</th><th>Sent back by</th><th>Reason</th><th class="pe-3"></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($notes as $n)
                            <tr>
                                <td class="ps-3 small text-nowrap">{{ $n->issued_on->format('d M Y') }}</td>
                                <td class="small font-monospace">{{ $n->number }}</td>
                                <td class="small">{{ $n->order->member->name }}<div class="text-secondary">{{ $n->order->member->phone }}</div></td>
                                <td class="small font-monospace">{{ $n->order->order_code }}</td>
                                <td class="text-end fw-semibold">₹{{ number_format($n->amount, 2) }}</td>
                                <td class="small">{{ $n->methodLabel() }}@if ($n->refund_reference)<div class="text-secondary font-monospace">{{ $n->refund_reference }}</div>@endif</td>
                                <td class="small text-secondary" style="max-width: 240px;">{{ $n->reason }}</td>
                                <td class="pe-3 text-end"><a href="{{ route('admin.orders.credit-note', $n->order) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-body-tertiary py-5">No refunds yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($notes->hasPages())<div class="card-footer bg-white">{{ $notes->links('pagination::bootstrap-5') }}</div>@endif
        </div>
    @endif
@endsection
