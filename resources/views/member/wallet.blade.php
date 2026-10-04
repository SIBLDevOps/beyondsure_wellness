@extends('layouts.member')

@section('title', 'Wallet & Payouts')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="text-secondary small">Available to Withdraw</div>
                        <div class="fs-4 fw-bold text-success">₹{{ number_format($availableBalance, 2) }}</div>
                        @if ($availableBalance < $balance)
                            <div class="text-body-tertiary" style="font-size: .72rem;">₹{{ number_format($balance, 2) }} total — ₹{{ number_format($balance - $availableBalance, 2) }} already reserved by pending/approved requests</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">Weekly Matching Cap Used</span>
                        <span class="fw-bold">{{ $weeklyPct }}%</span>
                    </div>
                    <div class="progress mt-2" style="height:.45rem;">
                        <div class="progress-bar bg-info" style="width: {{ min(100, $weeklyPct) }}%"></div>
                    </div>
                    <div class="text-body-tertiary mt-1" style="font-size: .72rem;">Based on your active plan's weekly cap</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">Monthly Matching Cap Used</span>
                        <span class="fw-bold">{{ $monthlyPct }}%</span>
                    </div>
                    <div class="progress mt-2" style="height:.45rem;">
                        <div class="progress-bar bg-primary" style="width: {{ min(100, $monthlyPct) }}%"></div>
                    </div>
                    <div class="text-body-tertiary mt-1" style="font-size: .72rem;">Based on your active plan's monthly cap</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Left Column: Request Withdrawal & Withdrawal History --}}
        <div class="col-lg-7">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Withdrawals</h2>
                        <div class="text-body-tertiary small">Request a payout from your wallet balance and track dispatch status</div>
                    </div>
                    @if ($member->hasBankDetails())
                        <div>
                            <form method="POST" action="{{ route('member.wallet.request') }}" class="d-flex align-items-center gap-2">
                                @csrf
                                <div class="input-group input-group-sm" style="width: 195px;">
                                    <span class="input-group-text bg-white">₹</span>
                                    <input name="amount" type="number" step="0.01" min="1" max="{{ $availableBalance }}" required
                                        value="{{ old('amount') }}"
                                        class="form-control @error('amount') is-invalid @enderror"
                                        placeholder="Max {{ number_format($availableBalance, 0) }}">
                                </div>
                                <button class="btn btn-success btn-sm flex-shrink-0"><i class="bi bi-send me-1"></i>Withdraw</button>
                            </form>
                            @error('amount')
                                <div class="text-danger small mt-1 text-end" style="font-size: .75rem;"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                </div>

                @if (! $member->hasBankDetails())
                    <div class="card-body py-2">
                        <div class="alert alert-warning py-2 small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Complete your Bank &amp; Payout Details on the right before requesting a withdrawal.</div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3 text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th>Payout Destination</th>
                                <th>UTR Reference</th>
                                <th class="pe-3">Requested</th>
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
                                    <td class="ps-3 text-end fw-semibold">₹{{ number_format($w->amount, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-{{ $statusColor }}-subtle text-{{ $statusColor === 'warning' || $statusColor === 'info' ? $statusColor.'-emphasis' : $statusColor }} text-capitalize">{{ $w->status }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $w->bank_name }} <span class="font-monospace">•••{{ substr($w->bank_account_number ?? '', -4) }}</span></td>
                                    <td class="font-monospace small">{{ $w->payout_reference ?? '—' }}</td>
                                    <td class="pe-3 text-body-tertiary small">{{ $w->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-tertiary py-4">No withdrawals requested yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column: Bank & UPI KYC Form --}}
        <div class="col-lg-5">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bank me-2 text-success"></i>Bank &amp; Payout Details</h2>
                        <div class="text-body-tertiary small">Saved once and snapshotted on each withdrawal</div>
                    </div>
                    @if ($member->hasBankDetails())
                        <span class="badge bg-success-subtle text-success rounded-pill"><i class="bi bi-check-lg me-1"></i>On file</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">Required</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('member.wallet.bank-details') }}">
                    @csrf @method('PUT')
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-medium small mb-1">Account Holder Name <span class="text-danger">*</span></label>
                                <input name="bank_account_name" value="{{ old('bank_account_name', $member->bank_account_name) }}" required
                                    class="form-control form-control-sm @error('bank_account_name') is-invalid @enderror">
                                @error('bank_account_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium small mb-1">Bank Name <span class="text-danger">*</span></label>
                                <input name="bank_name" value="{{ old('bank_name', $member->bank_name) }}" required
                                    class="form-control form-control-sm @error('bank_name') is-invalid @enderror">
                                @error('bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium small mb-1">IFSC Code <span class="text-danger">*</span></label>
                                <input name="bank_ifsc" value="{{ old('bank_ifsc', $member->bank_ifsc) }}" required
                                    pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" maxlength="11"
                                    class="form-control form-control-sm font-monospace text-uppercase @error('bank_ifsc') is-invalid @enderror" placeholder="e.g. HDFC0001234">
                                @error('bank_ifsc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-medium small mb-1">Account Number <span class="text-danger">*</span></label>
                                <input name="bank_account_number" value="{{ old('bank_account_number', $member->bank_account_number) }}" required inputmode="numeric"
                                    pattern="[0-9]{8,34}" minlength="8" maxlength="34"
                                    class="form-control form-control-sm font-monospace @error('bank_account_number') is-invalid @enderror">
                                @error('bank_account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-medium small mb-1">UPI ID <span class="text-body-tertiary fw-normal">(opt)</span></label>
                                <input name="upi_id" value="{{ old('upi_id', $member->upi_id) }}"
                                    class="form-control form-control-sm @error('upi_id') is-invalid @enderror" placeholder="name@upi">
                                @error('upi_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light-subtle border-top py-2 px-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Save bank details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-success"></i>Wallet Ledger Statement</h2>
                <div class="text-body-tertiary small">All compensation credits and withdrawal debits recorded on your account</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 200px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-secondary"></i></span>
                    <input type="text" class="form-control" placeholder="Filter statement…" data-table-filter="#walletLedgerTable">
                </div>
                <span class="badge bg-light text-secondary border">{{ $ledgerEntries->count() }} entries</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="walletLedgerTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Date</th>
                        <th data-sort-key="text">Type</th>
                        <th data-sort-key="text">Description</th>
                        <th data-sort-key="number" class="text-end pe-3">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php $typeColors = ['self' => 'success', 'sponsor' => 'info', 'matching' => 'primary', 'rank' => 'warning', 'withdrawal' => 'danger']; @endphp
                    @forelse ($ledgerEntries as $entry)
                        @php $c = $typeColors[$entry->type] ?? 'secondary'; @endphp
                        <tr>
                            <td class="ps-3 text-body-tertiary small text-nowrap">{{ $entry->created_at->format('d M Y') }}</td>
                            <td>
                                <span class="badge rounded-pill bg-{{ $c }}-subtle text-{{ $c === 'warning' || $c === 'info' ? $c.'-emphasis' : $c }} text-capitalize">{{ $entry->type }}</span>
                            </td>
                            <td class="text-secondary small">{{ $entry->description }}</td>
                            <td class="text-end pe-3 {{ $entry->amount < 0 ? 'text-danger' : 'text-success' }} fw-semibold" data-sort-value="{{ $entry->amount }}">
                                {{ $entry->amount >= 0 ? '+' : '' }}₹{{ number_format($entry->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-4">No ledger activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
