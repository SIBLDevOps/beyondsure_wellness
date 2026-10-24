<style>
    .rep-tabs { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .35rem; margin-bottom: .85rem; scrollbar-width: thin; }
    .rep-tabs a { flex: none; white-space: nowrap; padding: .45rem .95rem; border-radius: 99px; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); color: var(--bs-secondary-color); font-size: .84rem; font-weight: 500; text-decoration: none; transition: .12s; }
    .rep-tabs a:hover { border-color: var(--bs-primary); color: var(--bs-body-color); }
    .rep-tabs a.active { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; font-weight: 600; }
    .rep-range { display: flex; flex-wrap: wrap; gap: .6rem .9rem; align-items: center; }
    .rep-range .presets { display: flex; flex-wrap: wrap; gap: .35rem; }
    .rep-range .presets a { font-size: .75rem; padding: .22rem .65rem; border-radius: 99px; border: 1px solid var(--bs-border-color); text-decoration: none; color: var(--bs-secondary-color); background: var(--bs-body-bg); }
    .rep-range .presets a:hover { border-color: var(--bs-primary); color: var(--bs-primary); }
    .rep-range .presets a.on { background: var(--bs-primary-bg-subtle); border-color: var(--bs-primary); color: var(--bs-primary-text-emphasis); font-weight: 600; }
</style>

@php
    $repRegions = \App\Models\Region::orderBy('sort_order')->get();
    $repRegion = request()->integer('region') ?: null;
@endphp
<nav class="rep-tabs" aria-label="Reports">
    @foreach ([
        'admin.reports.overview' => 'Business overview',
        'admin.reports.sales' => 'Sales & products',
        'admin.reports.repurchase' => 'Repurchase',
        'admin.reports.payouts' => 'Payouts',
        'admin.reports.levels' => 'Level-wise income',
        'admin.reports.pending' => 'Pending & cash',
        'admin.reports.compliance' => 'Compliance / audit',
    ] as $route => $label)
        @continue(! auth()->user()->canAccessRoute($route))
        <a href="{{ route($route, $repRegion ? ['region' => $repRegion] : []) }}" class="{{ request()->routeIs($route) ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>

@if ($repRegions->count() > 1)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="small fw-semibold text-secondary"><i class="bi bi-geo-alt me-1"></i>Region</span>
        <a href="{{ request()->fullUrlWithQuery(['region' => null, 'page' => null]) }}" class="btn btn-sm rounded-pill {{ $repRegion ? 'btn-outline-secondary' : 'btn-primary' }}">All regions</a>
        @foreach ($repRegions as $r)
            <a href="{{ request()->fullUrlWithQuery(['region' => $r->id, 'page' => null]) }}" class="btn btn-sm rounded-pill {{ $repRegion === $r->id ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $r->name }}</a>
        @endforeach
        @if ($repRegion)<span class="small text-secondary ms-1">Showing <strong>{{ $repRegions->firstWhere('id', $repRegion)?->name }}</strong> only</span>@endif
    </div>
@endif

@if (isset($from) && isset($to))
    @php
        $fmt = fn ($d) => $d->format('Y-m-d');
        $today = now();
        $presets = [
            'Today' => [$today->copy(), $today->copy()],
            'Last 7 days' => [$today->copy()->subDays(6), $today->copy()],
            'Last 30 days' => [$today->copy()->subDays(29), $today->copy()],
            'This month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'This year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
        ];
    @endphp
    <div class="card border mb-4">
        <div class="card-body py-3 px-3">
            <form method="GET" class="rep-range">
                @if ($repRegion)<input type="hidden" name="region" value="{{ $repRegion }}">@endif
                <span class="small fw-semibold text-secondary"><i class="bi bi-calendar-range me-1 text-success"></i>Date range</span>
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text">From</span>
                    <input type="date" name="from" value="{{ $fmt($from) }}" class="form-control">
                </div>
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text">To</span>
                    <input type="date" name="to" value="{{ $fmt($to) }}" class="form-control">
                </div>
                <button class="btn btn-primary btn-sm px-3"><i class="bi bi-funnel me-1"></i>Apply</button>
                <div class="presets ms-lg-auto">
                    @foreach ($presets as $label => [$a, $b])
                        <a href="{{ request()->url() }}?from={{ $fmt($a) }}&to={{ $fmt($b) }}{{ $repRegion ? '&region='.$repRegion : '' }}" class="{{ $fmt($from) === $fmt($a) && $fmt($to) === $fmt($b) ? 'on' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </form>
        </div>
    </div>
@endif
