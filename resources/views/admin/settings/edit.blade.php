@extends('layouts.admin')

@section('title', 'Settings — Compensation & Level Configuration')

@section('content')
    <p class="text-secondary mb-4" style="max-width: 720px;">
        This is where the entire compensation model is defined — every plan, every payout, and every report
        reads these numbers live. Configure individual percentages for each level, increase or decrease the number
        of levels, and use the live calculator below to verify your plan's math. See it visualized per-plan on the
        <a href="{{ route('admin.plans.index') }}" class="link-primary fw-medium text-decoration-none">Plans</a> page
        or in the <a href="{{ route('admin.reports.levels') }}" class="link-primary fw-medium text-decoration-none">Level-wise income report</a>.
    </p>

    @php
        $oldLevels = old('levels');
        $activeLevels = is_array($oldLevels) ? array_values($oldLevels) : array_values($levelPcts ?? [10, 10, 10, 10, 10, 10, 10]);
    @endphp

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border mb-4">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update') }}" id="settingsForm">
                        @csrf @method('PUT')

                        {{-- 0. Tax Assumption --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                0. Tax assumption
                            </legend>
                            <p class="text-body-tertiary small mb-3">
                                Every plan's price is the <strong>GST-inclusive MRP</strong> a member actually pays. This rate is
                                the <strong>company-wide default</strong> used to back taxable value out of that price — it's never
                                part of company margin or the compensation pool, it's collected on the government's behalf.
                                A product taxed at a different slab (e.g. 5% vs 18%) can be given its own rate on the
                                <a href="{{ route('admin.products.index') }}" class="link-primary text-decoration-none">Products</a>
                                page; a bundle's GST is then the weighted blend of whatever's actually inside it, not this one flat number.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-medium">Default GST rate (used when a product has none set)</label>
                                    <div class="input-group input-group-sm">
                                        <input name="gst_pct" type="number" step="0.01" min="0" max="100"
                                            value="{{ old('gst_pct', $values['gst_pct']) }}" class="form-control" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        {{-- 1. Revenue Split --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                1. Revenue split (target % of taxable value, i.e. MRP excl. GST)
                            </legend>
                            <p class="text-body-tertiary small mb-3">
                                How every ₹1 of <strong>taxable value</strong> (MRP with GST already removed) is divided: cost of
                                physical goods, the compensation pool (BV), and company margin. <strong>These three must add up to 100%.</strong>
                            </p>
                            <div class="row g-3">
                                @foreach (['split_cost_pct' => 'Cost of Goods (%)', 'split_pool_pct' => 'Pool / BV (%)', 'split_company_pct' => 'Company Margin (%)'] as $key => $label)
                                    <div class="col-md-4">
                                        <label class="form-label small fw-medium">{{ $label }}</label>
                                        <input name="{{ $key }}" id="input_{{ $key }}" type="number" step="0.01" min="0" max="100"
                                            value="{{ old($key, $values[$key]) }}" class="form-control form-control-sm split-input" required>
                                    </div>
                                @endforeach
                            </div>
                            <div id="splitGuidance" class="mt-2 small px-2 py-1 rounded-2"></div>
                        </fieldset>

                        {{-- 2. Base Compensation Rates --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                2. Direct & pool compensation rates (% of BV)
                            </legend>
                            <p class="text-body-tertiary small mb-3">
                                Calculated from a plan's <strong>BV</strong>. Self and Sponsor pay once per purchase;
                                Rank pool splits what's left among qualifying ranks at cycle approval.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-medium">Self income (% of BV)</label>
                                    <input name="self_pct" id="input_self_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('self_pct', $values['self_pct']) }}" class="form-control form-control-sm comp-input" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-medium">Direct Sponsor L1 (% of BV)</label>
                                    <input name="sponsor_pct" id="input_sponsor_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('sponsor_pct', $values['sponsor_pct']) }}" class="form-control form-control-sm comp-input" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-medium">Rank pool (% of monthly BV)</label>
                                    <input name="rank_pool_pct" id="input_rank_pool_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('rank_pool_pct', $values['rank_pool_pct']) }}" class="form-control form-control-sm comp-input" required>
                                </div>
                            </div>
                        </fieldset>

                        {{-- 3. Dynamic Level-Wise Percentage Configuration --}}
                        <fieldset class="border border-primary-subtle rounded-3 p-3 mb-4 bg-light bg-opacity-50">
                            <legend class="float-none w-auto small fw-bold text-primary px-2 mb-0" style="font-size: .82rem;">
                                <i class="bi bi-layers-half me-1"></i>3. Level-wise matching income distribution (% per level)
                            </legend>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <p class="text-secondary small mb-0">
                                    Set the exact percentage for each generation level. Add or remove levels anytime to increase or decrease your cascade depth.
                                </p>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: .75rem;" id="btnPresetTiered" title="Set 15%, 12%, 10%, 8%, 5%, 3%, 2% (Total 55%)">
                                        Preset: Tiered (55%)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: .75rem;" id="btnPresetFlat" title="Set 7 levels × 10%">
                                        Preset: Flat 10%
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive bg-white rounded-3 border mb-3">
                                <table class="table table-sm align-middle mb-0" id="levelsTable">
                                    <thead class="table-light small text-secondary">
                                        <tr>
                                            <th class="ps-3" style="width: 120px;">Level</th>
                                            <th>Generation / Beneficiary</th>
                                            <th style="width: 180px;">Payout (% of BV)</th>
                                            <th class="text-end pe-3" style="width: 90px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="levelsContainer">
                                        @foreach ($activeLevels as $idx => $pct)
                                            @php $lvlNum = $idx + 1; @endphp
                                            <tr class="level-row">
                                                <td class="ps-3">
                                                    <span class="badge bg-primary-subtle text-primary fw-semibold level-badge">Level {{ $lvlNum }}</span>
                                                </td>
                                                <td class="small text-secondary level-desc">
                                                    @if ($lvlNum === 1)
                                                        1st Generation (Matching Member / Direct Upline)
                                                    @else
                                                        Generation {{ $lvlNum }} Placement Ancestor
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" name="levels[]" step="0.01" min="0" max="100"
                                                            value="{{ $pct }}" class="form-control level-pct-input" required>
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-level" title="Decrease / Remove Level">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light small fw-semibold">
                                        <tr>
                                            <td class="ps-3">
                                                <span id="totalLevelsCount">{{ count($activeLevels) }}</span> Levels
                                            </td>
                                            <td>Total Level-Wise Cascade Distribution:</td>
                                            <td>
                                                <span id="totalLevelsPctBadge" class="badge bg-primary fs-6">0%</span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-sm btn-primary text-nowrap" id="btnAddLevel">
                                                    <i class="bi bi-plus-lg me-1"></i>Add Level
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Live Calculation Guidance Box --}}
                            <div id="calcGuidanceCard" class="rounded-3 p-3 border bg-white">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-bold small d-flex align-items-center gap-1">
                                        <i class="bi bi-calculator text-primary"></i>
                                        Live Compensation Calculation & Solvency Check
                                    </span>
                                    <span id="calcStatusBadge" class="badge">Checking...</span>
                                </div>

                                <div class="progress mb-2" style="height: 10px;">
                                    <div id="barSelf" class="progress-bar bg-success" title="Self Income"></div>
                                    <div id="barSponsor" class="progress-bar bg-info" title="Sponsor Income"></div>
                                    <div id="barLevels" class="progress-bar" style="background-color: #6d28d9;" title="Level-Wise Cascade"></div>
                                    <div id="barRank" class="progress-bar bg-warning" title="Rank Pool"></div>
                                </div>

                                <div class="row g-2 text-center small mb-2">
                                    <div class="col-3">
                                        <div class="border rounded py-1 bg-light">
                                            <div class="text-secondary" style="font-size: .7rem;">Self</div>
                                            <div class="fw-bold text-success" id="summarySelf">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded py-1 bg-light">
                                            <div class="text-secondary" style="font-size: .7rem;">Sponsor (L1)</div>
                                            <div class="fw-bold text-info-emphasis" id="summarySponsor">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded py-1 bg-light">
                                            <div class="text-secondary" style="font-size: .7rem;">Levels Sum</div>
                                            <div class="fw-bold" style="color: #6d28d9;" id="summaryLevels">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded py-1 bg-light">
                                            <div class="text-secondary" style="font-size: .7rem;">Rank Pool</div>
                                            <div class="fw-bold text-warning-emphasis" id="summaryRank">0%</div>
                                        </div>
                                    </div>
                                </div>

                                <div id="calcGuidanceMessage" class="small mt-2 p-2 rounded-2"></div>
                            </div>
                        </fieldset>

                        {{-- 4. Solvency Circuit Breaker --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                4. Solvency Protection &amp; Circuit Breaker
                            </legend>
                            <p class="text-body-tertiary small mb-3">
                                The platform automatically applies <strong>Global Solvency Protection</strong> so that total compensation paid can never exceed the cycle's available Pool BV budget.
                                The layer ceiling below caps how much matching is paid out when old carry-forward matches all at once. Set to <strong>100%</strong> to disable per-layer throttling (paying full percentage as long as cycle pool budget permits).
                            </p>
                            <div class="row g-3 align-items-center">
                                <div class="col-md-5">
                                    <label class="form-label small fw-medium mb-1">Max matching payout (% of layer's new BV)</label>
                                    <div class="input-group input-group-sm">
                                        <input name="circuit_breaker_pct" type="number" step="0.01" min="0" max="100"
                                            value="{{ old('circuit_breaker_pct', $values['circuit_breaker_pct']) }}" class="form-control" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text" style="font-size: .72rem;">Default: 50%–60%. Enter 100% to disable layer-level scaling.</div>
                                </div>
                            </div>
                        </fieldset>

                        {{-- 5. Matching Caps --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                5. Matching caps (₹, 0 disables)
                            </legend>
                            <p class="text-body-tertiary small mb-3">
                                Ceilings on how much matching income any one partner can earn — checked independently, so
                                both apply at once. A member's usage is shown on their own Wallet page.
                            </p>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small fw-medium">Monthly cap per partner (₹)</label>
                                    <input name="match_cap_per_cycle" type="number" step="0.01" min="0"
                                        value="{{ old('match_cap_per_cycle', $values['match_cap_per_cycle']) }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-medium">Weekly cap per partner (₹)</label>
                                    <input name="match_cap_per_week" type="number" step="0.01" min="0"
                                        value="{{ old('match_cap_per_week', $values['match_cap_per_week']) }}" class="form-control form-control-sm" required>
                                </div>
                            </div>
                        </fieldset>



                        <div class="d-flex align-items-center justify-content-between">
                            <button type="submit" class="btn btn-success px-4" id="btnSubmitSettings">
                                <i class="bi bi-check-lg me-1"></i>Save settings & level percentages
                            </button>
                            <a href="{{ route('admin.reports.levels') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-bar-chart-steps me-1"></i>View Level Distribution Report
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Guidance Reference Card --}}
            <div class="card border mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-1"></i>How to Set Proper Level %</h2>
                    <p class="text-secondary small mb-2">
                        To ensure you never overpay or run into deficit:
                    </p>
                    <ol class="small text-secondary ps-3 mb-3 d-grid gap-2">
                        <li>
                            <strong>Check Fixed Allocations:</strong> Self Income + Direct Sponsor + Rank Pool are paid first.
                            For example: <code>10% + 20% + 3% = 33%</code> of BV.
                        </li>
                        <li>
                            <strong>Remaining Level Budget:</strong> Subtract fixed allocations from <code>100%</code>.
                            In the example above, you have <code>100% - 33% = 67%</code> available to distribute across all levels.
                        </li>
                        <li>
                            <strong>Tapered vs Flat Levels:</strong>
                            <ul class="mt-1 ps-3">
                                <li><em>Tiered (Recommended):</em> Higher % on upper levels, tapering down (e.g. L1: 15%, L2: 12%, L3: 10%, L4: 8%, L5: 5%, L6: 3%, L7: 2% = 55%).</li>
                                <li><em>Flat:</em> Equal % on every level (e.g. 7 levels × 8% = 56%).</li>
                            </ul>
                        </li>
                        <li>
                            <strong>Increasing/Decreasing Levels:</strong>
                            Click <strong>+ Add Level</strong> to add deeper generations (up to 25), or click the trash icon to reduce depth.
                        </li>
                    </ol>
                </div>
            </div>

            {{-- Ranks Table --}}
            <div class="card border">
                <div class="card-body">
                    <h2 class="h6 mb-1">Ranks (seeded)</h2>
                    <p class="text-body-tertiary small mb-3">
                        A member qualifies for the highest rank they meet: active this month, matched BV at or above the
                        threshold, and at least the minimum active personal directs.
                    </p>
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="text-secondary">
                            <tr><th>Rank</th><th>Matched BV</th><th>Directs</th><th>Share</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($ranks as $rank)
                                <tr>
                                    <td class="fw-medium">{{ $rank->name }}</td>
                                    <td>₹{{ number_format($rank->matched_bv_threshold, 0) }}</td>
                                    <td>{{ $rank->min_active_directs }}</td>
                                    <td>{{ $rank->share }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const levelsContainer = document.getElementById('levelsContainer');
            const btnAddLevel = document.getElementById('btnAddLevel');
            const btnPresetTiered = document.getElementById('btnPresetTiered');
            const btnPresetFlat = document.getElementById('btnPresetFlat');

            const inputCost = document.getElementById('input_split_cost_pct');
            const inputPool = document.getElementById('input_split_pool_pct');
            const inputCompany = document.getElementById('input_split_company_pct');
            const splitGuidance = document.getElementById('splitGuidance');

            const inputSelf = document.getElementById('input_self_pct');
            const inputSponsor = document.getElementById('input_sponsor_pct');
            const inputRank = document.getElementById('input_rank_pool_pct');

            const totalLevelsCount = document.getElementById('totalLevelsCount');
            const totalLevelsPctBadge = document.getElementById('totalLevelsPctBadge');
            const calcStatusBadge = document.getElementById('calcStatusBadge');
            const calcGuidanceMessage = document.getElementById('calcGuidanceMessage');

            const summarySelf = document.getElementById('summarySelf');
            const summarySponsor = document.getElementById('summarySponsor');
            const summaryLevels = document.getElementById('summaryLevels');
            const summaryRank = document.getElementById('summaryRank');

            const barSelf = document.getElementById('barSelf');
            const barSponsor = document.getElementById('barSponsor');
            const barLevels = document.getElementById('barLevels');
            const barRank = document.getElementById('barRank');

            function renumberLevels() {
                const rows = levelsContainer.querySelectorAll('.level-row');
                rows.forEach((row, index) => {
                    const num = index + 1;
                    row.querySelector('.level-badge').textContent = 'Level ' + num;
                    row.querySelector('.level-desc').textContent = num === 1
                        ? '1st Generation (Matching Member / Direct Upline)'
                        : 'Generation ' + num + ' Placement Ancestor';
                    const removeBtn = row.querySelector('.btn-remove-level');
                    removeBtn.disabled = rows.length <= 1;
                });
                totalLevelsCount.textContent = rows.length;
                recalculateAll();
            }

            function createLevelRow(pctValue) {
                const tr = document.createElement('tr');
                tr.className = 'level-row';
                tr.innerHTML = `
                    <td class="ps-3">
                        <span class="badge bg-primary-subtle text-primary fw-semibold level-badge">Level</span>
                    </td>
                    <td class="small text-secondary level-desc"></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" name="levels[]" step="0.01" min="0" max="100"
                                value="${pctValue}" class="form-control level-pct-input" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-level" title="Decrease / Remove Level">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tr.querySelector('.level-pct-input').addEventListener('input', recalculateAll);
                tr.querySelector('.btn-remove-level').addEventListener('click', function () {
                    if (levelsContainer.querySelectorAll('.level-row').length > 1) {
                        tr.remove();
                        renumberLevels();
                    }
                });
                return tr;
            }

            function recalculateAll() {
                // 1. Check Revenue Split
                const cost = parseFloat(inputCost.value) || 0;
                const pool = parseFloat(inputPool.value) || 0;
                const company = parseFloat(inputCompany.value) || 0;
                const splitSum = Math.round((cost + pool + company) * 100) / 100;

                if (Math.abs(splitSum - 100) > 0.01) {
                    const diff = Math.round((100 - splitSum) * 100) / 100;
                    splitGuidance.className = 'mt-2 small px-2 py-1 rounded-2 bg-danger-subtle text-danger fw-medium';
                    splitGuidance.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-1"></i>Wrong Split Sum: ${cost}% + ${pool}% + ${company}% = <strong>${splitSum}%</strong> (Must equal 100%. ${diff > 0 ? 'Add ' + diff + '%' : 'Reduce by ' + Math.abs(diff) + '%'}).`;
                } else {
                    splitGuidance.className = 'mt-2 small px-2 py-1 rounded-2 bg-success-subtle text-success';
                    splitGuidance.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Revenue split equals <strong>100%</strong>.`;
                }

                // 2. Check Level & Compensation Sum
                const selfPct = parseFloat(inputSelf.value) || 0;
                const sponsorPct = parseFloat(inputSponsor.value) || 0;
                const rankPct = parseFloat(inputRank.value) || 0;

                let levelsSum = 0;
                let hasInvalidLevel = false;
                const pctInputs = levelsContainer.querySelectorAll('.level-pct-input');
                pctInputs.forEach(inp => {
                    const val = parseFloat(inp.value);
                    if (isNaN(val) || val < 0 || val > 100) {
                        hasInvalidLevel = true;
                    } else {
                        levelsSum += val;
                    }
                });

                levelsSum = Math.round(levelsSum * 100) / 100;
                const fixedSum = Math.round((selfPct + sponsorPct + rankPct) * 100) / 100;
                const availableForLevels = Math.max(0, Math.round((100 - fixedSum) * 100) / 100);
                const totalComp = Math.round((fixedSum + levelsSum) * 100) / 100;

                totalLevelsPctBadge.textContent = levelsSum + '%';
                summarySelf.textContent = selfPct + '%';
                summarySponsor.textContent = sponsorPct + '%';
                summaryLevels.textContent = levelsSum + '%';
                summaryRank.textContent = rankPct + '%';

                const maxBar = Math.max(100, totalComp);
                barSelf.style.width = Math.max(0, (selfPct / maxBar) * 100) + '%';
                barSponsor.style.width = Math.max(0, (sponsorPct / maxBar) * 100) + '%';
                barLevels.style.width = Math.max(0, (levelsSum / maxBar) * 100) + '%';
                barRank.style.width = Math.max(0, (rankPct / maxBar) * 100) + '%';

                if (hasInvalidLevel || selfPct < 0 || sponsorPct < 0 || rankPct < 0) {
                    calcStatusBadge.className = 'badge bg-danger';
                    calcStatusBadge.textContent = 'Invalid Value';
                    calcGuidanceMessage.className = 'small mt-2 p-2 rounded-2 bg-danger-subtle text-danger';
                    calcGuidanceMessage.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i><strong>Error:</strong> All percentages must be valid numbers between 0% and 100%.`;
                } else if (totalComp > 100) {
                    const overBy = Math.round((totalComp - 100) * 100) / 100;
                    calcStatusBadge.className = 'badge bg-warning text-dark';
                    calcStatusBadge.textContent = `Over Budget (${totalComp}% of BV)`;
                    calcGuidanceMessage.className = 'small mt-2 p-2 rounded-2 bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                    calcGuidanceMessage.innerHTML = `
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Calculation Guidance — Over-Allocated by ${overBy}%</div>
                        <div>
                            Fixed payouts (Self ${selfPct}% + Sponsor ${sponsorPct}% + Rank ${rankPct}%) use <strong>${fixedSum}%</strong> of BV,
                            leaving <strong>${availableForLevels}%</strong> available for levels.
                            Your ${pctInputs.length} levels currently total <strong>${levelsSum}%</strong> (Combined: <strong>${totalComp}%</strong> of BV).
                            <br><strong>Recommendation:</strong> Reduce level percentages by <strong>${overBy}%</strong> (so levels total ≤ ${availableForLevels}%) for 100% self-funded sustainability, or rely on weekly/monthly caps to limit exposure.
                        </div>
                    `;
                } else if (Math.abs(totalComp - 100) <= 0.01) {
                    calcStatusBadge.className = 'badge bg-success';
                    calcStatusBadge.textContent = '100% Fully Allocated';
                    calcGuidanceMessage.className = 'small mt-2 p-2 rounded-2 bg-success-subtle text-success';
                    calcGuidanceMessage.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i><strong>Perfect 100% Balance:</strong> Self (${selfPct}%) + Sponsor (${sponsorPct}%) + ${pctInputs.length} Levels (${levelsSum}%) + Rank (${rankPct}%) = <strong>100% of BV</strong>. Every rupee of BV is accounted for.`;
                } else {
                    const reserve = Math.round((100 - totalComp) * 100) / 100;
                    calcStatusBadge.className = 'badge bg-success';
                    calcStatusBadge.textContent = `Safe & Sustainable (${totalComp}% of BV)`;
                    calcGuidanceMessage.className = 'small mt-2 p-2 rounded-2 bg-success-subtle text-success';
                    calcGuidanceMessage.innerHTML = `<i class="bi bi-shield-check me-1"></i><strong>Healthy Calculation:</strong> Total maximum payout is <strong>${totalComp}% of BV</strong> across ${pctInputs.length} levels. This leaves a safe <strong>${reserve}% BV reserve buffer</strong> (you can add up to ${reserve}% more across levels if desired).`;
                }
            }

            // Attach listeners to existing rows
            levelsContainer.querySelectorAll('.level-row').forEach(row => {
                row.querySelector('.level-pct-input').addEventListener('input', recalculateAll);
                row.querySelector('.btn-remove-level').addEventListener('click', function () {
                    if (levelsContainer.querySelectorAll('.level-row').length > 1) {
                        row.remove();
                        renumberLevels();
                    }
                });
            });

            btnAddLevel.addEventListener('click', function () {
                const count = levelsContainer.querySelectorAll('.level-row').length;
                if (count >= 25) {
                    alert('Maximum depth of 25 levels reached.');
                    return;
                }
                const newRow = createLevelRow(5);
                levelsContainer.appendChild(newRow);
                renumberLevels();
            });

            btnPresetTiered.addEventListener('click', function () {
                const tiered = [15, 12, 10, 8, 5, 3, 2];
                levelsContainer.innerHTML = '';
                tiered.forEach(val => levelsContainer.appendChild(createLevelRow(val)));
                renumberLevels();
            });

            btnPresetFlat.addEventListener('click', function () {
                const flat = [10, 10, 10, 10, 10, 10, 10];
                levelsContainer.innerHTML = '';
                flat.forEach(val => levelsContainer.appendChild(createLevelRow(val)));
                renumberLevels();
            });

            [inputCost, inputPool, inputCompany, inputSelf, inputSponsor, inputRank].forEach(el => {
                if (el) el.addEventListener('input', recalculateAll);
            });

            renumberLevels();
        });
    </script>
@endsection
