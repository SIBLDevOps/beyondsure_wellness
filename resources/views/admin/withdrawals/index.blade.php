@extends('layouts.admin')

@section('title', 'Withdrawals')

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <p class="text-secondary mb-0" style="max-width: 680px;">
            <strong>pending</strong> → member requested, needs approve/reject. <strong>approved</strong> → ready to
            pay, needs a reference. <strong>paid</strong> → money moved, debited from their wallet. Single-withdrawal
            actions below always work — for paying several at once on a schedule, use
            <a href="{{ route('admin.dispatch.index') }}" class="link-primary fw-medium text-decoration-none">Dispatch batches</a> instead.
        </p>
        <a href="{{ route('admin.dispatch.index') }}" class="btn btn-outline-primary flex-shrink-0">
            <i class="bi bi-truck me-1"></i>Dispatch Batches
        </a>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-success"></i>Member Withdrawal Requests</h2>
                <div class="text-body-tertiary small">
                    Showing {{ $withdrawals->firstItem() ?? 0 }}–{{ $withdrawals->lastItem() ?? 0 }} of {{ $withdrawals->total() }} requests
                </div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 280px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control" placeholder="Filter withdrawals…" data-table-filter="#withdrawalsTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="withdrawalsTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Member</th>
                        <th class="text-end" data-sort-key="number">Amount</th>
                        <th>Bank / UPI Destination</th>
                        <th class="text-center" data-sort-key="text">Status</th>
                        <th>Payout Ref / UTR</th>
                        <th>Requested</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawals as $w)
                        @php
                            $statusColor = match ($w->status) {
                                'paid' => 'success',
                                'approved' => 'info',
                                'rejected' => 'danger',
                                default => 'warning',
                            };
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('admin.members.show', $w->member) }}" class="fw-semibold text-dark text-decoration-none d-block">
                                    {{ $w->member->name }}
                                </a>
                                <span class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $w->member->member_code }}</span>
                            </td>
                            <td class="text-end fw-bold" data-sort-value="{{ $w->amount }}">₹{{ number_format($w->amount, 2) }}</td>
                            <td class="small">
                                @if ($w->bank_account_number)
                                    <div class="fw-medium">{{ $w->bank_name }} · <span class="font-monospace">{{ $w->bank_account_number }}</span></div>
                                    <div class="text-body-tertiary" style="font-size: .72rem;">
                                        {{ $w->bank_account_name }} · IFSC: <span class="font-monospace">{{ $w->bank_ifsc }}</span>
                                        @if ($w->upi_id) · UPI: {{ $w->upi_id }} @endif
                                    </div>
                                @elseif ($w->upi_id)
                                    <div class="fw-medium">UPI: {{ $w->upi_id }}</div>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-{{ $statusColor }}-subtle text-{{ $statusColor }} text-capitalize">
                                    {{ $w->status }}
                                </span>
                            </td>
                            <td class="font-monospace small">{{ $w->payout_reference ?? '—' }}</td>
                            <td class="text-body-tertiary small">{{ $w->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                @if ($w->status === 'pending')
                                    <div class="d-inline-flex gap-1 justify-content-end">
                                        <form method="POST" action="{{ route('admin.withdrawals.approve', $w) }}">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    </div>
                                @elseif ($w->status === 'approved')
                                    <form method="POST" action="{{ route('admin.withdrawals.mark-paid', $w) }}" class="d-inline-flex gap-1 justify-content-end align-items-center">
                                        @csrf @method('PUT')
                                        <input name="payout_reference" placeholder="Enter UTR / Ref" required class="form-control form-control-sm font-monospace" style="width: 145px;">
                                        <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-send-check me-1"></i>Mark paid</button>
                                    </form>
                                @else
                                    <span class="text-body-tertiary small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-tertiary py-5">No withdrawals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($withdrawals->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $withdrawals->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection