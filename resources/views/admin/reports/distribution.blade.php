@extends('layouts.admin')

@section('title', 'Money distribution')

@section('content')
    @include('partials.reports-nav')

    @php
        $t = $flow['totals'];
        $money = fn ($n) => '₹'.number_format((float) $n, 2);
        $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100, 1) : 0;
    @endphp

    <div class="card border mb-4 shadow-sm">
        <div class="card-body p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h2 class="h6 fw-bold mb-1"><i class="bi bi-diagram-2-fill me-2 text-primary"></i>Money distribution</h2>
                <p class="text-secondary small mb-0" style="max-width: 820px;">
                    Follow the money from what members paid to what each person was paid and what the company kept. Figures cover the approved cycles
                    from {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}, and every line is taken from the orders and the income ledger, so they always add up.
                </p>
            </div>
            <a href="{{ route('admin.reports.distribution.csv', request()->query()) }}" class="btn btn-sm btn-outline-success text-nowrap"><i class="bi bi-download me-1"></i>Export CSV</a>
        </div>
    </div>

    @if (empty($flow['cycles']))
        <div class="alert alert-info">No approved cycle in this date range. Approve a cycle (Cycles page) or widen the dates to see how its money was distributed.</div>
    @endif

    {{-- Step by step: from sales to what the company keeps --}}
    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3"><h2 class="h6 mb-0 fw-semibold">Where the money went</h2></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <tbody>
                    <tr><td class="ps-3 fw-semibold">1. Sales collected</td><td class="text-secondary small">What members paid for {{ number_format($t['orders']) }} orders, including GST</td><td class="text-end pe-3 fw-bold">{{ $money($t['sales']) }}</td></tr>
                    <tr><td class="ps-4 text-secondary">less GST</td><td class="text-secondary small">Collected for the government, not income</td><td class="text-end pe-3">− {{ $money($t['gst']) }}</td></tr>
                    <tr class="table-light"><td class="ps-3 fw-semibold">2. Taxable sales</td><td class="text-secondary small">Sales without GST</td><td class="text-end pe-3 fw-bold">{{ $money($t['taxable']) }}</td></tr>
                    <tr><td class="ps-3 fw-semibold">3. Income pool (BV)</td><td class="text-secondary small">The part of taxable sales set aside to fund member income. The rest, {{ $money($t['taxable'] - $t['pool_bv']) }}, covers product cost and the company margin.</td><td class="text-end pe-3 fw-bold">{{ $money($t['pool_bv']) }}</td></tr>
                    <tr class="table-light"><td class="ps-3 fw-semibold text-success">4. Paid to members</td><td class="text-secondary small">{{ $pct($t['paid'], $t['pool_bv']) }}% of the pool</td><td class="text-end pe-3 fw-bold text-success">{{ $money($t['paid']) }}</td></tr>
                    <tr><td class="ps-4">Self income</td><td class="text-secondary small">{{ $rules['self'] }}% of the buyer's own BV</td><td class="text-end pe-3">{{ $money($t['self']) }}</td></tr>
                    <tr><td class="ps-4">Sponsor income</td><td class="text-secondary small">{{ $rules['sponsor'] }}% of a direct's BV, paid to the sponsor</td><td class="text-end pe-3">{{ $money($t['sponsor']) }}</td></tr>
                    <tr><td class="ps-4">Matching income</td><td class="text-secondary small">Paid to seven people for each matched volume (the root holds the last slot on deep matches)</td><td class="text-end pe-3">{{ $money($t['matching']) }}</td></tr>
                    <tr><td class="ps-4">Rank income</td><td class="text-secondary small">{{ $rules['rank_pool'] }}% rank pool</td><td class="text-end pe-3">{{ $money($t['rank']) }}</td></tr>
                    <tr class="table-light"><td class="ps-3 fw-semibold text-warning-emphasis">5. Left in the pool, kept by the company</td><td class="text-secondary small">{{ $pct($t['unspent'], $t['pool_bv']) }}% of the pool: the pool minus what was paid</td><td class="text-end pe-3 fw-bold text-warning-emphasis">{{ $money($t['unspent']) }}</td></tr>
                    <tr><td class="ps-4">Sponsor income missed</td><td class="text-secondary small">The sponsor was not active that day. Not paid to anyone, including the root.</td><td class="text-end pe-3">{{ $money($t['missed_sponsor']) }}</td></tr>
                    <tr><td class="ps-4">Matching income missed</td><td class="text-secondary small">A slot holder had not bought a plan. Their share is not paid.</td><td class="text-end pe-3">{{ $money($t['missed_matching']) }}</td></tr>
                    <tr><td class="ps-4">No match happened</td><td class="text-secondary small">Volume still waiting on a leg (carried forward) or below the match, so nothing was owed yet</td><td class="text-end pe-3">{{ $money($t['not_generated']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <div class="small text-secondary mb-1">The income pool: paid out against kept</div>
            <div class="progress" style="height: 14px;" role="img" aria-label="Share of the pool paid to members">
                <div class="progress-bar bg-success" style="width: {{ min(100, $pct($t['paid'], $t['pool_bv'])) }}%"></div>
                <div class="progress-bar bg-warning" style="width: {{ max(0, 100 - min(100, $pct($t['paid'], $t['pool_bv']))) }}%"></div>
            </div>
            <div class="d-flex justify-content-between small text-secondary mt-1"><span>Paid to members {{ $pct($t['paid'], $t['pool_bv']) }}%</span><span>Kept {{ max(0, 100 - $pct($t['paid'], $t['pool_bv'])) }}%</span></div>
        </div>
    </div>

    {{-- By income type --}}
    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3"><h2 class="h6 mb-0 fw-semibold">By income type</h2></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-secondary small"><tr><th class="ps-3">Type</th><th class="text-end">Paid</th><th class="text-end">Share of what was paid</th><th class="text-end pe-3">Share of the pool</th></tr></thead>
                <tbody>
                    @foreach (['self' => 'Self income', 'sponsor' => 'Sponsor income', 'matching' => 'Matching income', 'rank' => 'Rank income'] as $key => $label)
                        <tr><td class="ps-3">{{ $label }}</td><td class="text-end">{{ $money($t[$key]) }}</td><td class="text-end">{{ $pct($t[$key], $t['paid']) }}%</td><td class="text-end pe-3">{{ $pct($t[$key], $t['pool_bv']) }}%</td></tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold"><tr><td class="ps-3">Total</td><td class="text-end">{{ $money($t['paid']) }}</td><td class="text-end">100%</td><td class="text-end pe-3">{{ $pct($t['paid'], $t['pool_bv']) }}%</td></tr></tfoot>
            </table>
        </div>
    </div>

    {{-- By cycle --}}
    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3"><h2 class="h6 mb-0 fw-semibold">Cycle by cycle</h2></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light text-secondary">
                    <tr><th class="ps-3">Cycle</th><th class="text-end">Orders</th><th class="text-end">Sales</th><th class="text-end">Pool BV</th><th class="text-end">Self</th><th class="text-end">Sponsor</th><th class="text-end">Matching</th><th class="text-end">Rank</th><th class="text-end">Paid</th><th class="text-end">Paid %</th><th class="text-end pe-3">Kept</th></tr>
                </thead>
                <tbody>
                    @forelse ($flow['cycles'] as $r)
                        <tr>
                            <td class="ps-3">{{ $r['cycle']->period_start->format('d M') }} to {{ $r['cycle']->period_end->format('d M Y') }}<div class="text-body-tertiary" style="font-size:.7rem;">Approved {{ \App\Support\BusinessDay::label($r['cycle']->approved_at) }} IST</div></td>
                            <td class="text-end">{{ $r['orders'] }}</td>
                            <td class="text-end">{{ $money($r['sales']) }}</td>
                            <td class="text-end">{{ $money($r['pool_bv']) }}</td>
                            <td class="text-end">{{ $money($r['self']) }}</td>
                            <td class="text-end">{{ $money($r['sponsor']) }}</td>
                            <td class="text-end">{{ $money($r['matching']) }}</td>
                            <td class="text-end">{{ $money($r['rank']) }}</td>
                            <td class="text-end fw-semibold">{{ $money($r['paid']) }}</td>
                            <td class="text-end">{{ $r['paid_pct'] }}%</td>
                            <td class="text-end pe-3">{{ $money($r['unspent']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-body-tertiary py-4">No approved cycle in this date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Rules in force --}}
    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0 fw-semibold">Rules in force</h2>
            <a href="{{ route('admin.settings.edit') }}" class="small text-decoration-none"><i class="bi bi-sliders me-1"></i>Change in Settings</a>
        </div>
        <div class="card-body small">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="fw-semibold mb-1">Income percentages (of BV)</div>
                    <ul class="mb-0 ps-3">
                        <li>Self income: {{ $rules['self'] }}%</li>
                        <li>Sponsor income: {{ $rules['sponsor'] }}%, paid only if the sponsor was active by 23:59 India time on the day the buyer paid</li>
                        <li>Matching, one slot per level:
                            @foreach ($rules['levels'] as $level => $p) Level {{ $level }} {{ $p }}%{{ ! $loop->last ? ',' : '' }} @endforeach
                        </li>
                        <li>Rank pool: {{ $rules['rank_pool'] }}%</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="fw-semibold mb-1">Protections and choices</div>
                    <ul class="mb-0 ps-3">
                        <li>Circuit breaker: {{ $rules['breaker'] }}% of a layer's new volume</li>
                        <li>Root of the tree: {{ $rules['root'] ? 'always holds the last slot on deep matches' : 'paid only inside the levels' }}</li>
                        <li>Members who have not bought a plan: sponsor income {{ $rules['inactive_sponsor'] ? 'is paid' : 'is not paid' }}, matching income {{ $rules['inactive_matching'] ? 'is paid' : 'is not paid' }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Trace one order --}}
    <div class="card border mb-4 shadow-sm" id="trace">
        <div class="card-header bg-light-subtle border-bottom py-3"><h2 class="h6 mb-0 fw-semibold">Trace an order</h2></div>
        <div class="card-body">
            <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                @foreach (['from', 'to', 'region'] as $keep)
                    @if (request()->filled($keep) && is_string(request($keep)))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
                @endforeach
                <input type="text" name="order" value="{{ $orderCode }}" maxlength="40" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Order code, e.g. ORD-ABCD1234">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Trace</button>
            </form>

            @if ($orderCode !== '')
                @if ($trace)
                    <div class="fw-semibold mb-2">Trace of order {{ $trace['order']->order_code }}
                        <span class="text-secondary fw-normal">· {{ $trace['order']->member->name }} · {{ $trace['order']->plan->name }} · {{ $money($trace['order']->amount) }} · {{ $trace['order']->bv }} BV</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm small align-middle mb-0">
                            <thead class="table-light text-secondary"><tr><th>Paid to</th><th>What</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                @foreach ($trace['lines'] as $line)
                                    <tr><td>{{ $line->member->name ?? '—' }}</td><td>{{ $line->description }}</td><td class="text-end">{{ $money($line->amount) }}</td></tr>
                                @endforeach
                                @foreach ($trace['kept'] as $line)
                                    <tr class="table-warning"><td>{{ $line->member->name ?? '—' }}</td><td>{{ $line->description }}: not paid, kept by the company</td><td class="text-end">{{ $money($line->amount) }}</td></tr>
                                @endforeach
                                @if ($trace['lines']->isEmpty() && $trace['kept']->isEmpty())
                                    <tr><td colspan="3" class="text-center text-body-tertiary py-3">No income has been posted for this order yet. It is posted when its cycle is approved.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-body-tertiary mt-2">Matching income is paid for matched volume across the tree, not for one order, so it is not listed here. See the 7th level cascade report for each match.</div>
                @else
                    <div class="text-secondary">No order found with that code.</div>
                @endif
            @endif
        </div>
    </div>
@endsection
