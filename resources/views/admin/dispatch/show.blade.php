@extends('layouts.admin')

@section('title', 'Batch — ' . $batch->scheduled_for->format('d M Y'))

@section('content')
    @php $isEarly = $batch->isDraft() && $batch->scheduled_for->isFuture(); @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="text-secondary small">
            Review withdrawal items in this dispatch batch, enter UTR / payout references, and release when transfers are complete.
        </div>
        <a href="{{ route('admin.dispatch.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Dispatch Batches
        </a>
    </div>

    {{-- Batch KPI Summary Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small">Scheduled for</div>
                    <div class="fs-5 fw-bold">{{ $batch->scheduled_for->format('d M Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small">Batch Status</div>
                    <div class="mt-1">
                        <span class="badge rounded-pill {{ $batch->isDraft() ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }} text-capitalize fs-6">
                            {{ $batch->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small">Withdrawals in Batch</div>
                    <div class="fs-4 fw-bold">{{ $batch->withdrawals->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small">Total Batch Amount</div>
                    <div class="fs-4 fw-bold text-primary">₹{{ number_format($batch->total_amount, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($batch->isDraft())
        {{-- Unified Draft Batch Items & Release Form Table --}}
        <div class="card border mb-4">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-truck me-2 text-success"></i>Items in This Batch &amp; Payout References</h2>
                    <div class="text-body-tertiary small">Enter the bank UTR / payout reference for each item before releasing</div>
                </div>
                <span class="badge bg-primary-subtle text-primary">{{ $batch->withdrawals->count() }} items</span>
            </div>

            <form method="POST" action="{{ route('admin.dispatch.release', $batch) }}" id="releaseBatchForm"
                onsubmit="return confirm('Release this batch? Every withdrawal will be marked paid immediately — this is the moment money moves.')">
                @csrf @method('PUT')

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th class="text-end">Amount</th>
                                <th>Bank Account &amp; IFSC</th>
                                <th style="width: 260px;">UTR / Payout Reference <span class="text-danger">*</span></th>
                                <th class="text-end pe-3">Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batch->withdrawals as $w)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold">{{ $w->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $w->member->member_code }}</div>
                                    </td>
                                    <td class="text-end fw-bold">₹{{ number_format($w->amount, 2) }}</td>
                                    <td class="small">
                                        <div class="fw-medium">{{ $w->bank_name }} · <span class="font-monospace">{{ $w->bank_account_number }}</span></div>
                                        <div class="text-body-tertiary" style="font-size: .72rem;">
                                            {{ $w->bank_account_name }} · IFSC: <span class="font-monospace">{{ $w->bank_ifsc }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <input name="reference[{{ $w->id }}]" placeholder="UTR / payout ref" required class="form-control form-control-sm font-monospace">
                                    </td>
                                    <td class="text-end pe-3">
                                        <button type="submit" form="remove-item-{{ $w->id }}" class="btn btn-sm btn-outline-danger" title="Remove from batch">
                                            <i class="bi bi-x-lg"></i> Remove
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-tertiary py-4">No withdrawals in this batch.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($batch->withdrawals->isNotEmpty())
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td class="ps-3">Total Batch Payout</td>
                                    <td class="text-end text-primary">₹{{ number_format($batch->total_amount, 2) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                @if ($batch->withdrawals->isNotEmpty())
                    <div class="card-footer bg-white border-top py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        @if ($isEarly)
                            <div class="form-check mb-0">
                                <input type="checkbox" required class="form-check-input" id="early-confirm">
                                <label class="form-check-label small text-warning-emphasis fw-medium" for="early-confirm">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Scheduled date is in the future — I'm releasing early on purpose.
                                </label>
                            </div>
                        @else
                            <div class="small text-secondary">All items require a valid UTR reference before release.</div>
                        @endif

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-send-check-fill me-1"></i>Release batch — pay everyone now
                        </button>
                    </div>
                @endif
            </form>

            {{-- Hidden Remove Forms outside main release form --}}
            @foreach ($batch->withdrawals as $w)
                <form id="remove-item-{{ $w->id }}" method="POST" action="{{ route('admin.dispatch.remove-item', [$batch, $w]) }}" class="d-none">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        </div>

        {{-- Other Approved Withdrawals Available to Add --}}
        @if ($available->isNotEmpty())
            <div class="card border">
                <div class="card-header bg-light-subtle border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-truck me-2 text-primary"></i>Other approved withdrawals not yet in this batch</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th class="text-end">Amount</th>
                                <th>Bank Destination</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($available as $w)
                                <tr>
                                    <td class="ps-3 fw-medium">{{ $w->member->name }}</td>
                                    <td class="text-end fw-semibold">₹{{ number_format($w->amount, 2) }}</td>
                                    <td class="small text-secondary">{{ $w->bank_name }} •••{{ substr($w->bank_account_number ?? '', -4) }}</td>
                                    <td class="text-end pe-3">
                                        <form method="POST" action="{{ route('admin.dispatch.add-item', $batch) }}">
                                            @csrf
                                            <input type="hidden" name="withdrawal_id" value="{{ $w->id }}">
                                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-plus-lg me-1"></i>Add to batch</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @else
        {{-- Released Batch Table --}}
        <div class="card border">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-truck me-2 text-primary"></i>Released — {{ $batch->released_at->format('d M Y H:i') }}</h2>
                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Dispatched</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-3">Member</th>
                            <th class="text-end">Amount</th>
                            <th>Paid to Bank Account</th>
                            <th class="pe-3">UTR / Payout Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batch->withdrawals as $w)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $w->member->name }}</td>
                                <td class="text-end fw-bold text-success">₹{{ number_format($w->amount, 2) }}</td>
                                <td class="small text-secondary">{{ $w->bank_name }} •••{{ substr($w->bank_account_number ?? '', -4) }} ({{ $w->bank_ifsc }})</td>
                                <td class="font-monospace small pe-3">{{ $w->payout_reference }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection