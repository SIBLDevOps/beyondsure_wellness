@extends('layouts.member')

@section('title', 'Member Dashboard')

@section('content')
    @if (!$member->hasBankDetails())
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-3 py-2 mb-4">
            <div><i class="bi bi-exclamation-triangle-fill me-1"></i> Add your bank &amp; UPI payout details to enable quick wallet withdrawals.</div>
            <a href="{{ route('member.wallet.index') }}" class="btn btn-sm btn-warning flex-shrink-0"><i class="bi bi-bank me-1"></i>Complete Bank KYC</a>
        </div>
    @endif

    @if (($pendingOrderCount ?? 0) > 0)
        <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-3 py-2 mb-4">
            <div><i class="bi bi-bag-exclamation-fill me-1"></i> You have <strong>{{ $pendingOrderCount }}</strong> order(s) awaiting payment completion.</div>
            <a href="{{ route('member.orders.index') }}" class="btn btn-sm btn-info text-white flex-shrink-0"><i class="bi bi-credit-card me-1"></i>Complete Payment</a>
        </div>
    @endif

    {{-- Welcome & Referral Share Banner --}}
    <div class="card border mb-4" style="background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 65%);">
        <div class="card-body py-3 px-3 px-md-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar-circle bg-success shadow-sm d-none d-sm-inline-flex" style="width:54px;height:54px;font-size:1.1rem;">
                            {{ strtoupper(substr($member->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <h2 class="h6 h5-sm mb-0 fw-bold text-truncate">Welcome back, {{ $member->name }}</h2>
                            <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                <span class="badge rounded-pill {{ $member->status === 'active' ? 'bg-success text-white' : 'bg-secondary-subtle text-secondary' }} text-capitalize">
                                    {{ $member->status }}
                                </span>
                                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle text-capitalize">
                                    <i class="bi bi-award-fill me-1"></i>{{ $member->rank }} Rank
                                </span>
                            </div>
                            <div class="text-secondary small mt-1">
                                Code: <strong class="font-monospace text-dark">{{ $member->member_code }}</strong>
                                <span class="d-none d-sm-inline mx-1 text-body-tertiary">·</span>
                                <span class="d-none d-sm-inline">Joined {{ $member->created_at?->format('d M Y') ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    @php $referralUrl = route('register', ['sponsor' => $member->member_code]); @endphp
                    <div class="bg-white border rounded-3 p-2 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small fw-semibold text-secondary"><i class="bi bi-share-fill text-success me-1"></i>Your Referral Link</span>
                            <span class="badge bg-success-subtle text-success font-monospace d-none d-sm-inline">Sponsor: {{ $member->member_code }}</span>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <input type="text" readonly value="{{ $referralUrl }}" class="form-control form-control-sm bg-light font-monospace text-secondary text-truncate">
                            <div class="d-flex gap-2 flex-shrink-0">
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 flex-sm-grow-0" data-copy-text="{{ $member->member_code }}">
                                    <i class="bi bi-person-badge me-1"></i>Code
                                </button>
                                <button type="button" class="btn btn-sm btn-success flex-grow-1 flex-sm-grow-0" data-copy-text="{{ $referralUrl }}">
                                    <i class="bi bi-clipboard-check me-1"></i>Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 6 KPI Cards --}}
    @php
        $leftBv = (float) ($legTotal->left_bv ?? 0);
        $rightBv = (float) ($legTotal->right_bv ?? 0);
        $matchedBv = (float) ($legTotal->matched_bv ?? 0);
        $carryLeft = max(0, $leftBv - $matchedBv);
        $carryRight = max(0, $rightBv - $matchedBv);
        $maxLegBv = max($leftBv, $rightBv, 1);
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border h-100 hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bg-success-subtle text-success" style="width:36px;height:36px;font-size:1rem;"><i class="bi bi-wallet2"></i></div>
                        <a href="{{ route('member.wallet.index') }}" class="small link-success text-decoration-none">Wallet &rarr;</a>
                    </div>
                    <div class="text-secondary small">Wallet Balance</div>
                    <div class="fs-5 fw-bold text-success">₹{{ number_format($walletBalance, 2) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">Available to withdraw</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border h-100 hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bg-primary-subtle text-primary" style="width:36px;height:36px;font-size:1rem;"><i class="bi bi-graph-up-arrow"></i></div>
                        <a href="{{ route('member.reports.index') }}" class="small link-primary text-decoration-none">Report &rarr;</a>
                    </div>
                    <div class="text-secondary small">Total Earned</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($totalEarned ?? 0, 0) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">₹{{ number_format($totalWithdrawn ?? 0, 0) }} withdrawn</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border h-100 hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bg-warning-subtle text-warning-emphasis" style="width:36px;height:36px;font-size:1rem;"><i class="bi bi-bag-check"></i></div>
                        <a href="{{ route('member.orders.index') }}" class="small link-secondary text-decoration-none">Orders &rarr;</a>
                    </div>
                    <div class="text-secondary small">Personal BV</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($personalBv ?? 0, 0) }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">{{ $orderCount }} confirmed order(s)</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border h-100 hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bg-info-subtle text-info-emphasis" style="width:36px;height:36px;font-size:1rem;"><i class="bi bi-people"></i></div>
                        <a href="{{ route('member.team.index') }}" class="small link-info text-decoration-none">Team &rarr;</a>
                    </div>
                    <div class="text-secondary small">Direct Referrals</div>
                    <div class="fs-5 fw-bold">{{ $directCount }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">{{ $activeDirectCount ?? 0 }} active member(s)</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border h-100 hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bg-success-subtle text-success" style="width:36px;height:36px;font-size:1rem;"><i class="bi bi-diagram-3"></i></div>
                        <a href="{{ route('member.downline.index') }}" class="small link-success text-decoration-none">Tree &rarr;</a>
                    </div>
                    <div class="text-secondary small">Left / Right Leg BV</div>
                    <div class="fs-6 fw-bold"><span class="text-info-emphasis">L ₹{{ number_format($leftBv, 0) }}</span> <span class="text-body-tertiary">|</span> <span class="text-success">R ₹{{ number_format($rightBv, 0) }}</span></div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">Matched ₹{{ number_format($matchedBv, 0) }} · carry-forward ₹{{ number_format($carryLeft + $carryRight, 0) }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border h-100 hover-lift">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bg-warning-subtle text-warning-emphasis" style="width:36px;height:36px;font-size:1rem;"><i class="bi bi-award"></i></div>
                        <a href="{{ route('member.levels.index') }}" class="small link-secondary text-decoration-none">Levels &rarr;</a>
                    </div>
                    <div class="text-secondary small">Current Rank</div>
                    <div class="fs-5 fw-bold text-capitalize">{{ $member->rank }}</div>
                    <div class="text-body-tertiary" style="font-size: .72rem;">{{ $member->hasBankDetails() ? 'Bank KYC verified' : 'Bank KYC pending' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dedicated Income Streams Breakdown (Direct, Matching, Self, Rank) --}}
    @php
        $safeTotalEarned = max(0.01, $totalEarned ?? 0);
        $directPct = round(($directIncome / $safeTotalEarned) * 100, 1);
        $matchingPct = round(($matchingIncome / $safeTotalEarned) * 100, 1);
        $selfPct = round(($selfIncome / $safeTotalEarned) * 100, 1);
        $rankPct = round(($rankIncome / $safeTotalEarned) * 100, 1);
    @endphp
    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold">
                    <i class="bi bi-cash-stack me-2 text-success"></i>My Incomes &amp; Commission Breakdown
                </h2>
                <div class="text-body-tertiary small">Transparent view of all earnings credited to your wallet across 4 revenue streams</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-secondary border">Total Earned: <strong class="text-dark font-monospace">₹{{ number_format($totalEarned ?? 0, 2) }}</strong></span>
                <a href="{{ route('member.reports.index') }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-graph-up me-1"></i>Income Report
                </a>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                {{-- 1. Direct Sponsor Income --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="p-3 rounded-3 border bg-light bg-opacity-25 h-100 hover-lift">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Direct Sponsor</span>
                            <span class="small fw-semibold text-secondary">20% Bonus</span>
                        </div>
                        <div class="fs-4 fw-bold text-info-emphasis mb-1">₹{{ number_format($directIncome, 2) }}</div>
                        <div class="text-secondary small mb-2" style="font-size: .78rem;">Earned from orders placed by personally sponsored direct partners.</div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: {{ $totalEarned > 0 ? $directPct : 0 }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-body-tertiary mt-1" style="font-size: .72rem;">
                            <span>Share of Total</span>
                            <span>{{ $totalEarned > 0 ? $directPct : 0 }}%</span>
                        </div>
                    </div>
                </div>

                {{-- 2. Binary Matching & Cascade --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="p-3 rounded-3 border bg-light bg-opacity-25 h-100 hover-lift">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge border" style="background:#ede9fe; color:#6d28d9; border-color:#ddd6fe !important;">Binary Matching</span>
                            <span class="small fw-semibold text-secondary">1:1 Leg Match</span>
                        </div>
                        <div class="fs-4 fw-bold mb-1" style="color: #6d28d9;">₹{{ number_format($matchingIncome, 2) }}</div>
                        <div class="text-secondary small mb-2" style="font-size: .78rem;">Earned when Left &amp; Right leg volumes match plus upline cascade tiers.</div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar" style="background:#6d28d9; width: {{ $totalEarned > 0 ? $matchingPct : 0 }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-body-tertiary mt-1" style="font-size: .72rem;">
                            <span>Share of Total</span>
                            <span>{{ $totalEarned > 0 ? $matchingPct : 0 }}%</span>
                        </div>
                    </div>
                </div>

                {{-- 3. Buyer Self Purchase Cashback --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="p-3 rounded-3 border bg-light bg-opacity-25 h-100 hover-lift">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Self Cashback</span>
                            <span class="small fw-semibold text-secondary">10% BV Rebate</span>
                        </div>
                        <div class="fs-4 fw-bold text-success mb-1">₹{{ number_format($selfIncome, 2) }}</div>
                        <div class="text-secondary small mb-2" style="font-size: .78rem;">Direct bonus credited right back to you on every personal plan order.</div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: {{ $totalEarned > 0 ? $selfPct : 0 }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-body-tertiary mt-1" style="font-size: .72rem;">
                            <span>Share of Total</span>
                            <span>{{ $totalEarned > 0 ? $selfPct : 0 }}%</span>
                        </div>
                    </div>
                </div>

                {{-- 4. Leadership Rank Pool --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="p-3 rounded-3 border bg-light bg-opacity-25 h-100 hover-lift">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Rank Pool</span>
                            <span class="small fw-semibold text-secondary">3% Leadership</span>
                        </div>
                        <div class="fs-4 fw-bold text-warning-emphasis mb-1">₹{{ number_format($rankIncome, 2) }}</div>
                        <div class="text-secondary small mb-2" style="font-size: .78rem;">Monthly company pool distribution for Silver, Gold, Platinum &amp; Diamond.</div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: {{ $totalEarned > 0 ? $rankPct : 0 }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-body-tertiary mt-1" style="font-size: .72rem;">
                            <span>Share of Total</span>
                            <span>{{ $totalEarned > 0 ? $rankPct : 0 }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            @if ($totalEarned > 0)
                {{-- Consolidated Distribution Strip --}}
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center justify-content-between small mb-1 text-secondary">
                        <span class="fw-medium">Cumulative Income Portfolio:</span>
                        <span>₹{{ number_format($totalEarned, 2) }} Total Earned</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info" style="width: {{ $directPct }}%" title="Direct Sponsor: ₹{{ number_format($directIncome, 2) }} ({{ $directPct }}%)"></div>
                        <div class="progress-bar" style="background:#6d28d9; width: {{ $matchingPct }}%" title="Matching: ₹{{ number_format($matchingIncome, 2) }} ({{ $matchingPct }}%)"></div>
                        <div class="progress-bar bg-success" style="width: {{ $selfPct }}%" title="Self Cashback: ₹{{ number_format($selfIncome, 2) }} ({{ $selfPct }}%)"></div>
                        <div class="progress-bar bg-warning" style="width: {{ $rankPct }}%" title="Rank Pool: ₹{{ number_format($rankIncome, 2) }} ({{ $rankPct }}%)"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small text-secondary mt-2" style="font-size: .75rem;">
                        <span><i class="bi bi-circle-fill text-info me-1" style="font-size: .6rem;"></i>Direct Sponsor (₹{{ number_format($directIncome, 0) }})</span>
                        <span><i class="bi bi-circle-fill me-1" style="color: #6d28d9; font-size: .6rem;"></i>Matching &amp; Cascade (₹{{ number_format($matchingIncome, 0) }})</span>
                        <span><i class="bi bi-circle-fill text-success me-1" style="font-size: .6rem;"></i>Self Cashback (₹{{ number_format($selfIncome, 0) }})</span>
                        <span><i class="bi bi-circle-fill text-warning me-1" style="font-size: .6rem;"></i>Rank Leadership (₹{{ number_format($rankIncome, 0) }})</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Row 1: Account & Upline Details + Binary Leg Volume Table --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-person-vcard me-2 text-success"></i>My Account &amp; Upline Profile</h2>
                        <div class="text-body-tertiary small">Your registration, sponsor, and binary placement details</div>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace">{{ $member->member_code }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody class="small">
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal bg-light-subtle" style="width: 38%;">Member Code</th>
                                <td class="pe-3 py-2 font-monospace fw-semibold">{{ $member->member_code }}</td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal bg-light-subtle">Mobile / Email</th>
                                <td class="pe-3 py-2">
                                    <span>{{ $member->phone }}</span>
                                    @if ($member->email)
                                        <span class="text-body-tertiary ms-1">· {{ $member->email }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal bg-light-subtle">Direct Sponsor</th>
                                <td class="pe-3 py-2">
                                    @if ($member->sponsor)
                                        <span class="fw-medium">{{ $member->sponsor->name }}</span>
                                        <span class="font-monospace text-body-tertiary">({{ $member->sponsor->member_code }})</span>
                                    @else
                                        <span class="text-body-tertiary">— (Root Member)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal bg-light-subtle">Binary Placement Parent</th>
                                <td class="pe-3 py-2">
                                    @if ($member->placementParent)
                                        <span class="fw-medium">{{ $member->placementParent->name }}</span>
                                        @if ($member->position)
                                            <span class="badge bg-{{ $member->position === 'left' ? 'info' : 'success' }}-subtle text-{{ $member->position === 'left' ? 'info-emphasis' : 'success' }} text-uppercase ms-1">{{ $member->position }} leg</span>
                                        @endif
                                    @else
                                        <span class="text-body-tertiary">— (Root Node)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2 text-secondary fw-normal bg-light-subtle border-bottom-0">Payout Bank KYC</th>
                                <td class="pe-3 py-2 border-bottom-0">
                                    @if ($member->hasBankDetails())
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>{{ $member->bank_name }} (•••{{ substr($member->bank_account_number ?? '', -4) }})</span>
                                    @else
                                        <a href="{{ route('member.wallet.index') }}" class="badge bg-warning-subtle text-warning-emphasis text-decoration-none">Add Bank Details &rarr;</a>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Binary Leg Volume &amp; Matching</h2>
                        <div class="text-body-tertiary small">Live Left vs. Right leg BV and carry-forward balance</div>
                    </div>
                    <a href="{{ route('member.downline.index') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-diagram-3 me-1"></i>Open Tree</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Leg / Metric</th>
                                <th style="width: 36%;">Volume Ratio</th>
                                <th class="text-end">Total BV</th>
                                <th class="text-end pe-3">Carry Forward</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <tr>
                                <td class="ps-3 fw-medium"><span class="badge bg-info-subtle text-info-emphasis me-1">LEFT</span> Left Leg</td>
                                <td>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-info" style="width: {{ min(100, $leftBv / $maxLegBv * 100) }}%"></div>
                                    </div>
                                </td>
                                <td class="text-end fw-semibold">₹{{ number_format($leftBv, 0) }}</td>
                                <td class="text-end pe-3 text-secondary">₹{{ number_format($carryLeft, 0) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3 fw-medium"><span class="badge bg-success-subtle text-success me-1">RIGHT</span> Right Leg</td>
                                <td>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: {{ min(100, $rightBv / $maxLegBv * 100) }}%"></div>
                                    </div>
                                </td>
                                <td class="text-end fw-semibold">₹{{ number_format($rightBv, 0) }}</td>
                                <td class="text-end pe-3 text-secondary">₹{{ number_format($carryRight, 0) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light small">
                            <tr>
                                <th class="ps-3 py-2" colspan="2">Total Matched Volume (1:1 Binary Pair)</th>
                                <th class="text-end py-2 text-primary fs-6 pe-3" colspan="2">₹{{ number_format($matchedBv, 0) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center small pt-2">
                    <span class="text-body-tertiary">Unmatched BV carries forward automatically to the next cycle.</span>
                    <a href="{{ route('member.levels.index') }}" class="link-success fw-medium text-decoration-none">Level Rates &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 2: Recent Earnings & Recent Orders Tables --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-journal-check me-2 text-success"></i>Recent Wallet Activity</h2>
                        <div class="text-body-tertiary small">Latest compensation credits &amp; payouts</div>
                    </div>
                    <a href="{{ route('member.wallet.index') }}" class="small link-success fw-medium text-decoration-none">Full ledger &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-end pe-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @php
                                $typeConfigs = [
                                    'self' => ['label' => 'Self Cashback', 'class' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-bag-check'],
                                    'sponsor' => ['label' => 'Direct Sponsor', 'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'icon' => 'bi-person-plus'],
                                    'matching' => ['label' => 'Binary Match', 'class' => 'border', 'style' => 'background:#ede9fe; color:#6d28d9; border-color:#ddd6fe !important;', 'icon' => 'bi-diagram-3'],
                                    'rank' => ['label' => 'Rank Pool', 'class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'icon' => 'bi-trophy'],
                                    'withdrawal' => ['label' => 'Withdrawal', 'class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-bank'],
                                ];
                            @endphp
                            @forelse ($recentLedger ?? [] as $entry)
                                @php
                                    $cfg = $typeConfigs[$entry->type] ?? [
                                        'label' => ucfirst($entry->type),
                                        'class' => 'bg-secondary-subtle text-secondary',
                                        'icon' => 'bi-journal-text',
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3 text-body-tertiary text-nowrap">{{ $entry->created_at->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge rounded-pill {{ $cfg['class'] }}" @if(isset($cfg['style'])) style="{{ $cfg['style'] }}" @endif>
                                            <i class="bi {{ $cfg['icon'] }} me-1"></i>{{ $cfg['label'] }}
                                        </span>
                                    </td>
                                    <td class="text-secondary text-truncate" style="max-width: 200px;">{{ $entry->description }}</td>
                                    <td class="text-end pe-3 fw-semibold {{ $entry->amount < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $entry->amount >= 0 ? '+' : '' }}₹{{ number_format($entry->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-tertiary py-4">No wallet activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-primary"></i>Recent Plan Orders</h2>
                        <div class="text-body-tertiary small">Your latest wellness bundle purchases</div>
                    </div>
                    <a href="{{ route('member.orders.index') }}" class="small link-success fw-medium text-decoration-none">All orders &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Order</th>
                                <th>Plan</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentOrders ?? [] as $order)
                                @php
                                    $statusColor = match ($order->status) {
                                        'settled' => 'success',
                                        'paid' => 'info',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-3 font-monospace fw-semibold">{{ $order->order_code }}</td>
                                    <td>{{ $order->plan->name }}</td>
                                    <td class="text-end fw-semibold">₹{{ number_format($order->amount, 0) }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-{{ $statusColor }}-subtle text-{{ $statusColor === 'warning' || $statusColor === 'info' ? $statusColor.'-emphasis' : $statusColor }}">
                                            {{ str_replace('_', ' ', $order->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        @if ($order->status === 'pending_payment')
                                            <a href="{{ route('member.orders.pay', $order) }}" class="btn btn-sm btn-success py-0 px-2">Pay</a>
                                        @else
                                            <span class="text-body-tertiary">{{ $order->created_at->format('d M') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-body-tertiary py-4">
                                        No orders yet — <a href="{{ route('member.plans.index') }}" class="link-success fw-medium">browse plans</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
