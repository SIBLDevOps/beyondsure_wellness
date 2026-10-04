{{-- Professional Binary Leg Balance + Visual Binary Genealogy Tree + Generation Breakdown --}}
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
        padding: 1.5rem 1rem 2rem;
        background: radial-gradient(#e2e8f0 1px, transparent 1px);
        background-size: 18px 18px;
        background-color: #f8fafc;
        border-radius: .75rem;
        text-align: center;
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
    }
    .genealogy-tree > .genealogy-branch {
        padding-top: 0;
    }
    .genealogy-branch {
        float: left;
        text-align: center;
        list-style-type: none;
        position: relative;
        padding: 24px 10px 0 10px;
    }
    /* Horizontal & vertical connector lines above each branch */
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
    /* Remove connectors from root node */
    .genealogy-tree > .genealogy-branch::before,
    .genealogy-tree > .genealogy-branch::after {
        display: none;
    }
    /* Remove outer horizontal connectors from first and last children */
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
    /* Vertical line going down from parent to children */
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
    /* Node Card Styling */
    .genealogy-node {
        display: inline-block;
        width: 195px;
        background: #ffffff;
        border: 1.5px solid #cbd5e1;
        border-radius: .75rem;
        padding: .65rem .75rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .05);
        transition: all .15s ease;
        position: relative;
        z-index: 2;
    }
    .genealogy-node:hover {
        border-color: #4f46e5;
        box-shadow: 0 8px 18px rgba(79, 70, 229, .14);
        transform: translateY(-2px);
    }
    .genealogy-node-root {
        border: 2px solid #4f46e5;
        background: linear-gradient(180deg, #eef2ff 0%, #ffffff 42%);
    }
    .genealogy-node.is-active-month {
        border-top: 3px solid #059669;
    }
    .genealogy-node-root.is-active-month {
        border-top: 3px solid #4f46e5;
    }
    .genealogy-node-vacant {
        width: 145px;
        background: rgba(255, 255, 255, .65);
        border: 1.5px dashed #cbd5e1;
        box-shadow: none;
        padding: .75rem .5rem;
    }
    .genealogy-node-vacant:hover {
        transform: none;
        border-color: #94a3b8;
        box-shadow: none;
    }
</style>

{{-- 1. Visual Binary Genealogy Tree Card (Primary Feature) --}}
<div class="card border mb-4">
    <div class="card-header bg-light-subtle border-bottom py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="h6 mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3-fill text-primary"></i>
                    <span>Binary Placement Genealogy Tree (Team Tree)</span>
                </h2>
                <div class="text-body-tertiary small">
                    Interactive Left &amp; Right binary tree hierarchy. Click any member's name to focus their subtree.
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Depth Selector --}}
                <form method="GET" class="d-flex align-items-center gap-2 small text-secondary me-2">
                    <label class="mb-0 fw-medium">Depth:</label>
                    <select name="depth" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                        @foreach ([2, 3, 4, 5, 6] as $d)
                            <option value="{{ $d }}" @selected($depth == $d)>{{ $d }} Levels</option>
                        @endforeach
                    </select>
                </form>

                {{-- Zoom Controls --}}
                <div class="btn-group btn-group-sm" id="treeZoomControls">
                    <button type="button" class="btn btn-outline-secondary" id="btnZoomOut" title="Zoom Out">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnZoomReset" title="Reset Zoom">
                        100%
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnZoomIn" title="Zoom In">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>

                {{-- View Mode Switcher --}}
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-primary" id="btnShowVisualTree">
                        <i class="bi bi-diagram-3 me-1"></i>Tree View
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btnShowTableTree">
                        <i class="bi bi-table me-1"></i>Table View ({{ count($flatNodes) }})
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        {{-- Legend Bar --}}
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
            @if ($member->placementParent)
                <a href="{{ route('admin.members.network', $member->placementParent) }}" class="btn btn-sm btn-light border text-secondary py-0 px-2">
                    <i class="bi bi-arrow-up-circle me-1"></i>Go Up to Parent ({{ $member->placementParent->name }})
                </a>
            @endif
        </div>

        {{-- Visual Binary Tree Diagram --}}
        <div id="visualTreeContainer" class="genealogy-viewport border">
            <div id="genealogyCanvas" class="genealogy-canvas">
                <ul class="genealogy-tree">
                    @include('admin.partials.tree-node', ['node' => $tree, 'position' => 'root'])
                </ul>
            </div>
        </div>

        {{-- Structured Hierarchy Table View (Alternative user-friendly table scenario) --}}
        <div id="tableTreeContainer" class="d-none">
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0" data-sortable>
                    <thead class="table-light text-secondary small">
                        <tr>
                            <th class="ps-3" data-sort-key="number">Level</th>
                            <th data-sort-key="text">Binary Position</th>
                            <th data-sort-key="text">Member</th>
                            <th data-sort-key="text">Placement Parent</th>
                            <th data-sort-key="text">Sponsor</th>
                            <th data-sort-key="text">Monthly Status</th>
                            <th class="text-end" data-sort-key="number">Personal BV</th>
                            <th class="text-end" data-sort-key="number">Left Leg BV</th>
                            <th class="text-end" data-sort-key="number">Right Leg BV</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flatNodes as $item)
                            @php $m = $item['member']; @endphp
                            <tr>
                                <td class="ps-3" data-sort-value="{{ $item['level'] }}">
                                    <span class="badge {{ $item['level'] === 1 ? 'bg-primary text-white' : 'bg-primary-subtle text-primary' }}">
                                        Level {{ $item['level'] }}
                                    </span>
                                </td>
                                <td>
                                    @if ($item['position'] === 'root')
                                        <span class="badge bg-dark-subtle text-dark">ROOT</span>
                                    @elseif ($item['position'] === 'left')
                                        <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-arrow-down-left me-1"></i>LEFT LEG</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-down-right me-1"></i>RIGHT LEG</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $m->name }}</div>
                                    <div class="font-monospace text-primary" style="font-size: .72rem;">{{ $m->member_code }}</div>
                                </td>
                                <td class="small text-secondary">{{ $item['parent_name'] ?? '—' }}</td>
                                <td class="small text-secondary">{{ $m->sponsor?->name ?? '—' }}</td>
                                <td>
                                    @if ($item['active_this_month'])
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end fw-medium" data-sort-value="{{ $item['personal_bv'] }}">₹{{ number_format($item['personal_bv'], 0) }}</td>
                                <td class="text-end text-info-emphasis" data-sort-value="{{ $item['left_bv'] }}">₹{{ number_format($item['left_bv'], 0) }}</td>
                                <td class="text-end text-success" data-sort-value="{{ $item['right_bv'] }}">₹{{ number_format($item['right_bv'], 0) }}</td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.members.show', $m) }}" class="btn btn-outline-secondary">Profile</a>
                                        <a href="{{ route('admin.members.network', $m) }}" class="btn btn-outline-primary">Tree</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- 2. Binary Leg Balance & Per-Generation Breakdown Side-by-Side --}}
<div class="row g-4 mb-4">
    {{-- Binary Leg Volume Balance Table & Progress --}}
    <div class="col-lg-5">
        <div class="card border h-100">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-intersect me-2 text-info"></i>Leg balance &amp; carry-forward</h2>
                <span class="badge bg-primary-subtle text-primary">1:1 Binary Match</span>
            </div>
            <div class="card-body">
                <div class="small mb-3">
                    <div class="d-flex justify-content-between text-secondary mb-1">
                        <span class="fw-medium text-info-emphasis"><i class="bi bi-arrow-down-left-circle me-1"></i>Left leg BV</span>
                        <span class="fw-bold text-dark">₹{{ number_format($left, 0) }}</span>
                    </div>
                    <div class="progress mb-3" style="height: .55rem;">
                        <div class="progress-bar bg-info" style="width: {{ min(100, $left / $max * 100) }}%"></div>
                    </div>

                    <div class="d-flex justify-content-between text-secondary mb-1">
                        <span class="fw-medium text-success"><i class="bi bi-arrow-down-right-circle me-1"></i>Right leg BV</span>
                        <span class="fw-bold text-dark">₹{{ number_format($right, 0) }}</span>
                    </div>
                    <div class="progress mb-3" style="height: .55rem;">
                        <div class="progress-bar bg-success" style="width: {{ min(100, $right / $max * 100) }}%"></div>
                    </div>
                </div>

                <table class="table table-sm align-middle mb-0 border-top">
                    <tbody>
                        <tr>
                            <td class="text-secondary py-2">Matched so far</td>
                            <td class="text-end fw-bold text-primary py-2">₹{{ number_format($matched, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-body-tertiary small py-2">Unmatched carry-forward — left</td>
                            <td class="text-end fw-medium text-info-emphasis py-2">₹{{ number_format($carryLeft, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-body-tertiary small py-2 border-bottom-0">Unmatched carry-forward — right</td>
                            <td class="text-end fw-medium text-success py-2 border-bottom-0">₹{{ number_format($carryRight, 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Per-Generation Breakdown Table --}}
    <div class="col-lg-7">
        <div class="card border h-100">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                <div>
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2 text-info"></i>Per-generation breakdown</h2>
                    <div class="text-body-tertiary small">Downline count, monthly activity, and volume per generation</div>
                </div>
                <a href="{{ route('admin.members.levels', $member) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-bar-chart-steps me-1"></i>Level Income Report
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
                                    <td class="ps-3">
                                        <span class="badge bg-primary-subtle text-primary">Level {{ $row['level'] }}</span>
                                    </td>
                                    <td class="text-center fw-semibold">{{ $row['count'] }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $row['active_count'] > 0 ? 'bg-success-subtle text-success' : 'bg-light text-secondary border' }}">
                                            {{ $row['active_count'] }} active
                                        </span>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold">₹{{ number_format($row['bv'], 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-4">No downline yet at this depth.</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($levelBreakdown) > 0)
                            <tfoot class="table-light fw-semibold small">
                                <tr>
                                    <td class="ps-3">Total ({{ count($levelBreakdown) }} Levels)</td>
                                    <td class="text-center">{{ collect($levelBreakdown)->sum('count') }}</td>
                                    <td class="text-center text-success">{{ collect($levelBreakdown)->sum('active_count') }} active</td>
                                    <td class="text-end pe-3 text-primary">₹{{ number_format(collect($levelBreakdown)->sum('bv'), 0) }}</td>
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
        const canvas = document.getElementById('genealogyCanvas');
        const btnZoomIn = document.getElementById('btnZoomIn');
        const btnZoomOut = document.getElementById('btnZoomOut');
        const btnZoomReset = document.getElementById('btnZoomReset');
        const btnShowVisual = document.getElementById('btnShowVisualTree');
        const btnShowTable = document.getElementById('btnShowTableTree');
        const visualContainer = document.getElementById('visualTreeContainer');
        const tableContainer = document.getElementById('tableTreeContainer');
        const zoomControls = document.getElementById('treeZoomControls');

        let scale = 1;
        function applyZoom() {
            if (!canvas) return;
            canvas.style.transform = `scale(${scale})`;
            btnZoomReset.textContent = Math.round(scale * 100) + '%';
        }

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
            });
        }

        if (btnShowVisual && btnShowTable) {
            btnShowVisual.addEventListener('click', () => {
                visualContainer.classList.remove('d-none');
                tableContainer.classList.add('d-none');
                zoomControls.classList.remove('d-none');
                btnShowVisual.className = 'btn btn-primary';
                btnShowTable.className = 'btn btn-outline-primary';
            });
            btnShowTable.addEventListener('click', () => {
                visualContainer.classList.add('d-none');
                tableContainer.classList.remove('d-none');
                zoomControls.classList.add('d-none');
                btnShowTable.className = 'btn btn-primary';
                btnShowVisual.className = 'btn btn-outline-primary';
            });
        }
    });
</script>