@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    @php
        $oldLevels = old('levels');
        $activeLevels = is_array($oldLevels) ? array_values($oldLevels) : array_values($levelPcts ?? [10, 10, 10, 10, 10, 10, 10]);
        $sections = [
            ['registration', 'bi-person-plus', 'Registration'],
            ['tax', 'bi-receipt', 'Tax (GST)'],
            ['split', 'bi-pie-chart', 'Revenue split'],
            ['rates', 'bi-percent', 'Direct & pool rates'],
            ['levels', 'bi-layers-half', 'Level-wise matching'],
            ['breaker', 'bi-shield-check', 'Circuit breaker'],
            ['caps', 'bi-speedometer2', 'Matching caps'],
            ['ranks', 'bi-trophy', 'Ranks'],
        ];
    @endphp

    <style>
        .settings-nav .list-group-item { border: 0; border-radius: .6rem; padding: .55rem .8rem; font-size: .86rem; color: var(--bs-secondary-color); display: flex; align-items: center; gap: .6rem; }
        .settings-nav .list-group-item i { font-size: 1rem; width: 1.1rem; text-align: center; }
        .settings-nav .list-group-item:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
        .settings-nav .list-group-item.active { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); font-weight: 600; }
        .settings-card { scroll-margin-top: 80px; }
        .settings-card > .card-header { background: transparent; padding: 1rem 1.25rem .9rem; display: flex; gap: .85rem; align-items: flex-start; }
        .settings-card .chip { width: 2.3rem; height: 2.3rem; border-radius: .65rem; display: grid; place-items: center; flex: none; font-size: 1.1rem; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
        .settings-card .chip.warn { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
        .settings-card .chip.good { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
        .settings-card .card-title { font-size: 1rem; font-weight: 650; margin: 0; }
        .settings-card .card-sub { color: var(--bs-secondary-color); font-size: .83rem; margin: .15rem 0 0; max-width: 68ch; line-height: 1.5; }
        .settings-card .field-label { font-size: .82rem; font-weight: 600; margin-bottom: .3rem; }
        .settings-card .field-hint { font-size: .75rem; color: var(--bs-secondary-color); margin-top: .3rem; }
        .settings-card .form-control, .settings-card .input-group-text { font-variant-numeric: tabular-nums; }
        .split-visual { display: flex; height: 12px; border-radius: 99px; overflow: hidden; background: var(--bs-tertiary-bg); }
        .split-visual > div { transition: width .2s ease; }
        .save-bar { position: sticky; bottom: 0; z-index: 20; margin-top: 1.25rem; padding: .85rem 1.1rem; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .9rem; box-shadow: 0 -6px 18px rgba(0, 0, 0, .06); display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .dirty-pill { display: none; align-items: center; gap: .4rem; font-size: .8rem; color: var(--bs-warning-text-emphasis); background: var(--bs-warning-bg-subtle); padding: .25rem .65rem; border-radius: 99px; }
        .dirty-pill.show { display: inline-flex; }
        @media (max-width: 991.98px) { .settings-nav-wrap { display: none; } }
        @media (max-width: 575.98px) {
            .save-bar { flex-wrap: nowrap; padding: .6rem .75rem; border-radius: .75rem; }
            .save-bar #saveHint { display: none; }
            .save-bar .btn { margin-left: auto; white-space: nowrap; }
            .settings-card > .card-header { padding: .9rem 1rem .75rem; }
            .settings-card .card-body { padding-inline: 1rem; }
        }
    </style>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <p class="text-secondary mb-0" style="max-width: 680px;">
            Every plan, payout and report reads these numbers live. Changes apply from the <strong>next cycle approval</strong>;
            cycles already approved are never rewritten.
        </p>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-seam me-1"></i>Plans</a>
            <a href="{{ route('admin.reports.levels') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-steps me-1"></i>Level-wise report</a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Section navigation --}}
        <div class="col-lg-3 settings-nav-wrap">
            <div class="list-group settings-nav sticky-top" id="settingsNav" style="top: 80px;">
                @foreach ($sections as [$id, $icon, $label])
                    <a href="#sec-{{ $id }}" class="list-group-item list-group-item-action {{ $loop->first ? 'active' : '' }}" data-section="sec-{{ $id }}">
                        <i class="bi {{ $icon }}"></i>{{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="col-lg-9">
            <form method="POST" action="{{ route('admin.settings.update') }}" id="settingsForm">
                @csrf @method('PUT')

                {{-- Registration --}}
                <section class="card border settings-card mb-4" id="sec-registration">
                    <div class="card-header border-bottom-0">
                        <div class="chip"><i class="bi bi-person-plus"></i></div>
                        <div>
                            <h2 class="card-title">Registration</h2>
                            <p class="card-sub">Who can join. Each member's invite links carry their sponsor code plus the leg (left or right) they pick.</p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex align-items-start justify-content-between gap-3 p-3 border rounded-3">
                            <div>
                                <label class="fw-semibold small mb-1 d-block" for="require_sponsor">Sponsor code is required to register</label>
                                <div class="field-hint mt-0" style="max-width: 56ch;">
                                    When on, sign-ups with the sponsor field blank are refused. The very first member is exempt while the
                                    network is empty, so someone can always start it.
                                </div>
                            </div>
                            <div class="form-check form-switch fs-4 mb-0 flex-none">
                                <input type="checkbox" class="form-check-input" role="switch" id="require_sponsor" name="require_sponsor" value="1"
                                    @checked(old('require_sponsor', $requireSponsor))>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Tax --}}
                <section class="card border settings-card mb-4" id="sec-tax">
                    <div class="card-header border-bottom-0">
                        <div class="chip warn"><i class="bi bi-receipt"></i></div>
                        <div>
                            <h2 class="card-title">Tax (GST)</h2>
                            <p class="card-sub">
                                A plan's price is the <strong>taxable base</strong> — GST is added on top at checkout and is never part of
                                company margin or the compensation pool. GST is set per product on the
                                <a href="{{ route('admin.products.index') }}" class="link-primary text-decoration-none">Products</a> page.
                            </p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="alert alert-info d-flex gap-2 mb-0 small">
                            <i class="bi bi-info-circle mt-1"></i>
                            <div>There is no company-wide GST rate. Set the rate on <strong>each product</strong> (0% for exempt items). A plan's GST is the blend of its products' rates, worked out when the order is placed.</div>
                        </div>
                    </div>
                </section>

                {{-- Revenue split --}}
                <section class="card border settings-card mb-4" id="sec-split">
                    <div class="card-header border-bottom-0">
                        <div class="chip good"><i class="bi bi-pie-chart"></i></div>
                        <div>
                            <h2 class="card-title">Revenue split</h2>
                            <p class="card-sub">
                                How every ₹1 of <strong>taxable value</strong> (price excl. GST) is divided between goods, the compensation
                                pool and company margin. The three must add up to exactly 100%.
                            </p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="split-visual mb-3" aria-hidden="true">
                            <div id="splitBarCost" class="bg-warning" style="width: {{ (float) old('split_cost_pct', $values['split_cost_pct']) }}%"></div>
                            <div id="splitBarPool" class="bg-primary" style="width: {{ (float) old('split_pool_pct', $values['split_pool_pct']) }}%"></div>
                            <div id="splitBarCompany" class="bg-secondary" style="width: {{ (float) old('split_company_pct', $values['split_company_pct']) }}%"></div>
                        </div>
                        <div class="row g-3">
                            @foreach ([
                                'split_cost_pct' => ['Cost of goods', 'warning'],
                                'split_pool_pct' => ['Compensation pool (BV)', 'primary'],
                                'split_company_pct' => ['Company margin', 'secondary'],
                            ] as $key => [$label, $tone])
                                <div class="col-md-4">
                                    <label class="field-label" for="input_{{ $key }}"><span class="badge rounded-pill bg-{{ $tone }} me-1">&nbsp;</span>{{ $label }}</label>
                                    <div class="input-group">
                                        <input name="{{ $key }}" id="input_{{ $key }}" type="number" step="0.01" min="0" max="100"
                                            value="{{ old($key, $values[$key]) }}" class="form-control split-input" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div id="splitGuidance" class="mt-3 small px-3 py-2 rounded-3"></div>
                    </div>
                </section>

                {{-- Direct & pool rates --}}
                <section class="card border settings-card mb-4" id="sec-rates">
                    <div class="card-header border-bottom-0">
                        <div class="chip"><i class="bi bi-percent"></i></div>
                        <div>
                            <h2 class="card-title">Direct &amp; pool rates</h2>
                            <p class="card-sub">Calculated from a plan's <strong>BV</strong>. Self and Sponsor pay once per purchase; the Rank pool is shared among qualifying ranks when a cycle is approved.</p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="field-label" for="input_self_pct">Self income</label>
                                <div class="input-group">
                                    <input name="self_pct" id="input_self_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('self_pct', $values['self_pct']) }}" class="form-control comp-input" required>
                                    <span class="input-group-text">% of BV</span>
                                </div>
                                <div class="field-hint">Paid to the buyer.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label" for="input_sponsor_pct">Direct sponsor</label>
                                <div class="input-group">
                                    <input name="sponsor_pct" id="input_sponsor_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('sponsor_pct', $values['sponsor_pct']) }}" class="form-control comp-input" required>
                                    <span class="input-group-text">% of BV</span>
                                </div>
                                <div class="field-hint">Paid to the personal introducer.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="field-label" for="input_rank_pool_pct">Rank pool</label>
                                <div class="input-group">
                                    <input name="rank_pool_pct" id="input_rank_pool_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('rank_pool_pct', $values['rank_pool_pct']) }}" class="form-control comp-input" required>
                                    <span class="input-group-text">% of BV</span>
                                </div>
                                <div class="field-hint">Shared by weighted rank points.</div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Level-wise matching --}}
                <section class="card border settings-card mb-4" id="sec-levels">
                    <div class="card-header border-bottom-0 flex-wrap">
                        <div class="chip"><i class="bi bi-layers-half"></i></div>
                        <div class="flex-grow-1">
                            <h2 class="card-title">Level-wise matching</h2>
                            <p class="card-sub">The % of matched BV each generation earns. Add or remove levels to change how far up the cascade reaches (up to 25).</p>
                        </div>
                        <div class="d-flex gap-1 flex-none">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPresetTiered" title="15%, 12%, 10%, 8%, 5%, 3%, 2% (total 55%)">Tiered 55%</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPresetFlat" title="7 levels × 10%">Flat 10%</button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive rounded-3 border mb-3">
                            <table class="table table-sm align-middle mb-0" id="levelsTable">
                                <thead class="table-light small text-secondary">
                                    <tr>
                                        <th class="ps-3" style="width: 120px;">Level</th>
                                        <th>Beneficiary</th>
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
                                                    Matching member (generation 1)
                                                @else
                                                    Generation {{ $lvlNum }} ancestor
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
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-level" title="Remove this level">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light small fw-semibold">
                                    <tr>
                                        <td class="ps-3"><span id="totalLevelsCount">{{ count($activeLevels) }}</span> levels</td>
                                        <td>Total cascade</td>
                                        <td><span id="totalLevelsPctBadge" class="badge bg-primary fs-6">0%</span></td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-sm btn-primary text-nowrap" id="btnAddLevel">
                                                <i class="bi bi-plus-lg me-1"></i>Add level
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Live solvency check --}}
                        <div id="calcGuidanceCard" class="rounded-3 p-3 border bg-body-tertiary">
                            <div class="d-flex align-items-center justify-content-between mb-2 gap-2 flex-wrap">
                                <span class="fw-bold small d-flex align-items-center gap-1">
                                    <i class="bi bi-calculator text-primary"></i>Live payout check
                                </span>
                                <span id="calcStatusBadge" class="badge">Checking...</span>
                            </div>

                            <div class="progress mb-3" style="height: 10px;">
                                <div id="barSelf" class="progress-bar bg-success" title="Self income"></div>
                                <div id="barSponsor" class="progress-bar bg-info" title="Sponsor income"></div>
                                <div id="barLevels" class="progress-bar" style="background-color: #6d28d9;" title="Level-wise cascade"></div>
                                <div id="barRank" class="progress-bar bg-warning" title="Rank pool"></div>
                            </div>

                            <div class="row g-2 text-center small">
                                <div class="col-6 col-md-3"><div class="border rounded-3 py-2 bg-body"><div class="text-secondary" style="font-size: .7rem;">Self</div><div class="fw-bold text-success" id="summarySelf">0%</div></div></div>
                                <div class="col-6 col-md-3"><div class="border rounded-3 py-2 bg-body"><div class="text-secondary" style="font-size: .7rem;">Sponsor</div><div class="fw-bold text-info-emphasis" id="summarySponsor">0%</div></div></div>
                                <div class="col-6 col-md-3"><div class="border rounded-3 py-2 bg-body"><div class="text-secondary" style="font-size: .7rem;">Levels</div><div class="fw-bold" style="color: #6d28d9;" id="summaryLevels">0%</div></div></div>
                                <div class="col-6 col-md-3"><div class="border rounded-3 py-2 bg-body"><div class="text-secondary" style="font-size: .7rem;">Rank pool</div><div class="fw-bold text-warning-emphasis" id="summaryRank">0%</div></div></div>
                            </div>

                            <div id="calcGuidanceMessage" class="small mt-3 p-2 rounded-3"></div>
                            <div class="field-hint">
                                This adds up the rates as if each rupee of BV were paid out once. One purchase can match several uplines at
                                the same time, so real payouts can run higher — the circuit breaker below limits that.
                            </div>
                        </div>

                        <details class="mt-3">
                            <summary class="small fw-semibold text-secondary" style="cursor: pointer;"><i class="bi bi-lightbulb text-warning me-1"></i>How to choose level percentages</summary>
                            <ol class="small text-secondary ps-3 mt-2 mb-0 d-grid gap-2">
                                <li><strong>Fixed allocations first:</strong> Self + Sponsor + Rank pool (e.g. <code>10% + 20% + 3% = 33%</code> of BV).</li>
                                <li><strong>Remaining budget:</strong> <code>100% − 33% = 67%</code> is the most the levels could take.</li>
                                <li><strong>Tiered</strong> (recommended): higher on the upper levels, tapering down, e.g. 15 / 12 / 10 / 8 / 5 / 3 / 2 = 55%. <strong>Flat:</strong> the same % on every level.</li>
                                <li>Leave headroom below that budget — overlapping matches mean real payouts can exceed the simple sum.</li>
                            </ol>
                        </details>
                    </div>
                </section>

                {{-- Circuit breaker --}}
                <section class="card border settings-card mb-4" id="sec-breaker">
                    <div class="card-header border-bottom-0">
                        <div class="chip warn"><i class="bi bi-shield-check"></i></div>
                        <div>
                            <h2 class="card-title">Circuit breaker</h2>
                            <p class="card-sub">
                                Caps how much matching one layer can pay out in a cycle, as a % of that layer's <strong>own new BV</strong>.
                                It stops old carry-forward volume from matching all at once and paying out more than came in.
                                Set <strong>100%</strong> to switch the per-layer limit off.
                            </p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="field-label" for="circuit_breaker_pct">Max matching payout</label>
                                <div class="input-group">
                                    <input id="circuit_breaker_pct" name="circuit_breaker_pct" type="number" step="0.01" min="0" max="100"
                                        value="{{ old('circuit_breaker_pct', $values['circuit_breaker_pct']) }}" class="form-control" required>
                                    <span class="input-group-text">% of new BV</span>
                                </div>
                                <div class="field-hint">Typical range 50–60%.</div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Matching caps --}}
                <section class="card border settings-card mb-4" id="sec-caps">
                    <div class="card-header border-bottom-0">
                        <div class="chip good"><i class="bi bi-speedometer2"></i></div>
                        <div>
                            <h2 class="card-title">Matching caps</h2>
                            <p class="card-sub">The most matching income any one member can earn. Both limits apply at once; enter 0 to switch one off. Members see their usage on their Wallet page.</p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="field-label" for="match_cap_per_cycle">Monthly cap per member</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input id="match_cap_per_cycle" name="match_cap_per_cycle" type="number" step="0.01" min="0"
                                        value="{{ old('match_cap_per_cycle', $values['match_cap_per_cycle']) }}" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="field-label" for="match_cap_per_week">Weekly cap per member</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input id="match_cap_per_week" name="match_cap_per_week" type="number" step="0.01" min="0"
                                        value="{{ old('match_cap_per_week', $values['match_cap_per_week']) }}" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Ranks (own form: inputs below belong to #ranksForm via the form="" attribute) --}}
                <section class="card border settings-card" id="sec-ranks">
                    <div class="card-header border-bottom-0">
                        <div class="chip warn"><i class="bi bi-trophy"></i></div>
                        <div>
                            <h2 class="card-title">Ranks</h2>
                            <p class="card-sub">
                                A member is placed on the <strong>highest rank where both are true</strong>: their matched BV is at or above the
                                rank's threshold, <em>and</em> they have at least the required number of active directs (directs who bought this month).
                                Only members who bought this month are considered, and ranks are re-evaluated each time a cycle is approved.
                            </p>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive rounded-3 border mb-2">
                            <table class="table table-sm align-middle mb-0 small" id="ranksTable">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th class="ps-3" style="min-width: 150px;">Rank name</th>
                                        <th style="min-width: 160px;">Matched BV needed</th>
                                        <th style="min-width: 100px;">Active directs</th>
                                        <th style="min-width: 90px;">Pool share</th>
                                        <th class="text-end pe-3" style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="ranksBody">
                                    @foreach ($ranks as $rank)
                                        <tr class="rank-row">
                                            <td class="ps-3">
                                                <input form="ranksForm" name="ranks[{{ $rank->id }}][name]" value="{{ old("ranks.{$rank->id}.name", $rank->name) }}" class="form-control form-control-sm" maxlength="50" required aria-label="Rank name">
                                                <input form="ranksForm" type="hidden" name="ranks[{{ $rank->id }}][remove]" value="0" class="rank-remove">
                                            </td>
                                            <td><div class="input-group input-group-sm"><span class="input-group-text">₹</span><input form="ranksForm" name="ranks[{{ $rank->id }}][matched_bv_threshold]" type="number" step="0.01" min="0" value="{{ old("ranks.{$rank->id}.matched_bv_threshold", (float) $rank->matched_bv_threshold) }}" class="form-control" required aria-label="Matched BV needed"></div></td>
                                            <td><input form="ranksForm" name="ranks[{{ $rank->id }}][min_active_directs]" type="number" step="1" min="0" value="{{ old("ranks.{$rank->id}.min_active_directs", $rank->min_active_directs) }}" class="form-control form-control-sm" required aria-label="Active directs"></td>
                                            <td><input form="ranksForm" name="ranks[{{ $rank->id }}][share]" type="number" step="1" min="1" value="{{ old("ranks.{$rank->id}.share", $rank->share) }}" class="form-control form-control-sm" required aria-label="Pool share"></td>
                                            <td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-rank-remove" title="Remove this rank"><i class="bi bi-trash"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <template id="newRankTemplate">
                            <tr class="rank-row is-new">
                                <td class="ps-3"><input form="ranksForm" name="new_ranks[__I__][name]" class="form-control form-control-sm" maxlength="50" placeholder="e.g. Crown" required aria-label="Rank name"></td>
                                <td><div class="input-group input-group-sm"><span class="input-group-text">₹</span><input form="ranksForm" name="new_ranks[__I__][matched_bv_threshold]" type="number" step="0.01" min="0" value="0" class="form-control" required aria-label="Matched BV needed"></div></td>
                                <td><input form="ranksForm" name="new_ranks[__I__][min_active_directs]" type="number" step="1" min="0" value="2" class="form-control form-control-sm" required aria-label="Active directs"></td>
                                <td><input form="ranksForm" name="new_ranks[__I__][share]" type="number" step="1" min="1" value="1" class="form-control form-control-sm" required aria-label="Pool share"></td>
                                <td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-rank-remove" title="Remove this rank"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </template>

                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mt-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRank"><i class="bi bi-plus-lg me-1"></i>Add rank</button>
                            <button type="submit" form="ranksForm" class="btn btn-sm btn-success px-3"><i class="bi bi-check-lg me-1"></i>Save ranks</button>
                        </div>

                        <details class="mt-3">
                            <summary class="small fw-semibold text-secondary" style="cursor: pointer;"><i class="bi bi-lightbulb text-warning me-1"></i>How ranks pay</summary>
                            <ul class="small text-secondary ps-3 mt-2 mb-0 d-grid gap-1">
                                <li>The rank pool is <strong>{{ $values['rank_pool_pct'] }}% of the cycle's BV</strong> (set under Direct &amp; pool rates), and only what is left after Self, Sponsor and Matching are paid.</li>
                                <li><strong>Pool share</strong> is a member's points: each qualifier gets <em>pool ÷ everyone's total points × their own points</em>. A rank with share 3 earns three times a rank with share 1.</li>
                                <li>The strictest rank is checked first, so keep thresholds rising from the lowest to the highest rank. Rows are re-sorted by threshold when you save.</li>
                                <li>Renaming a rank renames it for members who hold it. Removing a rank sets its holders back to "none" until they qualify again.</li>
                                <li>Saving ranks changes nothing already paid; it applies from the next cycle approval.</li>
                            </ul>
                        </details>
                    </div>
                </section>

                {{-- Save bar --}}
                <div class="save-bar">
                    <span class="dirty-pill" id="dirtyPill"><i class="bi bi-exclamation-circle"></i>Unsaved changes</span>
                    <span class="text-body-tertiary small me-auto" id="saveHint">Applies from the next cycle approval.</span>
                    <button type="submit" class="btn btn-success px-4" id="btnSubmitSettings">
                        <i class="bi bi-check-lg me-1"></i>Save settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <form id="ranksForm" method="POST" action="{{ route('admin.settings.ranks') }}" class="d-none">@csrf @method('PUT')</form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Highlight the section being read in the left nav.
            const links = document.querySelectorAll('#settingsNav [data-section]');
            const cards = Array.from(links).map(l => document.getElementById(l.dataset.section)).filter(Boolean);
            const setActive = id => links.forEach(l => l.classList.toggle('active', l.dataset.section === id));
            const spy = () => {
                const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
                const current = atBottom ? cards[cards.length - 1] : cards.filter(c => c.getBoundingClientRect().top <= 150).pop() || cards[0];
                if (current) setActive(current.id);
            };
            let spyTick = false;
            ['scroll', 'resize'].forEach(ev => window.addEventListener(ev, () => { if (!spyTick) { spyTick = true; requestAnimationFrame(() => { spyTick = false; spy(); }); } }, { passive: true }));
            spy();
            links.forEach(l => l.addEventListener('click', () => setActive(l.dataset.section)));

            // Revenue split bar + unsaved-changes pill.
            const bars = { split_cost_pct: 'splitBarCost', split_pool_pct: 'splitBarPool', split_company_pct: 'splitBarCompany' };
            Object.entries(bars).forEach(([name, id]) => {
                const input = document.getElementById('input_' + name);
                const bar = document.getElementById(id);
                if (input && bar) input.addEventListener('input', () => { bar.style.width = Math.max(0, Math.min(100, parseFloat(input.value) || 0)) + '%'; });
            });
            const form = document.getElementById('settingsForm');
            const pill = document.getElementById('dirtyPill');
            const markDirty = () => pill.classList.add('show');
            // Rank inputs sit inside this form's markup but belong to the separate Ranks form.
            form.addEventListener('input', e => { if (!e.target.hasAttribute('form')) markDirty(); });

            // Ranks: add a row, or remove one (existing rows are flagged and struck through until saved).
            const ranksBody = document.getElementById('ranksBody');
            const rankTpl = document.getElementById('newRankTemplate');
            let newRankIndex = 0;
            document.getElementById('btnAddRank').addEventListener('click', () => {
                const html = rankTpl.innerHTML.replaceAll('__I__', newRankIndex++);
                ranksBody.insertAdjacentHTML('beforeend', html);
                ranksBody.lastElementChild.querySelector('input').focus();
            });
            ranksBody.addEventListener('click', e => {
                const btn = e.target.closest('.btn-rank-remove');
                if (!btn) return;
                const row = btn.closest('.rank-row');
                if (row.classList.contains('is-new')) { row.remove(); return; }
                const flag = row.querySelector('.rank-remove');
                const removing = flag.value !== '1';
                flag.value = removing ? '1' : '0';
                row.style.opacity = removing ? '.45' : '';
                row.querySelectorAll('input:not([type=hidden])').forEach(i => { i.disabled = false; i.style.textDecoration = removing ? 'line-through' : ''; });
                btn.classList.toggle('btn-danger', removing);
                btn.classList.toggle('btn-outline-danger', !removing);
                btn.title = removing ? 'Undo remove' : 'Remove this rank';
            });
            form.addEventListener('click', e => { if (e.target.closest('#btnAddLevel, .btn-remove-level, #btnPresetTiered, #btnPresetFlat')) markDirty(); });
            form.addEventListener('submit', () => { pill.classList.remove('show'); });
        });
    </script>

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
                        ? 'Matching member (generation 1)'
                        : 'Generation ' + num + ' ancestor';
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
