@extends('layouts.member')

@section('title', 'Level-Wise Income')

@section('content')
    @include('member.partials.network-nav')

    @php
        $money = fn ($n) => '₹'.number_format((float) $n, 2);
        $r = $report;
        $lost = $r['lost'];
        $wait = $r['waiting'];
        $total = max(0.0, (float) $r['earned']);
        $share = fn ($part) => $total > 0 ? round($part / $total * 100, 1) : 0;
        $parts = [
            ['Self income', 'bi-person-check', '#16a34a', $r['self'], 'Your own purchase: '.$r['rules']['self'].'% of its BV'],
            ['Sponsor income', 'bi-person-plus', '#0d9488', $r['sponsor'], $r['rules']['sponsor'].'% of the BV of people you personally sponsored'],
            ['Matching income from your team', 'bi-diagram-3', '#2563eb', $r['matching_team'], 'Matches made by people below you, paid to you by level'],
            ['Matching income from your own legs', 'bi-arrows-expand', '#60a5fa', $r['matching_own'], 'Your left and right legs matched, you are level 1'],
            ['Rank income', 'bi-trophy', '#7c3aed', $r['rank'], 'Your share of the rank pool'],
        ];
    @endphp

    {{-- 1. The three numbers that matter --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card border-0 h-100 shadow-sm" style="background: linear-gradient(135deg, #059669, #047857);">
                <div class="card-body text-white p-4">
                    <div class="small text-white-50 mb-1"><i class="bi bi-wallet2 me-1"></i>You have earned</div>
                    <div class="display-6 fw-bold lh-1">{{ $money($r['earned']) }}</div>
                    <div class="small text-white-50 mt-2">Everything in your wallet, before any withdrawal</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card border h-100 {{ $lost['total'] > 0 ? 'border-danger' : '' }}">
                <div class="card-body p-4">
                    <div class="small text-secondary mb-1"><i class="bi bi-exclamation-triangle me-1 text-danger"></i>Income you lost</div>
                    <div class="fs-2 fw-bold lh-1 {{ $lost['total'] > 0 ? 'text-danger' : 'text-success' }}">{{ $money($lost['total']) }}</div>
                    <div class="small text-secondary mt-2">{{ $lost['total'] > 0 ? 'Not paid, because you were not active at the time' : 'Nothing lost, well done' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="card border h-100">
                <div class="card-body p-4">
                    <div class="small text-secondary mb-1"><i class="bi bi-hourglass-split me-1" style="color:#2563eb;"></i>Waiting to be matched</div>
                    <div class="fs-2 fw-bold lh-1" style="color:#2563eb;">{{ number_format($wait['bv'], 0) }} <span class="fs-6 fw-normal text-secondary">BV</span></div>
                    <div class="small text-secondary mt-2">{{ $wait['bv'] > 0 ? 'Not lost: it pays when the other side grows' : 'Your two legs are level' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. What you earned --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-success"></i>What you earned</h2>
            <div class="text-body-tertiary small">Every rupee in your wallet, by where it came from</div>
        </div>
        <div class="card-body">
            @if ($total <= 0)
                <div class="text-center text-secondary py-4"><i class="bi bi-hourglass fs-3 d-block mb-2"></i><strong>No income yet.</strong><div class="small">Income appears here after the next billing cycle is approved.</div></div>
            @else
                <div class="progress mb-3" style="height: 14px;" role="img" aria-label="Share of your income by type">
                    @foreach ($parts as [$label, $icon, $color, $amount])
                        @if ($amount > 0)<div class="progress-bar" style="width: {{ $share($amount) }}%; background: {{ $color }};" title="{{ $label }}: {{ $money($amount) }}"></div>@endif
                    @endforeach
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            @foreach ($parts as [$label, $icon, $color, $amount, $how])
                                <tr class="{{ $amount > 0 ? '' : 'text-body-tertiary' }}">
                                    <td style="width: 48px;"><span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white" style="width:34px;height:34px;background: {{ $color }}; {{ $amount > 0 ? '' : 'opacity:.35;' }}"><i class="bi {{ $icon }}"></i></span></td>
                                    <td><div class="fw-semibold">{{ $label }}</div><div class="small text-secondary">{{ $how }}</div></td>
                                    <td class="text-end"><div class="fw-bold">{{ $money($amount) }}</div><div class="small text-secondary">{{ $share($amount) }}%</div></td>
                                </tr>
                            @endforeach
                            @if (abs($r['other']) >= 0.01)
                                <tr><td></td><td><div class="fw-semibold">Other</div></td><td class="text-end fw-bold">{{ $money($r['other']) }}</td></tr>
                            @endif
                        </tbody>
                        <tfoot><tr class="table-light fw-bold"><td></td><td>Total earned</td><td class="text-end text-success">{{ $money($r['earned']) }}</td></tr></tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- 3. What you lost --}}
    @if ($lost['total'] > 0)
        <div class="card border-danger mb-4" id="lost-income">
            <div class="card-header py-3" style="background: var(--bs-danger-bg-subtle);">
                <h2 class="h6 mb-0 fw-semibold text-danger-emphasis"><i class="bi bi-exclamation-triangle-fill me-2"></i>Income you lost: {{ $money($lost['total']) }}</h2>
                <div class="small text-secondary">This was earned by your team while your account was <strong>not active</strong>. It was not paid to you or to anyone else: the company kept it, and it does not count in your wallet.</div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><div class="p-3 rounded-3 bg-light border"><div class="small text-secondary">Matching income lost</div><div class="fs-5 fw-bold">{{ $money($lost['matching']) }}</div><div class="small text-secondary">Your slot in a match was empty because you had not bought a plan</div></div></div>
                    <div class="col-md-6"><div class="p-3 rounded-3 bg-light border"><div class="small text-secondary">Sponsor income lost</div><div class="fs-5 fw-bold">{{ $money($lost['sponsor']) }}</div><div class="small text-secondary">Someone you sponsored bought before you were active that day (by 23:59)</div></div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="text-secondary"><tr><th>Date</th><th>From</th><th>How it was calculated</th><th class="text-end">Lost</th></tr></thead>
                        <tbody>
                            @foreach ($lost['rows'] as $row)
                                <tr>
                                    <td>{{ $row->created_at->format('d M Y') }}</td>
                                    <td>{{ $row->sourceMember->name ?? '—' }}<div class="font-monospace text-body-tertiary" style="font-size:.7rem;">{{ $row->sourceMember->member_code ?? '' }}</div></td>
                                    <td><span class="badge bg-danger-subtle text-danger-emphasis text-capitalize me-1">{{ $row->type }}</span>{{ $row->description }}<div class="text-secondary">{{ $row->how ?? '' }}</div></td>
                                    <td class="text-end fw-semibold">{{ $money($row->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-secondary mt-3"><i class="bi bi-lightbulb me-1 text-danger"></i>Stay active (keep your plan paid) and share your invite link so your team's purchases are counted from the day you activate.</div>
            </div>
        </div>
    @else
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="status"><i class="bi bi-shield-check fs-5"></i><div><strong>Nothing lost.</strong> You were active every time income was calculated for you.</div></div>
    @endif

    {{-- 4. Waiting, not lost --}}
    <div class="card border mb-4" id="waiting">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-hourglass-split me-2 text-primary"></i>Waiting to be matched</h2>
            <div class="text-body-tertiary small">Matching pays on the smaller of your two legs. The extra on the bigger leg is kept for you.</div>
        </div>
        <div class="card-body">
            @php $maxLeg = max(1, $wait['left'], $wait['right']); @endphp
            @foreach (['left' => 'Left leg', 'right' => 'Right leg'] as $side => $label)
                <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold">{{ $label }}</span><span>{{ number_format($wait[$side], 0) }} BV</span></div>
                <div class="progress mb-3" style="height: 10px;"><div class="progress-bar {{ $side === 'left' ? 'bg-info' : 'bg-success' }}" style="width: {{ round($wait[$side] / $maxLeg * 100) }}%"></div></div>
            @endforeach
            <div class="d-flex justify-content-between small text-secondary mb-3"><span>Already matched and paid</span><span class="fw-semibold">{{ number_format($wait['matched'], 0) }} BV</span></div>
            @if (! empty($wait['blocked']))
                <div class="p-3 rounded-3 bg-danger-subtle border border-danger-subtle small mb-3" id="own-match-waiting">
                    <strong>Your own match is waiting: {{ $money($wait['blocked_amount']) }}.</strong>
                    Your own match pays only when the member placed directly under you on each side is active.
                    @foreach ($wait['blocked'] as $b)
                        <strong>{{ $b['name'] }}</strong> ({{ $b['side'] }} leg) is not active yet{{ $loop->last ? '' : ', ' }}
                    @endforeach
                    . It is not lost: it is paid in the cycle after they activate.
                </div>
            @endif
            @if ($wait['bv'] > 0)
                <div class="p-3 rounded-3 bg-primary-subtle border border-primary-subtle small">
                    <strong>{{ number_format($wait['bv'], 0) }} BV is waiting on your {{ $wait['strong_side'] }} leg.</strong>
                    It is not lost. It pays as your {{ $wait['weak_side'] }} leg grows: every 1,000 BV added to your {{ $wait['weak_side'] }} leg earns you about {{ $money($wait['per_1000']) }}.
                </div>
            @else
                <div class="small text-secondary"><i class="bi bi-check-circle text-success me-1"></i>Nothing waiting: your two legs are level.</div>
            @endif
        </div>
    </div>

    {{-- 5. Income by generation --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-success"></i>Income by generation</h2>
                <div class="text-body-tertiary small">Level 1 is the people placed directly under you, Level 2 the next row, and so on. You are paid down to Level {{ $cascadeDepth }}.</div>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2 small text-secondary">
                <label class="mb-0 fw-medium" for="depth">Show</label>
                <select id="depth" name="depth" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                    @foreach ([4, 5, 6, 7, 8, 10] as $d)
                        <option value="{{ $d }}" @selected(($depth ?? 7) == $d)>{{ $d }} levels</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Level</th>
                        <th class="text-center">Members</th>
                        <th>Rate</th>
                        <th class="text-end">Team BV</th>
                        <th class="text-end">Sponsor</th>
                        <th class="text-end">Matching</th>
                        <th class="text-end pe-3">You earned</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-success">
                        <td class="ps-3 fw-semibold"><span class="badge bg-success">You</span></td>
                        <td class="text-center">1</td>
                        <td class="small text-secondary" colspan="2">Your own purchase and match</td>
                        <td class="text-end">{{ $money($r['self']) }}<div class="text-secondary" style="font-size:.7rem;">self</div></td>
                        <td class="text-end">{{ $money($r['matching_own']) }}<div class="text-secondary" style="font-size:.7rem;">own legs</div></td>
                        <td class="text-end pe-3 fw-bold">{{ $money($r['self'] + $r['matching_own']) }}</td>
                    </tr>
                    @forelse ($levels as $row)
                        @php
                            $within = $row['level'] <= $cascadeDepth;
                            $lvlPct = ($levelPcts ?? [])[$row['level']] ?? ($matchingPct ?? 10);
                        @endphp
                        <tr class="{{ $within ? '' : 'text-body-tertiary' }}">
                            <td class="ps-3"><span class="badge {{ $within ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">Level {{ $row['level'] }}</span></td>
                            <td class="text-center fw-medium">
                                {{ $row['count'] }}
                                @if (($row['paid_count'] ?? $row['count']) < $row['count'])
                                    <div class="text-danger fw-semibold" style="font-size: .7rem;">{{ $row['count'] - $row['paid_count'] }} not active</div>
                                @endif
                            </td>
                            <td class="small">
                                @if ($row['level'] === 1)<span class="badge bg-info-subtle text-info-emphasis">{{ $sponsorPct ?? 20 }}% sponsor</span> @endif
                                @if ($within)<span class="badge" style="background:#dbeafe;color:#1d4ed8;">{{ $lvlPct }}% match</span>@else<span class="badge bg-secondary-subtle text-secondary">not paid</span>@endif
                            </td>
                            <td class="text-end">{{ number_format($row['bv'], 0) }}</td>
                            <td class="text-end">{{ $money($row['sponsor_income']) }}</td>
                            <td class="text-end">{{ $money($row['matching_income']) }}</td>
                            <td class="text-end pe-3 fw-bold">{{ $money($row['total_income']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-tertiary py-4">No team yet. Share your invite link to start building your levels.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr><td class="ps-3" colspan="4">Total</td><td class="text-end">{{ $money($r['self'] + $totalSponsor) }}</td><td class="text-end">{{ $money($r['matching_own'] + $totalMatching) }}</td><td class="text-end pe-3 text-success">{{ $money($r['self'] + $r['matching_own'] + $totalLevelIncome) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-white small text-secondary">
            This table adds up to your total earned of {{ $money($r['earned']) }} when the depth shown covers your whole team{{ $r['rank'] > 0 ? ' and rank income is added' : '' }}.
        </div>
    </div>

    {{-- 6. Every rupee, line by line, with the working --}}
    <div class="card border mb-4" id="statement">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-success"></i>Every rupee, line by line</h2>
                <div class="text-body-tertiary small">What each amount was, who it came from and exactly how it was worked out</div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                @foreach ([null => 'All', 'self' => 'Self', 'sponsor' => 'Sponsor', 'matching' => 'Matching', 'rank' => 'Rank'] as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['type' => $key, 'page' => null]) }}#statement" class="btn btn-sm rounded-pill {{ $statementType === $key ? 'btn-success' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr><th class="ps-3">Date</th><th>Type</th><th>From</th><th>How it was calculated</th><th class="text-end pe-3">Amount</th></tr>
                </thead>
                <tbody>
                    @forelse ($statement as $line)
                        <tr>
                            <td class="ps-3 small text-nowrap">{{ $line['date']->format('d M Y') }}</td>
                            <td>@if ($line['type'] === 'matching')<span class="badge text-capitalize" style="background:#dbeafe;color:#1d4ed8;">{{ $line['type'] }}</span>@else<span class="badge bg-light text-dark border text-capitalize">{{ $line['type'] }}</span>@endif</td>
                            <td class="small">
                                @if ($line['from'])
                                    <span class="fw-medium">{{ $line['from']->name }}</span><div class="font-monospace text-body-tertiary" style="font-size:.7rem;">{{ $line['from']->member_code }}</div>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td class="small text-secondary" style="min-width: 260px;">{{ $line['how'] }}</td>
                            <td class="text-end pe-3 fw-bold text-success">{{ $money($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-tertiary py-4">No income lines yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr><td class="ps-3" colspan="4">Total of all {{ $statementType ? $statementType : '' }} lines ({{ $statement->total() }})</td><td class="text-end pe-3 text-success">{{ $money($statementTotal) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-white small text-secondary d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Matching volume is worked back from the amount paid and the level's rate.</span>
            @if ($statement->hasPages()){{ $statement->links('pagination::bootstrap-5') }}@endif
        </div>
    </div>

    {{-- 7. How it is calculated --}}
    <div class="card border mb-4" id="how">
        <div class="card-header bg-light-subtle border-bottom py-3"><h2 class="h6 mb-0 fw-semibold"><i class="bi bi-calculator me-2 text-success"></i>How your income is calculated</h2></div>
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-6"><div class="fw-semibold">Self income: {{ $r['rules']['self'] }}%</div>Paid on the BV of your own plan each time you buy.</div>
                <div class="col-md-6"><div class="fw-semibold">Sponsor income: {{ $r['rules']['sponsor'] }}%</div>Paid on the BV of each person you personally sponsored, when you were active by 23:59 on the day they paid.</div>
                <div class="col-12">
                    <div class="fw-semibold mb-1">Matching income: paid on the smaller of your two legs</div>
                    Each time volume matches, up to seven people above it are paid, one level each:
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach ($r['rules']['levels'] as $level => $p)
                            <span class="badge bg-primary-subtle text-primary fw-medium">Level {{ $level }}: {{ $p }}%</span>
                        @endforeach
                    </div>
                </div>
                <div class="col-12 text-secondary">
                    <i class="bi bi-info-circle me-1"></i>You must be active (have bought a plan) to be paid. If you are not active when a match or a purchase happens, that income is lost and kept by the company. The root of the tree always holds the last of the seven slots on a deep match.
                </div>
            </div>
        </div>
    </div>
@endsection
