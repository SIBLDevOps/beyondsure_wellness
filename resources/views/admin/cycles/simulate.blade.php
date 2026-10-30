@extends('layouts.admin')

@section('title', 'Compensation simulation')

@section('content')
    @php
        $inr = fn ($v) => '₹'.number_format((float) $v, 2);
        $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
    @endphp

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
        <div style="max-width: 680px;">
            <h2 class="h5 fw-bold mb-1">Compensation simulation</h2>
            <p class="text-secondary small mb-0">
                The rules below are the live settings. <strong>Run simulation</strong> runs the real engine on today's paid orders and shows what the next cycle would pay.
                Nothing is posted: the run is rolled back, so no cycle, income, rank or order is changed.
            </p>
        </div>
        <a href="{{ route('admin.cycles.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to cycles</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3"><h3 class="h6 mb-0 fw-semibold"><i class="bi bi-sliders me-2 text-primary"></i>Live settings</h3></div>
                <table class="table table-sm mb-0 small">
                    <tbody>
                        <tr><td class="ps-3 text-secondary">Self income</td><td class="text-end pe-3 fw-semibold">{{ $pct($rules['self']) }} of own BV</td></tr>
                        <tr><td class="ps-3 text-secondary">Sponsor bonus</td><td class="text-end pe-3 fw-semibold">{{ $pct($rules['sponsor']) }} of each direct's BV</td></tr>
                        @foreach ($rules['levels'] as $level => $rate)
                            <tr><td class="ps-3 text-secondary">Level {{ $level }} matching</td><td class="text-end pe-3 fw-semibold">{{ $pct($rate) }} of matched BV</td></tr>
                        @endforeach
                        <tr><td class="ps-3 text-secondary">Rank pool</td><td class="text-end pe-3 fw-semibold">{{ $pct($rules['rank_pool']) }} of the pool</td></tr>
                        <tr><td class="ps-3 text-secondary">Revenue split</td><td class="text-end pe-3">Cost {{ $pct($rules['split']['cost']) }} · Pool {{ $pct($rules['split']['pool']) }} · Company {{ $pct($rules['split']['company']) }}</td></tr>
                        <tr><td class="ps-3 text-secondary">Circuit breaker</td><td class="text-end pe-3">{{ $pct($rules['breaker']) }} of a layer's new BV</td></tr>
                        <tr><td class="ps-3 text-secondary">Matching caps</td><td class="text-end pe-3">{{ $rules['cap_cycle'] > 0 ? $inr($rules['cap_cycle']).' a month' : 'No monthly limit' }} · {{ $rules['cap_week'] > 0 ? $inr($rules['cap_week']).' a week' : 'no weekly limit' }}</td></tr>
                    </tbody>
                </table>
                <div class="card-footer bg-body small text-secondary">Change these under <a href="{{ route('admin.settings.edit') }}" class="link-primary">Settings</a>; this page always shows the current values.</div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border h-100">
                <div class="card-body d-flex flex-column justify-content-center text-center py-5">
                    <i class="bi bi-play-circle fs-1 text-primary"></i>
                    <h3 class="h6 fw-bold mt-2">What would the next cycle pay?</h3>
                    <p class="text-secondary small mx-auto" style="max-width: 420px;">Uses every paid order that has not been settled yet and the settings on the left. Safe to run as often as you like.</p>
                    <form method="POST" action="{{ route('admin.cycles.simulate.run') }}">
                        @csrf
                        <button class="btn btn-primary px-4"><i class="bi bi-play-fill me-1"></i>Run simulation</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($result)
        @if ($result['orders'] === 0 && $result['total_paid'] == 0)
            <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>There are no paid orders waiting to be settled, so a cycle would pay nothing right now.</div>
        @else
            <div class="alert alert-success d-flex align-items-center gap-2 py-2"><i class="bi bi-shield-check"></i><span><strong>Nothing was paid.</strong> This was a simulation and has been rolled back.</span></div>

            <div class="row g-3 mb-4">
                @foreach ([
                    ['Orders settled', number_format($result['orders']), $inr($result['bv']).' BV'],
                    ['Pool', $inr($result['pool']), 'available to pay'],
                    ['Would be paid', $inr($result['total_paid']), ($result['pool'] > 0 ? round($result['total_paid'] / $result['pool'] * 100, 1) : 0).'% of the pool'],
                    ['Left in the pool', $inr(max(0, $result['pool'] - $result['total_paid'])), 'stays with the company'],
                ] as [$label, $big, $small])
                    <div class="col-6 col-xl-3"><div class="border rounded-3 p-3 h-100 bg-body"><div class="small text-secondary">{{ $label }}</div><div class="fw-bold fs-5">{{ $big }}</div><div class="small text-secondary">{{ $small }}</div></div></div>
                @endforeach
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card border">
                        <div class="card-header bg-light-subtle border-bottom py-3"><h3 class="h6 mb-0 fw-semibold">By income type</h3></div>
                        <table class="table table-sm mb-0 small">
                            <tbody>
                                <tr><td class="ps-3">Self income</td><td class="text-end pe-3">{{ $inr($result['totals']['self']) }}</td></tr>
                                <tr><td class="ps-3">Sponsor bonus</td><td class="text-end pe-3">{{ $inr($result['totals']['sponsor']) }}</td></tr>
                                <tr><td class="ps-3">Matching income</td><td class="text-end pe-3">{{ $inr($result['totals']['matching']) }}</td></tr>
                                <tr><td class="ps-3">Rank pool</td><td class="text-end pe-3">{{ $inr($result['totals']['rank']) }}</td></tr>
                            </tbody>
                            <tfoot class="table-light"><tr><td class="ps-3 fw-semibold">Total</td><td class="text-end pe-3 fw-semibold">{{ $inr($result['total_paid']) }}</td></tr></tfoot>
                        </table>
                    </div>

                    <div class="card border mt-4">
                        <div class="card-header bg-light-subtle border-bottom py-3"><h3 class="h6 mb-0 fw-semibold">Matching by level</h3></div>
                        <table class="table table-sm mb-0 small">
                            <tbody>
                                @forelse ($result['by_level'] as $level => $amount)
                                    <tr><td class="ps-3">Level {{ $level }} <span class="text-secondary">· {{ $pct($rules['levels'][$level] ?? 0) }}</span></td><td class="text-end pe-3">{{ $inr($amount) }}</td></tr>
                                @empty
                                    <tr><td class="ps-3 text-secondary" colspan="2">No matching income this time.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card border">
                        <div class="card-header bg-light-subtle border-bottom py-3"><h3 class="h6 mb-0 fw-semibold">Biggest earners</h3></div>
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light text-secondary"><tr><th class="ps-3">Member</th><th class="text-end">Would earn</th><th class="text-end pe-3">of the pool</th></tr></thead>
                            <tbody>
                                @foreach ($result['earners'] as $e)
                                    <tr>
                                        <td class="ps-3">{{ $e['name'] }} <span class="font-monospace text-secondary">{{ $e['code'] }}</span></td>
                                        <td class="text-end">{{ $inr($e['total']) }}</td>
                                        <td class="text-end pe-3 text-secondary">{{ $result['pool'] > 0 ? round($e['total'] / $result['pool'] * 100, 1) : 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection
