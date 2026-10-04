@extends('layouts.admin')

@section('title', 'Dispatch batches')

@section('content')
    <p class="text-secondary mb-3" style="max-width: 680px;">
        A batch groups approved withdrawals to pay out together on a chosen date, instead of clicking "mark
        paid" one at a time. Nothing is paid until you explicitly <strong>release</strong> a batch — that's
        the one moment money actually moves.
    </p>

    {{-- Create Batch Form Card --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-truck text-primary me-1"></i>Schedule New Payout Dispatch Batch</h2>
            <div class="text-body-tertiary small">Automatically pulls in every currently approved withdrawal request</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.dispatch.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label small fw-medium text-secondary mb-1">Scheduled Payout Date <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-calendar-date text-muted"></i></span>
                        <input type="date" name="scheduled_for" value="{{ old('scheduled_for', now()->format('Y-m-d')) }}" required
                            class="form-control @error('scheduled_for') is-invalid @enderror">
                    </div>
                    @error('scheduled_for') <div class="text-danger small mt-1" style="font-size: .75rem;">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-sm-6 col-md-8">
                    <button class="btn btn-primary btn-sm px-3" type="submit">
                        <i class="bi bi-plus-lg me-1"></i>Create batch — pulls in every approved withdrawal
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Dispatch Batches Table --}}
    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-truck me-2 text-primary"></i>Dispatch Batches Ledger</h2>
            <span class="badge bg-light text-secondary border">{{ $batches->total() }} batches</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Scheduled For</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" data-sort-key="number">Withdrawals Count</th>
                        <th class="text-end" data-sort-key="number">Total Payout Amount</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $batch->scheduled_for->format('d M Y') }}</td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $batch->isDraft() ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }} text-capitalize">
                                    {{ $batch->status }}
                                </span>
                            </td>
                            <td class="text-center" data-sort-value="{{ $batch->withdrawals_count }}">
                                <span class="badge bg-light text-dark border px-2 py-1">{{ $batch->withdrawals_count }}</span>
                            </td>
                            <td class="text-end fw-bold text-primary" data-sort-value="{{ $batch->total_amount }}">
                                ₹{{ number_format($batch->total_amount, 2) }}
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.dispatch.show', $batch) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Open Batch
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-tertiary py-5">No batches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $batches->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection