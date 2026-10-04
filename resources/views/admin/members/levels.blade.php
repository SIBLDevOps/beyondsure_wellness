@extends('layouts.admin')

@section('title', 'Level-wise income — ' . $member->name)

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="avatar-circle avatar-circle-sm bg-primary text-white">{{ strtoupper(substr($member->name, 0, 2)) }}</span>
                <span class="fs-5 fw-bold">{{ $member->name }}</span>
                <span class="badge bg-secondary-subtle text-secondary font-monospace">{{ $member->member_code }}</span>
                <span class="badge bg-primary-subtle text-primary text-capitalize">{{ $member->rank }}</span>
            </div>
            <p class="text-secondary small mb-0" style="max-width: 640px;">
                Breakdown of downline generations, Business Volume (BV) generated, and exact level-wise income distributed to <strong>{{ $member->name }}</strong>.
                Matches only pay up to cascade depth (currently <strong>{{ $cascadeDepth }}</strong> levels).
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <form method="GET" class="d-flex align-items-center gap-2 small text-secondary">
                <label for="depthSelect" class="form-label mb-0 small">Depth:</label>
                <select id="depthSelect" name="depth" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                    @foreach ([4, 5, 6, 7, 8, 10] as $d)
                        <option value="{{ $d }}" @selected($depth == $d)>{{ $d }} Levels</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('admin.members.network', $member) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Full network</a>
            <a href="{{ route('admin.members.show', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person me-1"></i>Profile</a>
        </div>
    </div>

    {{-- Member Level KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="text-secondary small">Total level income earned</div>
                    <div class="fs-3 fw-bold text-primary">₹{{ number_format($totalLevelIncome, 2) }}</div>
                    <div class="text-body-tertiary small mt-1">From all downline levels</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="text-secondary small">Direct sponsor income (L1)</div>
                    <div class="fs-3 fw-bold text-info-emphasis">₹{{ number_format($totalSponsor, 2) }}</div>
                    <div class="text-body-tertiary small mt-1">{{ $sponsorPct }}% on direct orders</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="text-secondary small">Matching cascade income</div>
                    <div class="fs-3 fw-bold" style="color: #6d28d9;">₹{{ number_format($totalMatching, 2) }}</div>
                    <div class="text-body-tertiary small mt-1">{{ $matchingPct }}% on leg matches</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="text-secondary small">Cascade depth limit</div>
                    <div class="fs-3 fw-bold text-dark">{{ $cascadeDepth }} Levels</div>
                    <div class="text-body-tertiary small mt-1">Levels > {{ $cascadeDepth }} do not pay</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Level Wise Income Distribution Table --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Generation-by-generation income & volume</h2>
            <span class="badge bg-primary-subtle text-primary">Cascade depth: {{ $cascadeDepth }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-secondary small table-light">
                        <tr>
                            <th class="ps-3">Level</th>
                            <th>Members</th>
                            <th>Active this month</th>
                            <th>BV contributed</th>
                            <th>Distribution rate</th>
                            <th class="text-end">Sponsor Income</th>
                            <th class="text-end">Matching Income</th>
                            <th class="text-end">Total Income Earned</th>
                            <th class="text-center pe-3">Pays this member?</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($levels as $row)
                            @php
                                $isWithinDepth = $row['level'] <= $cascadeDepth;
                                $lvlMatchPct = $levelPcts[$row['level']] ?? $matchingPct;
                            @endphp
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <span class="badge {{ $isWithinDepth ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }} px-2 py-1">
                                        Level {{ $row['level'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $row['count'] }}</span>
                                    <span class="text-body-tertiary small">members</span>
                                </td>
                                <td>
                                    <span class="badge {{ $row['active_count'] > 0 ? 'bg-success-subtle text-success' : 'bg-light text-secondary border' }}">
                                        {{ $row['active_count'] }} active
                                    </span>
                                </td>
                                <td class="fw-medium">₹{{ number_format($row['bv'], 0) }}</td>
                                <td>
                                    @if ($row['level'] === 1)
                                        <span class="badge bg-info-subtle text-info-emphasis">{{ $sponsorPct }}% Sponsor</span>
                                        <span class="badge bg-purple-subtle text-purple" style="background:#ede9fe; color:#6d28d9;">{{ $lvlMatchPct }}% Match</span>
                                    @elseif ($isWithinDepth)
                                        <span class="badge bg-purple-subtle text-purple" style="background:#ede9fe; color:#6d28d9;">{{ $lvlMatchPct }}% Match</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">0% (Beyond depth)</span>
                                    @endif
                                </td>
                                <td class="text-end text-info-emphasis fw-medium">
                                    ₹{{ number_format($row['sponsor_income'] ?? 0, 2) }}
                                </td>
                                <td class="text-end fw-medium" style="color: #6d28d9;">
                                    ₹{{ number_format($row['matching_income'] ?? 0, 2) }}
                                </td>
                                <td class="text-end fw-bold text-primary">
                                    ₹{{ number_format($row['total_income'] ?? 0, 2) }}
                                </td>
                                <td class="text-center pe-3">
                                    @if ($isWithinDepth)
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Yes</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-dash-circle me-1"></i>No (Exceeds depth)</span>
                                    @endif
                                </td>
                            </tr>
                            @if (!empty($row['members']) && count($row['members']) > 0)
                                <tr class="bg-light bg-opacity-50">
                                    <td colspan="9" class="ps-4 py-2 border-bottom">
                                        <div class="small">
                                            <span class="text-secondary fw-semibold">Level {{ $row['level'] }} members:</span>
                                            <div class="d-flex flex-wrap gap-2 mt-1">
                                                @foreach ($row['members'] as $m)
                                                    <span class="badge bg-white text-dark border py-1 px-2 d-inline-flex align-items-center gap-1">
                                                        <span class="fw-medium">{{ $m['name'] }}</span>
                                                        <span class="text-body-tertiary font-monospace" style="font-size: .7rem;">({{ $m['member_code'] }})</span>
                                                        @if ($m['position'])
                                                            <span class="badge bg-light text-secondary border-0" style="font-size: .65rem;">{{ strtoupper($m['position'][0]) }}</span>
                                                        @endif
                                                        <span class="badge bg-primary-subtle text-primary border-0" style="font-size: .65rem;">BV ₹{{ number_format($m['bv'], 0) }}</span>
                                                        @if ($m['income_to_root'] > 0)
                                                            <span class="badge bg-success-subtle text-success border-0" style="font-size: .65rem;">Paid: ₹{{ number_format($m['income_to_root'], 2) }}</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-body-tertiary py-4">No downline members found for this member yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="5" class="ps-3">Total Across Illustrated Levels</td>
                            <td class="text-end text-info-emphasis">₹{{ number_format($totalSponsor, 2) }}</td>
                            <td class="text-end" style="color: #6d28d9;">₹{{ number_format($totalMatching, 2) }}</td>
                            <td class="text-end text-primary">₹{{ number_format($totalLevelIncome, 2) }}</td>
                            <td class="pe-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Distribution Explanation Card --}}
    <div class="card border">
        <div class="card-body">
            <h2 class="h6 mb-2">How Income is Calculated for {{ $member->name }}</h2>
            <div class="row g-3 small text-secondary">
                <div class="col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="fw-bold text-dark mb-1">1. Direct Referrals (Level 1 Sponsor)</div>
                        <p class="mb-0">
                            Whenever a personally sponsored member purchases any bundle, {{ $member->name }} instantly receives
                            <strong>{{ $sponsorPct }}%</strong> of that order's BV as Sponsor Income.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="fw-bold text-dark mb-1">2. Binary Matching Cascade (Levels 1 to {{ $cascadeDepth }})</div>
                        <p class="mb-0">
                            All downline purchases add volume to {{ $member->name }}'s left or right leg.
                            Whenever volume is matched, and whenever downline members match their own legs, {{ $member->name }} receives
                            <strong>{{ $matchingPct }}%</strong> matching cascade income as long as the source is within <strong>{{ $cascadeDepth }}</strong> levels.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="fw-bold text-dark mb-1">3. Monthly & Weekly Safety Caps</div>
                        <p class="mb-0">
                            Matching payouts are capped at <strong>₹{{ number_format((float) \App\Models\Setting::get('match_cap_per_cycle', 600000), 0) }}</strong> per calendar month
                            and <strong>₹{{ number_format((float) \App\Models\Setting::get('match_cap_per_week', 150000), 0) }}</strong> per ISO week to ensure financial stability.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection