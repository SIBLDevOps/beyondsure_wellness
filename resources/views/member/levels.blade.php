@extends('layouts.member')

@section('title', 'Level-Wise Income')

@section('content')
    @include('member.partials.network-nav')

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success-subtle text-success px-2.5 py-1">Cascade Depth: {{ $cascadeDepth }} Generations</span>
                <span class="badge bg-light text-secondary border">Active Rule: 1:1 Matching</span>
            </div>
            <p class="text-secondary small mb-0">
                You earn generation-by-generation team matching bonuses down to <strong>Level {{ $cascadeDepth }}</strong>. Select a depth view below to inspect your network.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form method="GET" class="d-flex align-items-center gap-2 small text-secondary">
                <label class="mb-0 fw-medium">View Depth:</label>
                <select name="depth" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                    @foreach ([4, 5, 6, 7, 8, 10] as $d)
                        <option value="{{ $d }}" @selected(($depth ?? 7) == $d)>{{ $d }} Levels</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Summary Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="text-secondary small">Total Level Income Earned</div>
                        <div class="fs-4 fw-bold text-success">₹{{ number_format($totalLevelIncome ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-person-plus"></i></div>
                    <div>
                        <div class="text-secondary small">Direct Sponsor Income (L1)</div>
                        <div class="fs-4 fw-bold text-info-emphasis">₹{{ number_format($totalSponsor ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-bar-chart-steps"></i></div>
                    <div>
                        <div class="text-secondary small">Matching Cascade Income</div>
                        <div class="fs-4 fw-bold text-primary">₹{{ number_format($totalMatching ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-success"></i>Generation-by-Generation Level Breakdown</h2>
                <div class="text-body-tertiary small">Team headcount, active members, BV contribution, and income earned at each level</div>
            </div>
            <span class="badge bg-success-subtle text-success">Cascade depth: {{ $cascadeDepth }} Levels</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Level</th>
                        <th class="text-center">Members</th>
                        <th class="text-center">Active this month</th>
                        <th>Level Rate</th>
                        <th class="text-end">BV contributed</th>
                        <th class="text-end">Sponsor Income</th>
                        <th class="text-end">Matching Income</th>
                        <th class="text-end">Total Earned</th>
                        <th class="text-center pe-3">Pays me?</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $row)
                        @php
                            $isWithinDepth = $row['level'] <= $cascadeDepth;
                            $lvlPct = ($levelPcts ?? [])[$row['level']] ?? ($matchingPct ?? 10);
                        @endphp
                        <tr>
                            <td class="ps-3 fw-semibold">
                                <span class="badge {{ $isWithinDepth ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    Level {{ $row['level'] }}
                                </span>
                            </td>
                            <td class="text-center fw-medium">{{ $row['count'] }}</td>
                            <td class="text-center">{{ $row['active_count'] }}</td>
                            <td>
                                @if ($row['level'] === 1)
                                    <span class="badge bg-info-subtle text-info-emphasis">{{ $sponsorPct ?? 20 }}% Sponsor</span>
                                    <span class="badge bg-primary-subtle text-primary">{{ $lvlPct }}% Match</span>
                                @elseif ($isWithinDepth)
                                    <span class="badge bg-primary-subtle text-primary">{{ $lvlPct }}% Match</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">0%</span>
                                @endif
                            </td>
                            <td class="text-end">₹{{ number_format($row['bv'], 0) }}</td>
                            <td class="text-end text-info-emphasis">₹{{ number_format($row['sponsor_income'] ?? 0, 2) }}</td>
                            <td class="text-end text-primary">₹{{ number_format($row['matching_income'] ?? 0, 2) }}</td>
                            <td class="text-end fw-bold text-success">₹{{ number_format($row['total_income'] ?? 0, 2) }}</td>
                            <td class="text-center pe-3">
                                @if ($isWithinDepth)
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-lg"></i> Yes</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">No</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-body-tertiary py-4">No downline members found yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
