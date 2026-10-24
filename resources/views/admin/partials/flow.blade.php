{{-- Shared "where am I in the money flow" tracker. Usage: @include('admin.partials.flow', ['current' => 'payments']) --}}
@php
    $staff = auth()->user();
    $counts = [
        'payments' => \App\Models\Payment::where('status', 'pending')->count(),
        'cycles' => \App\Models\Order::where('status', 'paid')->whereNull('cycle_id')->count(),
        'withdrawals' => \App\Models\Withdrawal::where('status', 'pending')->count(),
        'dispatch' => \App\Models\Withdrawal::where('status', 'approved')->count(),
    ];
    $flow = [
        'payments' => ['Verify payments', 'bi-credit-card', 'admin.payments.index', 'payments waiting for you', 'Check each manual payment against your bank, then verify or reject.'],
        'cycles' => ['Approve cycle', 'bi-arrow-repeat', 'admin.cycles.index', 'paid orders waiting to be settled', 'Settles all paid orders and posts income to wallets. Final.'],
        'withdrawals' => ['Review withdrawals', 'bi-cash-coin', 'admin.withdrawals.index', 'requests to approve or reject', 'Approve to make a request payable, or reject to free the money.'],
        'dispatch' => ['Pay out', 'bi-truck', 'admin.dispatch.index', 'approved withdrawals to pay', 'Group approved requests into a batch, pay from your bank, add the UTR and release.'],
    ];
    $keys = array_keys($flow);
    $curIndex = array_search($current ?? '', $keys, true);
    // "Next up" = the first step (starting from where you are) that has work and that this person can open.
    $next = null;
    foreach ($keys as $k) {
        if ($counts[$k] > 0 && $staff->canAccessRoute($flow[$k][2])) { $next = $k; break; }
    }
@endphp

<div class="card border mb-4" id="flowTracker">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-1">
            <div class="small fw-semibold text-secondary"><i class="bi bi-signpost-split text-primary me-1"></i>Money flow — what needs you</div>
            @if ($next)
                @if ($next === ($current ?? null))
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">You're on the step that needs attention</span>
                @else
                    <a href="{{ route($flow[$next][2]) }}" class="small fw-semibold text-decoration-none">Next up: {{ $flow[$next][0] }} ({{ $counts[$next] }}) <i class="bi bi-arrow-right"></i></a>
                @endif
            @else
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="bi bi-check-circle me-1"></i>All clear — nothing waiting</span>
            @endif
        </div>

        <div class="d-flex align-items-stretch gap-2 overflow-auto py-1">
            @foreach ($keys as $i => $k)
                @php
                    [$label, $icon, $route, $what, $help] = $flow[$k];
                    $n = $counts[$k];
                    $here = $k === ($current ?? null);
                    $can = $staff->canAccessRoute($route);
                @endphp
                @if ($i)<div class="d-flex align-items-center text-muted flex-shrink-0"><i class="bi bi-chevron-right"></i></div>@endif
                @php $tag = $can ? 'a' : 'div'; @endphp
                <{{ $tag }} @if ($can) href="{{ route($route) }}" @endif class="flex-fill border rounded-3 px-3 py-2 text-decoration-none {{ $here ? 'border-primary bg-primary-subtle' : ($n ? 'bg-warning-subtle border-warning-subtle' : 'bg-body') }}" style="min-width: 175px;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-grid rounded-circle {{ $here ? 'bg-primary text-white' : ($n ? 'bg-warning text-dark' : 'bg-secondary-subtle text-secondary') }}" style="width:24px;height:24px;display:inline-grid;place-items:center;font-size:.75rem;font-weight:700;">{{ $i + 1 }}</span>
                        <span class="small fw-semibold text-body">{{ $label }}</span>
                        @if ($n)<span class="badge bg-danger rounded-pill ms-auto">{{ $n }}</span>@else<i class="bi bi-check2 text-success ms-auto"></i>@endif
                    </div>
                    <div class="text-secondary mt-1" style="font-size: .72rem; line-height: 1.35;">{{ $n ? $n.' '.$what : 'Nothing waiting' }}</div>
                </{{ $tag }}>
            @endforeach
        </div>

        @if ($curIndex !== false)
            <div class="small text-secondary mt-2"><i class="bi bi-info-circle me-1"></i><strong>This step:</strong> {{ $flow[$current][4] }}</div>
        @endif
    </div>
</div>
