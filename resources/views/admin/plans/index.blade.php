@extends('layouts.admin')

@section('title', 'Plans')

@section('content')
    @php
        $inr = fn ($n) => '₹' . number_format($n, 0);
        $can = fn (string $r) => auth()->user()->canAccessRoute($r);
        $cascadePct = !empty($levelPcts) ? array_sum($levelPcts) : ($matchingPct * $cascadeDepth);
        // Everything a card needs, worked out once per plan.
        $cards = $plans->map(function ($p) use ($costPct, $selfPct, $sponsorPct, $cascadePct) {
            $cost = $p->costForQty();
            $limit = $p->price * $costPct / 100;

            return (object) [
                'plan' => $p, 'cost' => $cost, 'limit' => $limit,
                'over' => $p->is_active && $cost > $limit,
                'gst' => $p->gstAmount(), 'total' => $p->totalPayable(),
                'self' => $p->bv * $selfPct / 100, 'sponsor' => $p->bv * $sponsorPct / 100, 'levels' => $p->bv * $cascadePct / 100,
            ];
        });
    @endphp

    <style>
        .pl-stat { border: 1px solid var(--bs-border-color); border-radius: .9rem; padding: .8rem 1rem; background: var(--bs-body-bg); height: 100%; }
        .pl-stat .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); }
        .pl-stat .v { font-size: 1.6rem; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
        .pl-card { border-radius: 1rem; transition: box-shadow .15s, transform .15s; }
        .pl-card:hover { box-shadow: 0 .5rem 1.2rem rgba(15, 23, 42, .08); transform: translateY(-2px); }
        .pl-card.is-off { opacity: .72; }
        .pl-price { font-size: 1.9rem; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .pl-chip { display: inline-flex; align-items: center; gap: .25rem; font-size: .74rem; padding: .22rem .6rem; border-radius: 99px; background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); }
        .pl-health { border-radius: .7rem; padding: .55rem .8rem; font-size: .8rem; line-height: 1.4; }
        .pl-health.ok { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
        .pl-health.warn { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
        .pl-earn { border: 1px solid var(--bs-border-color); border-radius: .7rem; padding: .45rem .3rem; text-align: center; }
        .pl-earn b { display: block; font-size: .95rem; font-variant-numeric: tabular-nums; }
        .pl-earn span { font-size: .68rem; color: var(--bs-secondary-color); }
        .pl-filter.active { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
        details.pl-levels summary { cursor: pointer; font-size: .75rem; color: var(--bs-primary); list-style: none; }
        details.pl-levels summary::-webkit-details-marker { display: none; }
    </style>

    {{-- Header --}}
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
        <div style="max-width: 640px;">
            <h2 class="h5 fw-bold mb-1">Plans</h2>
            <p class="text-secondary mb-0 small">
                A plan is a bundle of products that members buy. Set its <strong>price</strong> (GST is added on top at checkout) and its
                <strong>BV</strong>, the part of each sale that is shared out as member income.
            </p>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            @if ($can('admin.reports.levels'))<a href="{{ route('admin.reports.levels') }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Level distribution</a>@endif
            @if ($can('admin.plans.create'))<a href="{{ route('admin.plans.create', $regionId ? ['region' => $regionId] : []) }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>New plan</a>@endif
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><div class="pl-stat"><div class="k">All plans</div><div class="v">{{ $stats['total'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="pl-stat"><div class="k">Active plans</div><div class="v text-success">{{ $stats['active'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="pl-stat"><div class="k">Inactive</div><div class="v text-secondary">{{ $stats['inactive'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="pl-stat {{ $stats['attention'] ? 'border-warning' : '' }}"><div class="k">Needs attention</div><div class="v {{ $stats['attention'] ? 'text-warning-emphasis' : '' }}">{{ $stats['attention'] }}</div></div></div>
    </div>

    @include('admin.partials.region-filter')

    @if ($plans->isEmpty())
        <div class="card border text-center py-5">
            <div class="card-body">
                <i class="bi bi-layers fs-1 text-body-tertiary"></i>
                <h3 class="h5 mt-2">No plans yet</h3>
                <p class="text-secondary mb-3">Plans are what members buy. Add products first, then bundle them into a plan.</p>
                @if ($can('admin.plans.create'))<a href="{{ route('admin.plans.create') }}" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Create your first plan</a>@endif
            </div>
        </div>
    @else
        {{-- Tabs + toolbar --}}
        <ul class="nav nav-pills mb-3 gap-1" role="tablist">
            <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="pill" data-bs-target="#tab-cards" type="button" role="tab"><i class="bi bi-grid me-1"></i>Plan cards</button></li>
            <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#tab-compare" type="button" role="tab"><i class="bi bi-table me-1"></i>Compare all plans</button></li>
        </ul>

        <div class="tab-content">
            {{-- Cards --}}
            <div class="tab-pane fade show active" id="tab-cards" role="tabpanel">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="planSearch" type="search" class="form-control" placeholder="Search by plan or product…" autocomplete="off">
                    </div>
                    <div class="d-flex gap-1 flex-wrap" id="planFilters">
                        <button type="button" class="btn btn-sm btn-outline-secondary pl-filter active" data-filter="all">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary pl-filter" data-filter="active">Active</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary pl-filter" data-filter="inactive">Inactive</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary pl-filter" data-filter="attention">Needs attention</button>
                    </div>
                </div>

                <div class="row g-4" id="planGrid">
                    @foreach ($cards as $c)
                        @php $p = $c->plan; @endphp
                        <div class="col-md-6 col-xl-4 plan-col"
                             data-plan-name="{{ strtolower($p->name) }}"
                             data-search="{{ strtolower($p->name.' '.$p->products->pluck('name')->join(' ')) }}"
                             data-status="{{ $p->is_active ? 'active' : 'inactive' }}"
                             data-attention="{{ $c->over ? '1' : '0' }}">
                            <div class="card border h-100 pl-card {{ $p->is_active ? '' : 'is-off' }}">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div class="min-w-0">
                                            <div class="fw-bold fs-5 text-truncate" title="{{ $p->name }}">{{ $p->name }}</div>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-normal"><i class="bi bi-geo-alt me-1"></i>{{ $p->region?->name }}</span>
                                        </div>
                                        <span class="badge rounded-pill {{ $p->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>

                                    <div class="mt-3">
                                        <div class="text-secondary small">Member pays</div>
                                        <div class="d-flex align-items-baseline gap-2 flex-wrap">
                                            <span class="pl-price">{{ $inr($p->price) }}</span>
                                            <span class="text-secondary small">+ GST {{ $inr($c->gst) }} = <strong class="text-body">{{ $inr($c->total) }}</strong></span>
                                        </div>
                                        <span class="pl-chip mt-2"><i class="bi bi-coin"></i>BV {{ $inr($p->bv) }}</span>
                                        @php $noGst = $p->products->filter(fn ($x) => $x->gst_pct === null)->count(); @endphp
                                        @if ($noGst)
                                            <div class="small text-warning-emphasis mt-2"><i class="bi bi-exclamation-circle me-1"></i>GST rate missing on {{ $noGst }} {{ Str::plural('product', $noGst) }}, so it is charged no GST.
                                                @if ($can('admin.products.index'))<a href="{{ route('admin.products.index') }}" class="text-decoration-none">Fix in Products</a>@endif</div>
                                        @endif
                                    </div>

                                    <div class="mt-3">
                                        <div class="small fw-semibold mb-1">What's inside</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @forelse ($p->products as $prod)
                                                <span class="pl-chip">{{ $prod->pivot->qty }}× {{ $prod->name }}</span>
                                            @empty
                                                <span class="text-body-tertiary small">No products added yet.</span>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="pl-health mt-3 {{ $c->over ? 'warn' : 'ok' }}">
                                        @if ($c->over)
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                            Product cost {{ $inr($c->cost) }} is over its {{ $inr($c->limit) }} limit by {{ $inr($c->cost - $c->limit) }}. <strong>Review pricing.</strong>
                                        @elseif (! $p->is_active)
                                            <i class="bi bi-pause-circle me-1"></i>Switched off. Members cannot buy it.
                                        @else
                                            <i class="bi bi-check-circle-fill me-1"></i>Product cost {{ $inr($c->cost) }} is within its limit of {{ $inr($c->limit) }}.
                                        @endif
                                    </div>

                                    <div class="mt-3">
                                        <div class="small fw-semibold mb-1">Member income from one sale</div>
                                        <div class="row g-2">
                                            <div class="col-4"><div class="pl-earn"><b class="text-success">{{ $inr($c->self) }}</b><span>Buyer ({{ $selfPct }}%)</span></div></div>
                                            <div class="col-4"><div class="pl-earn"><b class="text-info-emphasis">{{ $inr($c->sponsor) }}</b><span>Sponsor ({{ $sponsorPct }}%)</span></div></div>
                                            <div class="col-4"><div class="pl-earn"><b style="color:#6d28d9;">{{ $inr($c->levels) }}</b><span>Levels, up to</span></div></div>
                                        </div>
                                        @if (! empty($levelPcts))
                                            <details class="pl-levels mt-1">
                                                <summary>See the {{ count($levelPcts) }} levels</summary>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    @foreach ($levelPcts as $lvl => $pct)
                                                        <span class="pl-chip" style="font-size:.68rem;">L{{ $lvl }} {{ $pct }}% · {{ $inr($p->bv * $pct / 100) }}</span>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>

                                    <div class="d-flex gap-2 mt-auto pt-3">
                                        @if ($can('admin.plans.edit'))
                                            <a href="{{ route('admin.plans.edit', $p) }}" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-pencil me-1"></i>Edit plan</a>
                                        @endif
                                        @if ($can('admin.plans.create'))
                                            <a href="{{ route('admin.plans.create', ['clone' => $p->id]) }}" class="btn btn-sm btn-outline-primary" title="Open the new-plan form filled from this plan, so you can change it before saving"><i class="bi bi-copy me-1"></i>Clone</a>
                                        @endif
                                        @if ($can('admin.plans.toggle'))
                                            <form method="POST" action="{{ route('admin.plans.toggle', $p) }}" onsubmit="return confirm('{{ $p->is_active ? 'Switch off this plan? Members will no longer be able to buy it.' : 'Switch this plan back on?' }}')">
                                                @csrf @method('PUT')
                                                <button class="btn btn-sm {{ $p->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}" title="{{ $p->is_active ? 'Switch off' : 'Switch on' }}">
                                                    <i class="bi bi-{{ $p->is_active ? 'pause-circle' : 'play-circle' }} me-1"></i>{{ $p->is_active ? 'Switch off' : 'Switch on' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="planNoMatch" class="text-center text-body-tertiary py-5 d-none"><i class="bi bi-search fs-3"></i><div class="mt-1">No plans match your search.</div></div>
            </div>

            {{-- Compare table --}}
            <div class="tab-pane fade" id="tab-compare" role="tabpanel">
                <div class="card border">
                    <div class="card-header bg-light-subtle border-bottom py-3">
                        <div class="fw-semibold">Compare all plans <span class="text-body-tertiary fw-normal">· Compensation Matrix</span></div>
                        <div class="text-body-tertiary small">What each plan pays out per sale (buyer, sponsor and level income). Click a column heading to sort.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" data-sortable>
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th class="ps-3" data-sort-key="text">Plan</th>
                                    <th class="text-end" data-sort-key="number">Price</th>
                                    <th class="text-end" data-sort-key="number">With GST</th>
                                    <th class="text-end" data-sort-key="number">BV</th>
                                    <th class="text-end" data-sort-key="number">Product cost</th>
                                    <th class="text-end" data-sort-key="number">Buyer</th>
                                    <th class="text-end" data-sort-key="number">Sponsor</th>
                                    <th class="text-end" data-sort-key="number">Cascade ({{ $cascadeDepth }} levels)</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cards as $c)
                                    @php $p = $c->plan; @endphp
                                    <tr data-plan-name="{{ strtolower($p->name) }}">
                                        <td class="ps-3"><a href="{{ $can('admin.plans.edit') ? route('admin.plans.edit', $p) : '#' }}" class="fw-semibold text-decoration-none text-body">{{ $p->name }}</a>
                                            <div class="text-body-tertiary" style="font-size:.72rem;">{{ $p->region?->name }} · {{ $p->products->count() }} {{ Str::plural('item', $p->products->count()) }}</div></td>
                                        <td class="text-end fw-bold" data-sort-value="{{ $p->price }}">{{ $inr($p->price) }}</td>
                                        <td class="text-end" data-sort-value="{{ $c->total }}">{{ $inr($c->total) }}</td>
                                        <td class="text-end text-primary fw-semibold" data-sort-value="{{ $p->bv }}">{{ $inr($p->bv) }}</td>
                                        <td class="text-end" data-sort-value="{{ $c->cost }}"><span class="{{ $c->over ? 'text-danger fw-semibold' : '' }}">{{ $inr($c->cost) }}</span> <span class="text-body-tertiary small">/ {{ $inr($c->limit) }}</span>@if ($c->over) <span class="badge bg-danger-subtle text-danger">Over</span>@endif</td>
                                        <td class="text-end text-success" data-sort-value="{{ $c->self }}">{{ $inr($c->self) }}</td>
                                        <td class="text-end text-info-emphasis" data-sort-value="{{ $c->sponsor }}">{{ $inr($c->sponsor) }}</td>
                                        <td class="text-end" style="color:#6d28d9;" data-sort-value="{{ $c->levels }}">{{ $inr($c->levels) }}</td>
                                        <td class="text-center"><span class="badge rounded-pill {{ $p->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('planSearch');
            const cols = [...document.querySelectorAll('.plan-col')];
            const empty = document.getElementById('planNoMatch');
            let filter = 'all';

            function apply() {
                const q = (search?.value || '').trim().toLowerCase();
                let shown = 0;
                cols.forEach(c => {
                    const okText = !q || c.dataset.search.includes(q);
                    const okFilter = filter === 'all' || (filter === 'attention' ? c.dataset.attention === '1' : c.dataset.status === filter);
                    const show = okText && okFilter;
                    c.classList.toggle('d-none', !show);
                    shown += show ? 1 : 0;
                });
                empty?.classList.toggle('d-none', shown > 0);
            }

            search?.addEventListener('input', apply);
            document.querySelectorAll('#planFilters .pl-filter').forEach(b => b.addEventListener('click', () => {
                filter = b.dataset.filter;
                document.querySelectorAll('#planFilters .pl-filter').forEach(x => x.classList.toggle('active', x === b));
                apply();
            }));
        });
    </script>
@endsection
