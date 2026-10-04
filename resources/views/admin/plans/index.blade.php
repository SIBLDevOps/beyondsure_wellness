@extends('layouts.admin')

@section('title', 'Plans')

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <p class="text-secondary mb-0" style="max-width: 620px;">
            Each plan bundle has a <strong>price</strong> (the taxable base, excl. GST — GST is added on top at
            checkout) and a <strong>BV</strong> — Business Volume, the amount every compensation type (Self,
            Sponsor, Matching, Rank) is calculated from. The two are independent numbers you set per plan; they
            don't have to move together.
        </p>
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="{{ route('admin.reports.levels') }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Level distribution</a>
            <a href="{{ route('admin.plans.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>New plan</a>
        </div>
    </div>

    {{-- 1. Plans Comparison & Compensation Matrix Table --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-layers me-2 text-primary"></i>Plans Comparison &amp; Compensation Matrix</h2>
                <div class="text-body-tertiary small">Side-by-side comparison of bundle pricing, actual cost vs cap, and BV payouts</div>
            </div>
            <span class="badge bg-primary-subtle text-primary">{{ $plans->count() }} Plans</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Plan Bundle</th>
                        <th class="text-end" data-sort-key="number">Price (excl. GST)</th>
                        <th class="text-end" data-sort-key="number">BV</th>
                        <th class="text-end" data-sort-key="number">Bundle Cost (Target {{ $costPct }}%)</th>
                        <th class="text-end" data-sort-key="number">Self ({{ $selfPct }}%)</th>
                        <th class="text-end" data-sort-key="number">Sponsor ({{ $sponsorPct }}%)</th>
                        <th class="text-end" data-sort-key="number">L1 Match ({{ $matchingPct }}%)</th>
                        <th class="text-end" data-sort-key="number">Cascade ({{ $cascadeDepth }} levels)</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        @php
                            $cost = $plan->costForQty();
                            $costTarget = $plan->price * $costPct / 100;
                            $overCap = $plan->is_active && $cost > $costTarget;
                            $selfAmt = $plan->bv * $selfPct / 100;
                            $sponsorAmt = $plan->bv * $sponsorPct / 100;
                            $matchingAmt = $plan->bv * $matchingPct / 100;
                            $totalCascadePct = isset($levelPcts) ? array_sum($levelPcts) : ($matchingPct * $cascadeDepth);
                            $totalCascadeAmt = $plan->bv * $totalCascadePct / 100;
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $plan->name }}</div>
                                <div class="text-body-tertiary text-truncate" style="font-size: .72rem; max-width: 200px;">
                                    {{ $plan->products->count() }} {{ Str::plural('item', $plan->products->count()) }}
                                </div>
                            </td>
                            <td class="text-end fw-bold" data-sort-value="{{ $plan->price }}">
                                ₹{{ number_format($plan->price, 0) }}
                                <div class="text-body-tertiary fw-normal" style="font-size: .68rem;">₹{{ number_format($plan->totalPayable(), 0) }} w/ GST</div>
                            </td>
                            <td class="text-end fw-semibold text-primary" data-sort-value="{{ $plan->bv }}">₹{{ number_format($plan->bv, 0) }}</td>
                            <td class="text-end" data-sort-value="{{ $cost }}">
                                <span class="{{ $overCap ? 'text-danger fw-semibold' : 'text-secondary' }}">
                                    ₹{{ number_format($cost, 0) }}
                                </span>
                                <span class="text-body-tertiary small">/ ₹{{ number_format($costTarget, 0) }}</span>
                                @if ($overCap)
                                    <span class="badge bg-danger-subtle text-danger ms-1">Over Cap</span>
                                @endif
                            </td>
                            <td class="text-end text-success fw-medium" data-sort-value="{{ $selfAmt }}">₹{{ number_format($selfAmt, 0) }}</td>
                            <td class="text-end text-info-emphasis fw-medium" data-sort-value="{{ $sponsorAmt }}">₹{{ number_format($sponsorAmt, 0) }}</td>
                            <td class="text-end fw-medium" style="color: #6d28d9;" data-sort-value="{{ $matchingAmt }}">₹{{ number_format($matchingAmt, 0) }}</td>
                            <td class="text-end fw-semibold" style="color: #6d28d9;" data-sort-value="{{ $totalCascadeAmt }}">
                                ₹{{ number_format($totalCascadeAmt, 0) }}
                                <div class="text-body-tertiary" style="font-size: .68rem;">{{ $totalCascadePct }}% BV</div>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $plan->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-body-tertiary py-5">No plans configured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 2. Detailed Visual Breakdown Cards --}}
    <div class="row g-4">
        @foreach ($plans as $plan)
            @php
                $cost = $plan->costForQty();
                $costTarget = $plan->price * $costPct / 100;
                $overCap = $plan->is_active && $cost > $costTarget;

                $selfAmt = $plan->bv * $selfPct / 100;
                $sponsorAmt = $plan->bv * $sponsorPct / 100;
                $matchingAmt = $plan->bv * $matchingPct / 100;
                $totalCascadePct = isset($levelPcts) ? array_sum($levelPcts) : ($matchingPct * $cascadeDepth);
                $totalCascadeAmt = $plan->bv * $totalCascadePct / 100;
            @endphp
            <div class="col-lg-6">
                <div class="card border h-100 hover-lift">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="fw-semibold fs-5">{{ $plan->name }}</div>
                                <span class="badge rounded-pill {{ $plan->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                        </div>

                        <div class="d-flex align-items-baseline gap-2 mt-3">
                            <span class="fs-3 fw-bold">₹{{ number_format($plan->price, 0) }}</span>
                            <span class="text-body-tertiary small">member pays</span>
                            <span class="ms-auto fw-medium text-primary small">BV ₹{{ number_format($plan->bv, 0) }}</span>
                        </div>

                        {{-- Price split visualization --}}
                        <div class="mt-3">
                            <div class="small text-secondary mb-1">Where the ₹{{ number_format($plan->price, 0) }} price goes (target split)</div>
                            <div class="split-bar" data-bs-toggle="tooltip" title="Cost {{ $costPct }}% · Pool {{ $poolPct }}% · Company {{ $companyPct }}%">
                                <div class="bg-{{ $overCap ? 'danger' : 'warning' }}" style="width: {{ $costPct }}%"></div>
                                <div class="bg-primary" style="width: {{ $poolPct }}%"></div>
                                <div class="bg-secondary" style="width: {{ $companyPct }}%"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-secondary mt-1 flex-wrap gap-1">
                                <span><span class="badge rounded-pill bg-{{ $overCap ? 'danger' : 'warning' }} bg-opacity-25 border border-{{ $overCap ? 'danger' : 'warning' }}">&nbsp;</span> Cost {{ $costPct }}% (₹{{ number_format($costTarget, 0) }})</span>
                                <span><span class="badge rounded-pill bg-primary bg-opacity-25 border border-primary">&nbsp;</span> Pool {{ $poolPct }}%</span>
                                <span><span class="badge rounded-pill bg-secondary bg-opacity-25 border border-secondary">&nbsp;</span> Company {{ $companyPct }}%</span>
                            </div>
                            <div class="small mt-1 {{ $overCap ? 'text-danger fw-medium' : 'text-body-tertiary' }}">
                                @if ($overCap)
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                @endif
                                Actual bundle cost: ₹{{ number_format($cost, 0) }}
                                {{ $overCap ? '— exceeds target by ₹' . number_format($cost - $costTarget, 0) : '(within target)' }}
                            </div>
                        </div>

                        {{-- Compensation preview --}}
                        <div class="mt-3 pt-3 border-top">
                            <div class="small text-secondary mb-2">What this plan pays out (from its ₹{{ number_format($plan->bv, 0) }} BV)</div>
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="bg-success-subtle rounded-3 py-2">
                                        <div class="fw-semibold text-success">₹{{ number_format($selfAmt, 0) }}</div>
                                        <div class="text-secondary" style="font-size: .7rem;">Self ({{ $selfPct }}%)</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-info-subtle rounded-3 py-2">
                                        <div class="fw-semibold text-info-emphasis">₹{{ number_format($sponsorAmt, 0) }}</div>
                                        <div class="text-secondary" style="font-size: .7rem;">Sponsor ({{ $sponsorPct }}%)</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="rounded-3 py-2" style="background: #ede9fe;">
                                        <div class="fw-semibold" style="color:#6d28d9;">₹{{ number_format($matchingAmt, 0) }}</div>
                                        <div class="text-secondary" style="font-size: .7rem;">L1 Match ({{ $matchingPct }}%)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top small" style="border-style: dashed !important;">
                                <span class="text-secondary" style="font-size: .75rem;">
                                    Cascade ({{ $cascadeDepth }} levels total):
                                </span>
                                <span class="fw-semibold" style="color: #6d28d9; font-size: .75rem;">
                                    Max ₹{{ number_format($totalCascadeAmt, 0) }} ({{ $totalCascadePct }}% BV)
                                </span>
                            </div>
                            @if (!empty($levelPcts))
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach ($levelPcts as $lvl => $pct)
                                        <span class="badge bg-light text-secondary border" style="font-size: .65rem;">
                                            L{{ $lvl }}: {{ $pct }}% (₹{{ number_format($plan->bv * $pct / 100, 0) }})
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 pt-2 border-top small text-secondary">
                            <span class="fw-medium text-body-secondary">Bundle:</span>
                            {{ $plan->products->map(fn ($p) => "{$p->pivot->qty}× {$p->name}")->join(', ') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection