@extends('layouts.admin')

@section('title', 'Cycles')

@section('content')
    <p class="text-secondary mb-3" style="max-width: 680px;">
        A cycle is the moment compensation actually gets calculated and posted to member wallets. The period
        dates are just a label for your records — approving settles <strong>every</strong> currently-paid,
        unsettled order regardless of when it was placed.
    </p>

    {{-- Compensation Lifecycle Visual Guide --}}
    <div class="card border border-primary-subtle bg-light mb-4 rounded-3">
        <div class="card-body p-3">
            <div class="small fw-semibold text-primary mb-2">
                <i class="bi bi-info-circle me-1"></i>How Compensation Cycles Work (Step-by-Step Flow)
            </div>
            <div class="row g-2 text-center small text-secondary">
                <div class="col-6 col-md-3">
                    <div class="p-2 bg-white rounded-2 border h-100">
                        <span class="badge bg-light text-primary border mb-1">1. Orders Paid</span>
                        <strong class="text-dark d-block small">Plan Purchases</strong>
                        <span style="font-size: .75rem;">Members buy wellness bundles.</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 bg-white rounded-2 border h-100">
                        <span class="badge bg-light text-warning-emphasis border mb-1">2. Verification</span>
                        <strong class="text-dark d-block small">Verify Payments</strong>
                        <span style="font-size: .75rem;">Check UPI/Bank references.</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 bg-white rounded-2 border h-100">
                        <span class="badge bg-light text-success border mb-1">3. Approve Cycle</span>
                        <strong class="text-dark d-block small">Run Calculations</strong>
                        <span style="font-size: .75rem;">Self, Sponsor, Matching &amp; Ranks.</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 bg-white rounded-2 border h-100">
                        <span class="badge bg-light text-info-emphasis border mb-1">4. Payouts</span>
                        <strong class="text-dark d-block small">Wallets Credited</strong>
                        <span style="font-size: .75rem;">Instant credit ready for dispatch.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Draft Cycle Form & Unsettled Queue Summary --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-plus-circle text-primary me-1"></i>Create &amp; Schedule Compensation Cycle</h2>
                <div class="text-body-tertiary small">
                    Approving a cycle runs Self, Sponsor, Level-Wise Matching, and Rank pool calculations on all unsettled paid orders.
                </div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-info-subtle text-info-emphasis px-3 py-2">
                    <strong>{{ $unsettledCount }}</strong> Paid Order(s) Waiting
                </span>
                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                    Unsettled Pool: <strong>₹{{ number_format($unsettledBv, 0) }} BV</strong>
                </span>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.cycles.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-12 col-sm-5 col-md-4">
                    <label class="form-label small fw-medium text-secondary mb-1">Period Start Date <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-calendar-event text-muted"></i></span>
                        <input type="date" name="period_start" value="{{ old('period_start', now()->startOfWeek()->format('Y-m-d')) }}" required
                            class="form-control @error('period_start') is-invalid @enderror">
                    </div>
                    @error('period_start') <div class="text-danger small mt-1" style="font-size: .75rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-5 col-md-4">
                    <label class="form-label small fw-medium text-secondary mb-1">Period End Date <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-calendar-check text-muted"></i></span>
                        <input type="date" name="period_end" value="{{ old('period_end', now()->endOfWeek()->format('Y-m-d')) }}" required
                            class="form-control @error('period_end') is-invalid @enderror">
                    </div>
                    @error('period_end') <div class="text-danger small mt-1" style="font-size: .75rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-2 col-md-4">
                    <button class="btn btn-primary btn-sm px-3" type="submit">
                        <i class="bi bi-plus-lg me-1"></i>Create draft cycle
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Cycles History Table --}}
    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Compensation Cycles Ledger</h2>
            <span class="badge bg-light text-secondary border">{{ $cycles->total() }} cycles</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Cycle Period</th>
                        <th class="text-center">Status</th>
                        <th class="text-end" data-sort-key="number">Pool BV</th>
                        <th class="text-end" data-sort-key="number">Total Paid Out</th>
                        <th class="text-end" data-sort-key="number">Paid % of BV</th>
                        <th>Approved At</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cycles as $cycle)
                        @php
                            $paidPct = $cycle->pool_bv > 0 ? round($cycle->total_paid / $cycle->pool_bv * 100, 1) : null;
                        @endphp
                        <tr>
                            <td class="ps-3 fw-semibold">
                                {{ $cycle->period_start->format('d M Y') }} – {{ $cycle->period_end->format('d M Y') }}
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $cycle->isApproved() ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }} text-capitalize">
                                    {{ $cycle->status }}
                                </span>
                            </td>
                            <td class="text-end fw-medium" data-sort-value="{{ $cycle->pool_bv }}">₹{{ number_format($cycle->pool_bv, 0) }}</td>
                            <td class="text-end fw-bold text-primary" data-sort-value="{{ $cycle->total_paid }}">₹{{ number_format($cycle->total_paid, 0) }}</td>
                            <td class="text-end" data-sort-value="{{ $paidPct ?? 0 }}">
                                @if ($paidPct !== null)
                                    <span class="badge {{ $paidPct > 100 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                        {{ $paidPct }}%
                                    </span>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td class="text-body-tertiary small">{{ $cycle->approved_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="text-end pe-3">
                                @if (! $cycle->isApproved())
                                    <form method="POST" action="{{ route('admin.cycles.approve', $cycle) }}" onsubmit="return confirm('Approve this cycle? This posts income to member wallets and cannot be undone.')">
                                        @csrf @method('PUT')
                                        <button class="btn btn-sm btn-success">
                                            <i class="bi bi-play-fill me-1"></i>Approve &amp; run compensation
                                        </button>
                                    </form>
                                @else
                                    <span class="badge bg-light text-secondary border"><i class="bi bi-lock-fill me-1"></i>Settled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-tertiary py-5">No compensation cycles created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cycles->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $cycles->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection