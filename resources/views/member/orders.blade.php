@extends('layouts.member')

@section('title', 'My Orders')

@section('content')
    @php
        $confirmedOrders = $orders->whereIn('status', ['paid', 'settled']);
        $pendingOrders = $orders->where('status', 'pending_payment');
        $totalOrderSpent = $confirmedOrders->sum('amount');
        $totalOrderBv = $confirmedOrders->sum('bv');
    @endphp

    {{-- 4-Card Order Summary Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-bag-check"></i></div>
                    <div>
                        <div class="text-secondary small">Total Orders</div>
                        <div class="fs-5 fw-bold">{{ $orders->count() }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">{{ $confirmedOrders->count() }} confirmed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="text-secondary small">Awaiting Payment</div>
                        <div class="fs-5 fw-bold {{ $pendingOrders->count() > 0 ? 'text-warning-emphasis' : '' }}">{{ $pendingOrders->count() }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Pending checkout</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-currency-rupee"></i></div>
                    <div>
                        <div class="text-secondary small">Total Purchased</div>
                        <div class="fs-5 fw-bold">₹{{ number_format($totalOrderSpent, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Paid &amp; settled</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-stack"></i></div>
                    <div>
                        <div class="text-secondary small">Personal BV Earned</div>
                        <div class="fs-5 fw-bold text-success">₹{{ number_format($totalOrderBv, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Pool basis volume</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-success"></i>Order History</h2>
                <div class="text-body-tertiary small">All plan bundle orders placed under your member account</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 190px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-secondary"></i></span>
                    <input type="text" class="form-control" placeholder="Search orders…" data-table-filter="#memberOrdersTable">
                </div>
                <a href="{{ route('member.plans.index') }}" class="btn btn-sm btn-success"><i class="bi bi-cart-plus me-1"></i>New Order</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="memberOrdersTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Order Code</th>
                        <th data-sort-key="text">Plan Bundle</th>
                        <th data-sort-key="number" class="text-end">Amount</th>
                        <th data-sort-key="number" class="text-end">BV</th>
                        <th>Payment</th>
                        <th class="text-center">Order Status</th>
                        <th>Placed On</th>
                        <th class="text-end pe-3">Action</th>
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
                            <td class="fw-medium">{{ $order->plan->name }}</td>
                            <td class="text-end fw-semibold" data-sort-value="{{ $order->amount }}">₹{{ number_format($order->amount, 0) }}</td>
                            <td class="text-end text-secondary" data-sort-value="{{ $order->bv }}">₹{{ number_format($order->bv, 0) }}</td>
                            <td class="small">
                                @if ($order->payment)
                                    <span class="text-capitalize">{{ str_replace('_', ' ', $order->payment->method) }}</span>
                                    <span class="badge bg-light text-secondary border ms-1 text-capitalize">{{ $order->payment->status }}</span>
                                @else
                                    <span class="text-body-tertiary">Not submitted</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-{{ $statusColor }}-subtle text-{{ $statusColor === 'warning' || $statusColor === 'info' ? $statusColor.'-emphasis' : $statusColor }}">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </td>
                            <td class="text-body-tertiary small">{{ $order->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                @if ($order->status === 'pending_payment')
                                    <a href="{{ route('member.orders.pay', $order) }}" class="btn btn-sm btn-success"><i class="bi bi-credit-card me-1"></i>Pay now</a>
                                @else
                                    <span class="text-body-tertiary small"><i class="bi bi-check2-circle text-success me-1"></i>Confirmed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-tertiary py-4">No orders yet — <a href="{{ route('member.plans.index') }}" class="link-success">browse plans</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
