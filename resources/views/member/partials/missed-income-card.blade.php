@php
    // For an active member: sponsor income they missed on purchases made before the day they activated. Their own only.
    $missedRows = collect();
    $missedTotal = 0.0;
    if ($member && \App\Models\LockedIncome::ready()) {
        $missedRows = \App\Models\LockedIncome::where('member_id', $member->id)->latest('id')->limit(10)->get();
        $missedTotal = (float) \App\Models\LockedIncome::where('member_id', $member->id)->sum('amount');
    }
@endphp
@if ($missedTotal > 0)
    <div class="card border mb-4" id="missed-income">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-warning"></i>Income you missed: ₹{{ number_format($missedTotal, 2) }}</h2>
            <div class="text-body-tertiary small">It was not paid because your account was not active at the time, and it is kept by the company. It does not count in your wallet.</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 small align-middle">
                <thead class="table-light text-secondary"><tr><th class="ps-3">When</th><th>What</th><th>Type</th><th class="text-end pe-3">Missed</th></tr></thead>
                <tbody>
                    @foreach ($missedRows as $row)
                        <tr>
                            <td class="ps-3">{{ $row->created_at->format('d M Y') }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-capitalize">{{ $row->type }}</td>
                            <td class="text-end pe-3">₹{{ number_format($row->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
