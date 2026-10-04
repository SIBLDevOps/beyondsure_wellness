<div class="mb-4">
    <div class="d-inline-flex p-1 bg-white border rounded-pill shadow-sm flex-nowrap overflow-auto" style="gap: .35rem; max-width: 100%;">
        @foreach ([
            'member.downline.index' => ['Binary Tree', 'Binary Genealogy Tree', 'bi-diagram-3'],
            'member.team.index' => ['Direct Team', 'Direct Sponsored Team', 'bi-people'],
            'member.levels.index' => ['Level Income', 'Level-Wise Income', 'bi-bar-chart-steps'],
        ] as $route => [$shortLabel, $fullLabel, $icon])
            @php $isActive = request()->routeIs($route); @endphp
            <a href="{{ route($route) }}"
               class="rounded-pill text-decoration-none small fw-semibold d-inline-flex align-items-center gap-2 flex-shrink-0 {{ $isActive ? 'bg-success text-white shadow-sm' : 'text-secondary' }}"
               style="padding: .45rem 1.15rem; transition: all .2s ease;">
                <i class="bi {{ $icon }} {{ $isActive ? 'text-white' : 'text-success' }}"></i>
                <span class="d-none d-md-inline">{{ $fullLabel }}</span>
                <span class="d-md-none">{{ $shortLabel }}</span>
            </a>
        @endforeach
    </div>
</div>
