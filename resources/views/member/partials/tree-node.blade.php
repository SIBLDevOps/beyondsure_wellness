@php
    $position = $position ?? ($node['level'] === 1 ? 'root' : ($node['member']->position ?? 'left'));
@endphp

@if ($node)
    <li class="genealogy-branch">
        <div class="genealogy-node {{ $node['level'] === 1 ? 'genealogy-node-root' : '' }} {{ $node['active_this_month'] ? 'is-active-month' : 'is-inactive-month' }}">
            {{-- Top Position & Level Strip --}}
            <div class="d-flex align-items-center justify-content-between gap-1 mb-2 pb-1 border-bottom">
                @if ($node['level'] === 1)
                    <span class="badge bg-success text-white" style="font-size: .65rem; padding: .2rem .45rem;">
                        <i class="bi bi-star-fill me-1"></i>YOU (L1)
                    </span>
                @elseif ($position === 'left')
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: .65rem; padding: .2rem .45rem;">
                        <i class="bi bi-arrow-down-left me-1"></i>LEFT · L{{ $node['level'] }}
                    </span>
                @else
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: .65rem; padding: .2rem .45rem;">
                        <i class="bi bi-arrow-down-right me-1"></i>RIGHT · L{{ $node['level'] }}
                    </span>
                @endif

                <span class="d-inline-flex align-items-center gap-1" style="font-size: .65rem;">
                    <span class="rounded-circle {{ $node['active_this_month'] ? 'bg-success' : 'bg-secondary' }}" style="width:7px;height:7px;"></span>
                    <span class="{{ $node['active_this_month'] ? 'text-success fw-semibold' : 'text-secondary' }}">
                        {{ $node['active_this_month'] ? 'Active' : 'Inactive' }}
                    </span>
                </span>
            </div>

            {{-- Member Identity --}}
            <div class="d-flex align-items-center gap-2 text-start mb-2">
                <span class="avatar-circle avatar-circle-sm {{ $node['level'] === 1 ? 'bg-success' : ($position === 'left' ? 'bg-info' : 'bg-primary') }} text-white shadow-sm" style="font-weight: 700; width: 34px; height: 34px;">
                    {{ strtoupper(substr($node['member']->name, 0, 2)) }}
                </span>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-bold text-dark text-truncate" style="font-size: .82rem;" title="{{ $node['member']->name }}">
                        {{ $node['member']->name }}
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="font-monospace text-secondary" style="font-size: .7rem;">{{ $node['member']->member_code }}</span>
                        @if ($node['member']->rank && $node['member']->rank !== 'none')
                            <span class="badge bg-warning-subtle text-warning-emphasis text-capitalize" style="font-size: .6rem; padding: .15rem .35rem;">{{ $node['member']->rank }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Volume Metrics Strip --}}
            <div class="bg-light rounded-2 p-1 px-2 text-start border border-light-subtle" style="font-size: .68rem;">
                <div class="d-flex justify-content-between text-secondary">
                    <span>Own BV:</span>
                    <strong class="text-dark font-monospace">₹{{ number_format($node['personal_bv'] ?? 0, 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between text-secondary border-top mt-1 pt-1 font-monospace">
                    <span class="text-info-emphasis">L: ₹{{ number_format($node['left_bv'] ?? 0, 0) }}</span>
                    <span class="text-body-tertiary">|</span>
                    <span class="text-success">R: ₹{{ number_format($node['right_bv'] ?? 0, 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Child Branches --}}
        @if ($node['left'] || $node['right'] || $node['level'] === 1)
            <ul class="genealogy-children">
                {{-- Left Branch --}}
                @if ($node['left'])
                    @include('member.partials.tree-node', ['node' => $node['left'], 'position' => 'left'])
                @else
                    <li class="genealogy-branch">
                        <div class="genealogy-node genealogy-node-vacant">
                            <span class="badge bg-info-subtle text-info-emphasis mb-1" style="font-size: .62rem; padding: .2rem .4rem;">LEFT SLOT</span>
                            <div class="text-body-tertiary small"><i class="bi bi-plus-circle-dotted d-block fs-5 mb-1 opacity-50"></i>Vacant Left</div>
                            <a href="{{ route('register', ['sponsor' => auth('member')->user()?->member_code]) }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill py-0 px-2 mt-1" style="font-size: .62rem; text-decoration: none;" title="Invite new member with your sponsor code">
                                <i class="bi bi-person-plus me-1"></i>Invite
                            </a>
                        </div>
                    </li>
                @endif

                {{-- Right Branch --}}
                @if ($node['right'])
                    @include('member.partials.tree-node', ['node' => $node['right'], 'position' => 'right'])
                @else
                    <li class="genealogy-branch">
                        <div class="genealogy-node genealogy-node-vacant">
                            <span class="badge bg-success-subtle text-success mb-1" style="font-size: .62rem; padding: .2rem .4rem;">RIGHT SLOT</span>
                            <div class="text-body-tertiary small"><i class="bi bi-plus-circle-dotted d-block fs-5 mb-1 opacity-50"></i>Vacant Right</div>
                            <a href="{{ route('register', ['sponsor' => auth('member')->user()?->member_code]) }}" target="_blank" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 mt-1" style="font-size: .62rem; text-decoration: none;" title="Invite new member with your sponsor code">
                                <i class="bi bi-person-plus me-1"></i>Invite
                            </a>
                        </div>
                    </li>
                @endif
            </ul>
        @endif
    </li>
@endif
