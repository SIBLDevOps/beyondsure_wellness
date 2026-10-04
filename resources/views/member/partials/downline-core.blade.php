@php
    $left = (float) ($legTotal->left_bv ?? 0);
    $right = (float) ($legTotal->right_bv ?? 0);
    $matched = (float) ($legTotal->matched_bv ?? 0);
    $carryLeft = max(0, $left - $matched);
    $carryRight = max(0, $right - $matched);
    $max = max($left, $right, 1);

    // Flatten tree for the Hierarchy Table View
    $flatNodes = [];
    $flattenTree = function (?array $n, ?string $parentName = null, string $pos = 'root') use (&$flattenTree, &$flatNodes) {
        if (! $n) {
            return;
        }
        $flatNodes[] = [
            'level' => $n['level'],
            'position' => $pos,
            'member' => $n['member'],
            'parent_name' => $parentName,
            'active_this_month' => $n['active_this_month'],
            'personal_bv' => $n['personal_bv'] ?? 0,
            'left_bv' => $n['left_bv'] ?? 0,
            'right_bv' => $n['right_bv'] ?? 0,
        ];
        if ($n['left']) {
            $flattenTree($n['left'], $n['member']->name, 'left');
        }
        if ($n['right']) {
            $flattenTree($n['right'], $n['member']->name, 'right');
        }
    };
    $flattenTree($tree);
@endphp

<style>
    /* Visual Binary Genealogy Org-Chart Tree Styles */
    .genealogy-viewport {
        overflow-x: auto;
        overflow-y: hidden;
        padding: 1.75rem 1rem 2.25rem;
        background: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
        background-size: 20px 20px;
        background-color: #f8fafc;
        border-radius: .85rem;
        text-align: center;
        position: relative;
        cursor: grab;
    }
    .genealogy-viewport:active {
        cursor: grabbing;
    }
    .genealogy-canvas {
        display: inline-block;
        min-width: 100%;
        transform-origin: top center;
        transition: transform .2s ease;
    }
    .genealogy-tree,
    .genealogy-tree ul {
        padding-top: 24px;
        position: relative;
        display: inline-flex;
        justify-content: center;
        list-style-type: none;
        margin: 0;
        padding-left: 0;
        white-space: nowrap;
    }
    .genealogy-tree > .genealogy-branch {
        padding-top: 0;
    }
    .genealogy-branch {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        list-style-type: none;
        position: relative;
        padding: 24px 12px 0 12px;
        flex-shrink: 0;
    }
    .genealogy-branch::before,
    .genealogy-branch::after {
        content: '';
        position: absolute;
        top: 0;
        right: 50%;
        border-top: 2px solid #cbd5e1;
        width: 50%;
        height: 24px;
    }
    .genealogy-branch::after {
        right: auto;
        left: 50%;
        border-left: 2px solid #cbd5e1;
    }
    .genealogy-tree > .genealogy-branch::before,
    .genealogy-tree > .genealogy-branch::after {
        display: none;
    }
    .genealogy-branch:first-child::before,
    .genealogy-branch:last-child::after {
        border: 0 none;
    }
    .genealogy-branch:last-child::before {
        border-right: 2px solid #cbd5e1;
        border-radius: 0 8px 0 0;
    }
    .genealogy-branch:first-child::after {
        border-radius: 8px 0 0 0;
    }
    .genealogy-children::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        border-left: 2px solid #cbd5e1;
        width: 0;
        height: 24px;
        transform: translateX(-50%);
    }
    .genealogy-node {
        display: inline-block;
        width: 195px;
        background: #ffffff;
        border: 1.5px solid #cbd5e1;
        border-radius: .85rem;
        padding: .75rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .06);
        transition: all .15s ease;
        position: relative;
        z-index: 2;
        white-space: normal;
        text-align: left;
    }
    .genealogy-node:hover {
        border-color: #059669;
        box-shadow: 0 8px 20px rgba(5, 150, 105, .15);
        transform: translateY(-2px);
    }
    .genealogy-node-root {
        border: 2px solid #059669;
        background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 45%);
        box-shadow: 0 4px 12px rgba(5, 150, 105, .12);
    }
    .genealogy-node.is-active-month {
        border-top: 3px solid #059669;
    }
    .genealogy-node.is-inactive-month {
        border-top: 3px solid #94a3b8;
    }
    .genealogy-node-vacant {
        width: 145px;
        background: rgba(255, 255, 255, .75);
        border: 1.5px dashed #cbd5e1;
        box-shadow: none;
        padding: .75rem .5rem;
        text-align: center;
    }
</style>

{{-- 0. Binary Network KPI Summary Strip --}}
<div class="row g-3 mb-4">
    {{-- Left Leg Card --}}
    <div class="col-6 col-lg-3">
        <div class="card border h-100 shadow-sm hover-lift" style="border-top: 3px solid #0284c7 !important; border-radius: .85rem;">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle px-2" style="font-size: .68rem; font-weight: 700; letter-spacing: .03em; padding-top: .3rem; padding-bottom: .3rem;">
                        <i class="bi bi-arrow-down-left me-1"></i>LEFT LEG
                    </span>
                    <div class="stat-icon bg-info-subtle text-info-emphasis rounded-3" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-arrow-down-left-circle-fill"></i>
                    </div>
                </div>
                <div>
                    <div class="text-secondary small fw-medium">Total Left Volume</div>
                    <div class="fs-3 fw-bold text-dark my-1" style="letter-spacing: -0.02em;">₹{{ number_format($left, 0) }}</div>
                </div>
                <div class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between">
                    <span class="text-body-tertiary small" style="font-size: .75rem;"><i class="bi bi-arrow-repeat text-info me-1"></i>Carry:</span>
                    <span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: .75rem;">₹{{ number_format($carryLeft, 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Leg Card --}}
    <div class="col-6 col-lg-3">
        <div class="card border h-100 shadow-sm hover-lift" style="border-top: 3px solid #059669 !important; border-radius: .85rem;">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2" style="font-size: .68rem; font-weight: 700; letter-spacing: .03em; padding-top: .3rem; padding-bottom: .3rem;">
                        <i class="bi bi-arrow-down-right me-1"></i>RIGHT LEG
                    </span>
                    <div class="stat-icon bg-success-subtle text-success rounded-3" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-arrow-down-right-circle-fill"></i>
                    </div>
                </div>
                <div>
                    <div class="text-secondary small fw-medium">Total Right Volume</div>
                    <div class="fs-3 fw-bold text-dark my-1" style="letter-spacing: -0.02em;">₹{{ number_format($right, 0) }}</div>
                </div>
                <div class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between">
                    <span class="text-body-tertiary small" style="font-size: .75rem;"><i class="bi bi-arrow-repeat text-success me-1"></i>Carry:</span>
                    <span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: .75rem;">₹{{ number_format($carryRight, 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Matched BV Card --}}
    <div class="col-6 col-lg-3">
        <div class="card border h-100 shadow-sm hover-lift" style="border-top: 3px solid #6366f1 !important; border-radius: .85rem;">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2" style="font-size: .68rem; font-weight: 700; letter-spacing: .03em; padding-top: .3rem; padding-bottom: .3rem;">
                        <i class="bi bi-intersect me-1"></i>1:1 MATCH
                    </span>
                    <div class="stat-icon bg-primary-subtle text-primary rounded-3" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-intersect"></i>
                    </div>
                </div>
                <div>
                    <div class="text-secondary small fw-medium">Matched Volume</div>
                    <div class="fs-3 fw-bold text-dark my-1" style="letter-spacing: -0.02em;">₹{{ number_format($matched, 0) }}</div>
                </div>
                <div class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between">
                    <span class="text-body-tertiary small" style="font-size: .75rem;"><i class="bi bi-check-circle-fill text-success me-1"></i>Status:</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1" style="font-size: .72rem;">All-Time Paired</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Visible Nodes Card --}}
    <div class="col-6 col-lg-3">
        <div class="card border h-100 shadow-sm hover-lift" style="border-top: 3px solid #f59e0b !important; border-radius: .85rem;">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2" style="font-size: .68rem; font-weight: 700; letter-spacing: .03em; padding-top: .3rem; padding-bottom: .3rem;">
                        <i class="bi bi-diagram-3 me-1"></i>TREE ({{ $depth }}L)
                    </span>
                    <div class="stat-icon bg-warning-subtle text-warning-emphasis rounded-3" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div>
                    <div class="text-secondary small fw-medium">Visible Members</div>
                    <div class="fs-3 fw-bold text-dark my-1" style="letter-spacing: -0.02em;">{{ count($flatNodes) }} <span class="fs-6 fw-normal text-secondary">nodes</span></div>
                </div>
                <div class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between">
                    <span class="text-body-tertiary small" style="font-size: .75rem;"><i class="bi bi-activity text-success me-1"></i>Active:</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: .72rem;">{{ collect($flatNodes)->where('active_this_month', true)->count() }} this month</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Binary Education Helper Card --}}
<div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 50%, #f8fafc 100%); border: 1px solid rgba(5, 150, 105, 0.18) !important;">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow-sm" style="width: 30px; height: 30px; font-size: .85rem;">
                    <i class="bi bi-lightbulb-fill"></i>
                </span>
                <div>
                    <div class="fw-bold text-dark small mb-0">How Your Binary Team &amp; Matching Income Works</div>
                    <div class="text-body-tertiary" style="font-size: .75rem;">Four foundational mechanics driving volume matching and partner earnings</div>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 small fw-semibold d-inline-flex align-items-center gap-1 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#binaryGuideContent">
                <i class="bi bi-chevron-down"></i>
                <span>Toggle tips</span>
            </button>
        </div>
        <div class="collapse show" id="binaryGuideContent">
            <div class="row g-3 pt-1">
                {{-- Step 1 --}}
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm hover-lift" style="border-left: 3px solid #0284c7 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-2" style="font-size: .65rem; font-weight: 700; padding-top: .25rem; padding-bottom: .25rem;">1. STRUCTURE</span>
                            <i class="bi bi-arrows-split text-info fs-6"></i>
                        </div>
                        <div class="fw-bold text-dark small mb-1">Left &amp; Right Legs</div>
                        <p class="text-secondary mb-0" style="font-size: .78rem; line-height: 1.45;">
                            Your organization branches into <strong>two distinct sides</strong>. Product volume purchased anywhere in that lineage adds directly to your Left or Right Leg BV.
                        </p>
                    </div>
                </div>

                {{-- Step 2 --}}
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm hover-lift" style="border-left: 3px solid #059669 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success-subtle text-success rounded-pill px-2" style="font-size: .65rem; font-weight: 700; padding-top: .25rem; padding-bottom: .25rem;">2. COMMISSION</span>
                            <i class="bi bi-intersect text-success fs-6"></i>
                        </div>
                        <div class="fw-bold text-dark small mb-1">1:1 Pairing Match</div>
                        <p class="text-secondary mb-0" style="font-size: .78rem; line-height: 1.45;">
                            When both legs produce volume, matching BV pairs on a <strong>1:1 ratio</strong>, triggering your matching commission according to cycle rules.
                        </p>
                    </div>
                </div>

                {{-- Step 3 --}}
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm hover-lift" style="border-left: 3px solid #6366f1 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2" style="font-size: .65rem; font-weight: 700; padding-top: .25rem; padding-bottom: .25rem;">3. SECURITY</span>
                            <i class="bi bi-arrow-repeat text-primary fs-6"></i>
                        </div>
                        <div class="fw-bold text-dark small mb-1">Carry Forward</div>
                        <p class="text-secondary mb-0" style="font-size: .78rem; line-height: 1.45;">
                            Unmatched volume <strong>never expires</strong>! The stronger power leg retains its surplus balance and rolls it into subsequent cycles until matched.
                        </p>
                    </div>
                </div>

                {{-- Step 4 --}}
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-white rounded-3 border h-100 shadow-sm hover-lift" style="border-left: 3px solid #f59e0b !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2" style="font-size: .65rem; font-weight: 700; padding-top: .25rem; padding-bottom: .25rem;">4. LEVERAGE</span>
                            <i class="bi bi-people-fill text-warning fs-6"></i>
                        </div>
                        <div class="fw-bold text-dark small mb-1">Spillover Growth</div>
                        <p class="text-secondary mb-0" style="font-size: .78rem; line-height: 1.45;">
                            Partners placed in your team by your upline sponsors still generate volume that <strong>counts 100%</strong> toward your binary leg totals.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- 1. Visual Binary Genealogy Tree Card --}}
<div class="card border mb-4">
    <div class="card-header bg-light-subtle border-bottom py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="h6 mb-0 fw-semibold d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3-fill text-success"></i>
                    <span>Team Binary Genealogy Tree</span>
                </h2>
                <div class="text-body-tertiary small">
                    Your Left &amp; Right binary placement structure down to {{ $depth }} levels.
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form method="GET" class="d-flex align-items-center gap-2 small text-secondary me-2">
                    <label class="mb-0 fw-medium">Depth:</label>
                    <select name="depth" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                        @foreach ([2, 3, 4, 5, 6] as $d)
                            <option value="{{ $d }}" @selected($depth == $d)>{{ $d }} Levels</option>
                        @endforeach
                    </select>
                </form>

                <div class="btn-group btn-group-sm" id="memberTreeZoomControls">
                    <button type="button" class="btn btn-outline-secondary" id="memberBtnZoomOut" title="Zoom Out">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="memberBtnZoomReset" title="Reset Zoom">
                        100%
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="memberBtnZoomIn" title="Zoom In">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="memberBtnCenter" title="Center Tree View">
                        <i class="bi bi-crosshair"></i>
                    </button>
                </div>

                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-success" id="memberBtnShowVisual">
                        <i class="bi bi-diagram-3 me-1"></i>Tree View
                    </button>
                    <button type="button" class="btn btn-outline-success" id="memberBtnShowTable">
                        <i class="bi bi-table me-1"></i>Table View ({{ count($flatNodes) }})
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 small text-secondary">
            <div class="d-flex align-items-center flex-wrap gap-3">
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="rounded-circle bg-success d-inline-block" style="width:8px;height:8px;"></span> Active this month
                </span>
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="rounded-circle bg-secondary d-inline-block" style="width:8px;height:8px;"></span> Inactive this month
                </span>
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="badge bg-info-subtle text-info-emphasis">LEFT</span> Left Binary Leg
                </span>
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="badge bg-success-subtle text-success">RIGHT</span> Right Binary Leg
                </span>
            </div>
            <div class="text-body-tertiary small d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-arrows-move me-1"></i><span>Drag to pan canvas · Use zoom &amp; center</span>
            </div>
        </div>

        <div id="memberVisualTreeContainer" class="genealogy-viewport border">
            <div id="memberGenealogyCanvas" class="genealogy-canvas">
                <ul class="genealogy-tree">
                    @include('member.partials.tree-node', ['node' => $tree, 'position' => 'root'])
                </ul>
            </div>
        </div>

        <div id="memberTableTreeContainer" class="d-none">
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-3">Level</th>
                            <th>Position</th>
                            <th>Member</th>
                            <th>Placement Parent</th>
                            <th>Monthly Status</th>
                            <th class="text-end">Own BV</th>
                            <th class="text-end">Left BV</th>
                            <th class="text-end pe-3">Right BV</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flatNodes as $item)
                            @php $m = $item['member']; @endphp
                            <tr>
                                <td class="ps-3">
                                    <span class="badge {{ $item['level'] === 1 ? 'bg-success text-white' : 'bg-secondary-subtle text-secondary' }}">
                                        Level {{ $item['level'] }}
                                    </span>
                                </td>
                                <td>
                                    @if ($item['position'] === 'root')
                                        <span class="badge bg-dark-subtle text-dark">YOU (ROOT)</span>
                                    @elseif ($item['position'] === 'left')
                                        <span class="badge bg-info-subtle text-info-emphasis">LEFT LEG</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">RIGHT LEG</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $m->name }}</div>
                                    <div class="font-monospace text-secondary" style="font-size: .72rem;">{{ $m->member_code }}</div>
                                </td>
                                <td class="small text-secondary">{{ $item['parent_name'] ?? '—' }}</td>
                                <td>
                                    @if ($item['active_this_month'])
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end fw-medium">₹{{ number_format($item['personal_bv'], 0) }}</td>
                                <td class="text-end text-info-emphasis">₹{{ number_format($item['left_bv'], 0) }}</td>
                                <td class="text-end text-success pe-3">₹{{ number_format($item['right_bv'], 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- 2. Binary Leg Balance & Per-Generation Breakdown --}}
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border h-100">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-intersect me-2 text-primary"></i>Left &amp; Right Legs Balance</h2>
                    <div class="text-body-tertiary small">1:1 binary pairing ratio &amp; carry-forward volume</div>
                </div>
                @if ($carryLeft > 0 || $carryRight > 0)
                    <span class="badge bg-warning-subtle text-warning-emphasis">Carry Active</span>
                @else
                    <span class="badge bg-success-subtle text-success">Balanced</span>
                @endif
            </div>
            <div class="card-body">
                {{-- Side-by-side Leg Comparison Cards --}}
                <div class="row g-2 mb-3">
                    {{-- Left Leg Card --}}
                    <div class="col-6">
                        <div class="p-3 rounded-3 border {{ $left >= $right && $left > 0 ? 'bg-info bg-opacity-10 border-info-subtle' : 'bg-light border-light-subtle' }} h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-info text-white rounded-pill px-2 py-0.5" style="font-size: .65rem;">
                                    <i class="bi bi-arrow-down-left me-1"></i>LEFT LEG
                                </span>
                                @if ($left > $right)
                                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill" style="font-size: .62rem;">Power</span>
                                @endif
                            </div>
                            <div class="text-secondary small">Total Volume</div>
                            <div class="fs-5 fw-bold text-info-emphasis lh-1 mt-1">₹{{ number_format($left, 0) }}</div>
                            <div class="text-body-tertiary mt-2 pt-1 border-top" style="font-size: .72rem;">
                                Carry: <strong class="text-dark">₹{{ number_format($carryLeft, 0) }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Right Leg Card --}}
                    <div class="col-6">
                        <div class="p-3 rounded-3 border {{ $right >= $left && $right > 0 ? 'bg-success bg-opacity-10 border-success-subtle' : 'bg-light border-light-subtle' }} h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success text-white rounded-pill px-2 py-0.5" style="font-size: .65rem;">
                                    <i class="bi bi-arrow-down-right me-1"></i>RIGHT LEG
                                </span>
                                @if ($right > $left)
                                    <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: .62rem;">Power</span>
                                @endif
                            </div>
                            <div class="text-secondary small">Total Volume</div>
                            <div class="fs-5 fw-bold text-success lh-1 mt-1">₹{{ number_format($right, 0) }}</div>
                            <div class="text-body-tertiary mt-2 pt-1 border-top" style="font-size: .72rem;">
                                Carry: <strong class="text-dark">₹{{ number_format($carryRight, 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Visual Proportion Split Bar --}}
                @php
                    $totalLegBv = $left + $right;
                    $leftPct = $totalLegBv > 0 ? round(($left / $totalLegBv) * 100) : 50;
                    $rightPct = 100 - $leftPct;
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-secondary mb-1">
                        <span><i class="bi bi-square-fill text-info me-1" style="font-size: .6rem;"></i>Left Leg: {{ $leftPct }}%</span>
                        <span>Right Leg: {{ $rightPct }}% <i class="bi bi-square-fill text-success ms-1" style="font-size: .6rem;"></i></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info" style="width: {{ $leftPct }}%"></div>
                        <div class="progress-bar bg-success" style="width: {{ $rightPct }}%"></div>
                    </div>
                </div>

                {{-- Matched Volume Summary --}}
                <div class="p-3 bg-primary bg-opacity-10 border border-primary-subtle rounded-3 mb-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-primary fw-semibold small"><i class="bi bi-intersect me-1"></i>1:1 Matched Volume</div>
                        <div class="text-secondary" style="font-size: .72rem;">Volume paired and converted into matching income</div>
                    </div>
                    <div class="fs-5 fw-bold text-primary font-monospace">₹{{ number_format($matched, 0) }}</div>
                </div>

                {{-- Growth Tip Strategy Banner --}}
                <div class="p-3 bg-light rounded-3 border text-secondary" style="font-size: .78rem; line-height: 1.45;">
                    @if ($left > $right)
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i><strong>Strategy:</strong> Your Left leg is stronger (+₹{{ number_format($carryLeft, 0) }} carry). Focus on your <span class="badge bg-success-subtle text-success border border-success-subtle">Right Leg</span> to pair volume and maximize commissions!
                    @elseif ($right > $left)
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i><strong>Strategy:</strong> Your Right leg is stronger (+₹{{ number_format($carryRight, 0) }} carry). Focus on your <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Left Leg</span> to pair volume and maximize commissions!
                    @else
                        <i class="bi bi-check-circle-fill text-success me-1"></i><strong>Balanced:</strong> Both legs are producing equal volume. Keep building both sides evenly for maximum pairing payouts.
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border h-100">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-success"></i>Per-Generation Breakdown</h2>
                    <div class="text-body-tertiary small">Active members and volume contribution down {{ count($levelBreakdown) }} generations</div>
                </div>
                <a href="{{ route('member.levels.index') }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-bar-chart-steps me-1"></i>Level Rates
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Level</th>
                                <th class="text-center">Members</th>
                                <th class="text-center">Active this month</th>
                                <th class="text-end pe-3">BV contributed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($levelBreakdown as $row)
                                <tr>
                                    <td class="ps-3 fw-medium">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Level {{ $row['level'] }}</span>
                                    </td>
                                    <td class="text-center fw-semibold">{{ $row['count'] }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $row['active_count'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-secondary border' }}">
                                            {{ $row['active_count'] }} active
                                        </span>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold font-monospace">₹{{ number_format($row['bv'], 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-body-tertiary text-center py-4">No downline yet at this depth.</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($levelBreakdown) > 0)
                            <tfoot class="table-light fw-semibold small">
                                <tr>
                                    <td class="ps-3">Total ({{ count($levelBreakdown) }} Levels)</td>
                                    <td class="text-center">{{ collect($levelBreakdown)->sum('count') }}</td>
                                    <td class="text-center text-success">{{ collect($levelBreakdown)->sum('active_count') }} active</td>
                                    <td class="text-end pe-3 text-primary font-monospace">₹{{ number_format(collect($levelBreakdown)->sum('bv'), 0) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                <div class="p-3 bg-light border-top text-body-tertiary" style="font-size: .78rem;">
                    <i class="bi bi-info-circle me-1"></i>Members below cascade depth still add volume to your legs and help your own matching; only their personal match stops paying you.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('memberGenealogyCanvas');
        const btnZoomIn = document.getElementById('memberBtnZoomIn');
        const btnZoomOut = document.getElementById('memberBtnZoomOut');
        const btnZoomReset = document.getElementById('memberBtnZoomReset');
        const btnCenter = document.getElementById('memberBtnCenter');
        const btnShowVisual = document.getElementById('memberBtnShowVisual');
        const btnShowTable = document.getElementById('memberBtnShowTable');
        const visualContainer = document.getElementById('memberVisualTreeContainer');
        const tableContainer = document.getElementById('memberTableTreeContainer');
        const zoomControls = document.getElementById('memberTreeZoomControls');

        let scale = 1;
        function applyZoom() {
            if (!canvas) return;
            canvas.style.transform = `scale(${scale})`;
            if (btnZoomReset) {
                btnZoomReset.textContent = Math.round(scale * 100) + '%';
            }
        }

        function centerTree() {
            if (!visualContainer) return;
            const scrollableDist = visualContainer.scrollWidth - visualContainer.clientWidth;
            if (scrollableDist > 0) {
                visualContainer.scrollTo({
                    left: scrollableDist / 2,
                    behavior: 'smooth'
                });
            }
        }

        // Auto center tree on page load
        setTimeout(() => {
            if (visualContainer) {
                const scrollableDist = visualContainer.scrollWidth - visualContainer.clientWidth;
                if (scrollableDist > 0) {
                    visualContainer.scrollLeft = scrollableDist / 2;
                }
            }
        }, 100);

        if (btnZoomIn) {
            btnZoomIn.addEventListener('click', () => {
                scale = Math.min(1.4, Math.round((scale + 0.1) * 10) / 10);
                applyZoom();
            });
        }
        if (btnZoomOut) {
            btnZoomOut.addEventListener('click', () => {
                scale = Math.max(0.5, Math.round((scale - 0.1) * 10) / 10);
                applyZoom();
            });
        }
        if (btnZoomReset) {
            btnZoomReset.addEventListener('click', () => {
                scale = 1;
                applyZoom();
                centerTree();
            });
        }
        if (btnCenter) {
            btnCenter.addEventListener('click', () => {
                centerTree();
            });
        }

        // Drag to pan for tree viewport
        if (visualContainer) {
            let isDown = false;
            let startX = 0;
            let scrollLeftPos = 0;

            visualContainer.addEventListener('mousedown', function (e) {
                if (e.target.closest('a, button, input, select')) return;
                isDown = true;
                visualContainer.style.cursor = 'grabbing';
                startX = e.pageX - visualContainer.offsetLeft;
                scrollLeftPos = visualContainer.scrollLeft;
            });

            window.addEventListener('mouseup', function () {
                if (isDown) {
                    isDown = false;
                    visualContainer.style.cursor = 'grab';
                }
            });

            visualContainer.addEventListener('mousemove', function (e) {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - visualContainer.offsetLeft;
                const walk = (x - startX) * 1.3;
                visualContainer.scrollLeft = scrollLeftPos - walk;
            });
        }

        if (btnShowVisual && btnShowTable) {
            btnShowVisual.addEventListener('click', () => {
                visualContainer.classList.remove('d-none');
                tableContainer.classList.add('d-none');
                zoomControls.classList.remove('d-none');
                btnShowVisual.className = 'btn btn-success';
                btnShowTable.className = 'btn btn-outline-success';
                centerTree();
            });
            btnShowTable.addEventListener('click', () => {
                visualContainer.classList.add('d-none');
                tableContainer.classList.remove('d-none');
                zoomControls.classList.add('d-none');
                btnShowTable.className = 'btn btn-success';
                btnShowVisual.className = 'btn btn-outline-success';
            });
        }
    });
</script>
