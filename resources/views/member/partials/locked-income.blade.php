@php
    // Income that would have been theirs, but their account was not active when it was earned: matching income on a
    // cycle, or sponsor income on a purchase made before their activation day. Shown to people who are not active yet.
    $lockedTotal = 0.0;
    $lockedRows = collect();
    if ($member && $member->status !== 'active' && \App\Models\LockedIncome::ready()) {
        $lockedRows = \App\Models\LockedIncome::where('member_id', $member->id)->latest('id')->limit(10)->get();
        $lockedTotal = (float) \App\Models\LockedIncome::where('member_id', $member->id)->sum('amount');
    }
@endphp
@if ($lockedTotal > 0)
    <div class="card border-warning mb-4" id="locked-income" role="status">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="fw-bold text-warning-emphasis"><i class="bi bi-lock-fill me-1"></i>You have missed ₹{{ number_format($lockedTotal, 2) }} of income</div>
                    <div class="small text-secondary mt-1" style="max-width: 62ch;">
                        Your team earned income that would have been yours, but your account is not active yet.
                        <strong>Activate your account by purchasing a package</strong> to start receiving it. Sponsor income counts from the day you activate (by 23:59).
                    </div>
                </div>
                <a href="{{ route('member.plans.index') }}" class="btn btn-warning fw-semibold text-nowrap"><i class="bi bi-rocket-takeoff me-1"></i>Buy a package</a>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-sm mb-0 small align-middle">
                    <thead class="text-secondary"><tr><th>When</th><th>What</th><th>Type</th><th class="text-end">Missed</th></tr></thead>
                    <tbody>
                        @foreach ($lockedRows as $row)
                            <tr>
                                <td>{{ $row->created_at->format('d M Y') }}</td>
                                <td>{{ $row->description }}</td>
                                <td class="text-capitalize">{{ $row->type }}</td>
                                <td class="text-end">₹{{ number_format($row->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
