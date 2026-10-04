@php
    $position = $position ?? ($node['level'] === 1 ? 'root' : ($node['member']->position ?? 'left'));
@endphp

@if ($node)
    <li class="genealogy-branch">
        <div class="genealogy-node {{ $node['level'] === 1 ? 'genealogy-node-root' : '' }} {{ $node['active_this_month'] ? 'is-active-month' : 'is-inactive-month' }}">
            {{-- Top Position & Level Strip --}}
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2 pb-1 border-bottom">
                @if ($node['level'] === 1)
                    <span class="badge bg-primary text-white" style="font-size: .65rem;">
                        <i class="bi bi-star-fill me-1"></i>ROOT (L1)
                    </span>
                @elseif ($position === 'left')
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: .65rem;">
                        <i class="bi bi-arrow-down-left me-1"></i>LEFT · L{{ $node['level'] }}
                    </span>
                @else
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: .65rem;">
                        <i class="bi bi-arrow-down-right me-1"></i>RIGHT · L{{ $node['level'] }}
                    </span>
                @endif

                <span class="d-inline-flex align-items-center gap-1" style="font-size: .65rem;" title="{{ $node['active_this_month'] ? 'Active this month' : 'No order this month' }}">
                    <span class="rounded-circle {{ $node['active_this_month'] ? 'bg-success' : 'bg-secondary' }}" style="width:7px;height:7px;"></span>
                    <span class="{{ $node['active_this_month'] ? 'text-success fw-semibold' : 'text-body-tertiary' }}">
                        {{ $node['active_this_month'] ? 'Active' : 'Inactive' }}
                    </span>
                </span>
            </div>

            {{-- Member Identity --}}
            <div class="d-flex align-items-center gap-2 text-start mb-2">
                <span class="avatar-circle avatar-circle-sm {{ $node['level'] === 1 ? 'bg-primary' : ($position === 'left' ? 'bg-info' : 'bg-success') }}">
                    {{ strtoupper(substr($node['member']->name, 0, 2)) }}
                </span>
                <div class="min-w-0 flex-grow-1">
                    <a href="{{ route('admin.members.network', $node['member']) }}"
                       class="fw-bold text-dark text-decoration-none d-block text-truncate"
                       style="font-size: .82rem;"
                       title="Focus tree on {{ $node['member']->name }}">
                        {{ $node['member']->name }}
                    </a>
                    <div class="d-flex align-items-center gap-1">
                        <span class="font-monospace text-primary" style="font-size: .7rem;">{{ $node['member']->member_code }}</span>
                        @if ($node['member']->rank && $node['member']->rank !== 'none')
                            <span class="badge bg-warning-subtle text-warning-emphasis text-capitalize" style="font-size: .6rem;">{{ $node['member']->rank }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Volume Metrics Strip --}}
            <div class="bg-light rounded-2 p-1 px-2 text-start" style="font-size: .68rem;">
                <div class="d-flex justify-content-between text-secondary">
                    <span>Own BV:</span>
                    <strong class="text-dark">₹{{ number_format($node['personal_bv'] ?? 0, 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between text-secondary border-top mt-1 pt-1">
                    <span class="text-info-emphasis">L: ₹{{ number_format($node['left_bv'] ?? 0, 0) }}</span>
                    <span class="text-body-tertiary">|</span>
                    <span class="text-success">R: ₹{{ number_format($node['right_bv'] ?? 0, 0) }}</span>
                </div>
            </div>

            {{-- Quick Links Footer --}}
            <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top" style="font-size: .68rem;">
                <a href="{{ route('admin.members.show', $node['member']) }}" class="text-secondary text-decoration-none" title="View Member Profile">
                    <i class="bi bi-person-lines-fill"></i> Profile
                </a>
                <a href="{{ route('admin.members.levels', $node['member']) }}" class="text-primary text-decoration-none" title="View Level Income">
                    <i class="bi bi-bar-chart-steps"></i> Levels
                </a>
                @if ($node['level'] > 1)
                    <a href="{{ route('admin.members.network', $node['member']) }}" class="text-success text-decoration-none fw-medium" title="Expand from here">
                        <i class="bi bi-arrows-fullscreen"></i> Tree
                    </a>
                @endif
            </div>
        </div>

        {{-- Child Branches (Always render both Left & Right slots if either exists or if root node) --}}
        @if ($node['left'] || $node['right'] || $node['level'] === 1)
            <ul class="genealogy-children">
                {{-- Left Branch --}}
                @if ($node['left'])
                    @include('admin.partials.tree-node', ['node' => $node['left'], 'position' => 'left'])
                @else
                    <li class="genealogy-branch">
                        <div class="genealogy-node genealogy-node-vacant">
                            <div class="badge bg-info-subtle text-info-emphasis mb-1" style="font-size: .62rem;">LEFT SLOT</div>
                            <div class="text-body-tertiary small"><i class="bi bi-plus-circle-dotted d-block fs-5 mb-1 opacity-50"></i>Vacant Left</div>
                        </div>
                    </li>
                @endif

                {{-- Right Branch --}}
                @if ($node['right'])
                    @include('admin.partials.tree-node', ['node' => $node['right'], 'position' => 'right'])
                @else
                    <li class="genealogy-branch">
                        <div class="genealogy-node genealogy-node-vacant">
                            <div class="badge bg-success-subtle text-success mb-1" style="font-size: .62rem;">RIGHT SLOT</div>
                            <div class="text-body-tertiary small"><i class="bi bi-plus-circle-dotted d-block fs-5 mb-1 opacity-50"></i>Vacant Right</div>
                        </div>
                    </li>
                @endif
            </ul>
        @endif
    </li>
@endif
