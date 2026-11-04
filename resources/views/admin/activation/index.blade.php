@extends('layouts.admin')

@section('title', 'Pending activation')

@section('content')
    @php
        $can = fn (string $r) => auth()->user()->canAccessRoute($r);
        $staleDays = \App\Http\Controllers\Admin\ActivationController::STALE_DAYS;

        // Step => [label, icon, colour, what to do about it, which step bar it fills]
        $steps = [
            'registered' => ['Registered', 'bi-person-plus', 'secondary', 'Needs a nudge', 1],
            'order_placed' => ['Order placed', 'bi-bag', 'warning', 'Waiting for payment', 2],
            'payment_submitted' => ['Payment submitted', 'bi-credit-card', 'info', 'Verify now', 3],
        ];

        $keep = array_filter(['search' => $search, 'region' => $region, 'sort' => $sort === 'newest' ? 'newest' : null]);
        // Step tabs keep the "waiting 3+ days" choice; that tab switches it on or off and keeps the step.
        $stageUrl = fn (?string $s) => route('admin.activation.index', $keep + ($stale ? ['stale' => 1] : []) + ($s ? ['stage' => $s] : []));
        $staleUrl = route('admin.activation.index', $keep + ($stage !== 'all' ? ['stage' => $stage] : []) + ($stale ? [] : ['stale' => 1]));
        $filtered = $stage !== 'all' || $stale || $search !== '' || $region;
        $waiting = $counts['payment_submitted'];
    @endphp

    <style>
        .act-tab { display: block; height: 100%; padding: .8rem 1rem; border: 1.5px solid var(--bs-border-color); border-radius: 14px; background: var(--bs-body-bg); color: inherit; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
        .act-tab:hover { border-color: var(--bs-primary); color: inherit; }
        .act-tab[aria-current] { border-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), .12); }
        .act-row { border-left: 4px solid var(--bs-border-color); }
        .act-row[data-age="fresh"] { border-left-color: var(--bs-success); }
        .act-row[data-age="aging"] { border-left-color: var(--bs-warning); }
        .act-row[data-age="stale"] { border-left-color: var(--bs-danger); }
        .act-bar { display: flex; gap: 3px; }
        .act-bar span { flex: 1; height: 5px; border-radius: 3px; background: var(--bs-border-color); }
        .act-avatar { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; flex-shrink: 0; }
        .act-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); }
    </style>

    <div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
        <div style="max-width: 680px;">
            <h2 class="h5 fw-bold mb-1">Pending activation</h2>
            <p class="text-secondary small mb-0">
                Everyone who registered but has <strong>not paid yet</strong>. A person becomes an active member after you verify their payment.
                Until then they show as <em>Not active</em> in their sponsor's team and earn no sponsor or matching income (unless you allow it in Settings).
            </p>
        </div>
    </div>

    {{-- The one thing that needs the admin right now --}}
    @if ($can('admin.payments.index') && $waiting > 0)
        <div class="alert alert-info d-flex align-items-center justify-content-between gap-3 flex-wrap py-2 mb-3" role="status">
            <div><i class="bi bi-credit-card-2-front me-2"></i><strong>{{ $waiting }} {{ $waiting === 1 ? 'payment is' : 'payments are' }} waiting for you to verify.</strong>
                <span class="small">Check your bank, then approve them to activate these people.</span></div>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-primary"><i class="bi bi-check2-square me-1"></i>Open payments</a>
        </div>
    @endif

    {{-- The pipeline: everyone, each step, and who has waited too long. Click one to list only those. --}}
    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg">
            <a href="{{ $stageUrl(null) }}" class="act-tab" data-tab="all"@if ($stage === 'all') aria-current="page"@endif>
                <div class="d-flex align-items-center gap-2">
                    <span class="stat-icon bg-primary-subtle text-primary-emphasis" style="width:36px;height:36px;"><i class="bi bi-people"></i></span>
                    <div><div class="act-label">Everyone</div><div class="fs-4 fw-bold lh-1">{{ $counts['total'] }}</div></div>
                </div>
                <div class="small text-secondary mt-1">Not paid yet</div>
            </a>
        </div>
        @foreach ($steps as $key => [$title, $icon, $tone, $hint])
            <div class="col-6 col-lg">
                <a href="{{ $stageUrl($key) }}" class="act-tab" data-tab="{{ $key }}"@if ($stage === $key) aria-current="page"@endif>
                    <div class="d-flex align-items-center gap-2">
                        <span class="stat-icon bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis" style="width:36px;height:36px;"><i class="bi {{ $icon }}"></i></span>
                        <div><div class="act-label">{{ $title }}</div><div class="fs-4 fw-bold lh-1">{{ $counts[$key] }}</div></div>
                    </div>
                    <div class="small text-secondary mt-1">{{ $hint }}</div>
                </a>
            </div>
        @endforeach
        <div class="col-12 col-lg">
            <a href="{{ $staleUrl }}" class="act-tab" data-tab="stale"@if ($stale) aria-current="page"@endif>
                <div class="d-flex align-items-center gap-2">
                    <span class="stat-icon bg-danger-subtle text-danger-emphasis" style="width:36px;height:36px;"><i class="bi bi-clock-history"></i></span>
                    <div><div class="act-label">Waiting {{ $staleDays }}+ days</div><div class="fs-4 fw-bold lh-1">{{ $counts['stale'] }}</div></div>
                </div>
                <div class="small text-secondary mt-1">Chase these first</div>
            </a>
        </div>
    </div>

    @if ($counts['total'] === 0)
        <div class="card border text-center py-5"><div class="card-body">
            <i class="bi bi-check2-circle fs-1 text-success"></i>
            <h3 class="h6 fw-bold mt-2">Everyone who registered has paid</h3>
            <p class="text-secondary small mb-0">New sign-ups who have not paid yet will appear here.</p>
        </div></div>
    @else
        {{-- Filters: choosing a region or an order applies at once; search applies on Enter --}}
        <form method="GET" action="{{ route('admin.activation.index') }}" class="card border mb-3">
            <div class="card-body py-2">
                <input type="hidden" name="stage" value="{{ $stage }}">
                @if ($stale)<input type="hidden" name="stale" value="1">@endif
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                            <input id="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search by name, mobile or code" aria-label="Search">
                        </div>
                    </div>
                    @if ($regions->count() > 1)
                        <div class="col-6 col-md-auto">
                            <select id="region" name="region" class="form-select form-select-sm" aria-label="Region" onchange="this.form.submit()">
                                <option value="">All regions</option>
                                @foreach ($regions as $r)
                                    <option value="{{ $r->id }}" @selected($region === $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 col-sm-6 col-md-auto">
                        <select id="sort" name="sort" class="form-select form-select-sm" aria-label="Order" onchange="this.form.submit()">
                            <option value="oldest" @selected($sort === 'oldest')>Waiting longest first</option>
                            <option value="newest" @selected($sort === 'newest')>Newest first</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Search</button>
                        @if ($filtered || $sort === 'newest')
                            <a href="{{ route('admin.activation.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if ($people->isEmpty())
            <div class="card border text-center py-5"><div class="card-body">
                <i class="bi bi-search fs-2 text-body-tertiary"></i>
                <h3 class="h6 fw-bold mt-2">No one matches these filters</h3>
                <a href="{{ route('admin.activation.index') }}" class="btn btn-sm btn-outline-primary mt-1">Show everyone</a>
            </div></div>
        @else
            <div class="d-flex flex-column gap-2">
                @foreach ($people as $p)
                    @php
                        [$stageTitle, , $tone, , $progress] = $steps[$p->stage];
                        $ageTone = ['fresh' => 'success', 'aging' => 'warning', 'stale' => 'danger'][$p->age];
                        $initials = strtoupper(mb_substr(trim($p->name), 0, 1).(mb_substr(trim(strrchr(' '.trim($p->name), ' ')), 1, 1) ?: ''));
                    @endphp
                    <div class="card border act-row" data-age="{{ $p->age }}" data-stage="{{ $p->stage }}">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                {{-- Who --}}
                                <div class="col-12 col-md-6 col-xl-3 d-flex align-items-center gap-2 min-w-0">
                                    <span class="act-avatar bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis">{{ $initials }}</span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate" title="{{ $p->name }}">{{ $p->name }}</div>
                                        <div class="small text-secondary"><i class="bi bi-telephone me-1"></i>{{ $p->phone }}
                                            @if ($regions->count() > 1 && $p->region)<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-normal ms-1">{{ $p->region->name }}</span>@endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Where they are in the process --}}
                                <div class="col-6 col-md-3 col-xl-2">
                                    <span class="badge bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis">{{ $stageTitle }}</span>
                                    <div class="act-bar mt-2" data-progress="{{ $progress }}" aria-hidden="true">
                                        @foreach ([1, 2, 3, 4] as $i)<span @if ($i <= $progress) style="background: var(--bs-{{ $tone }});" @endif></span>@endforeach
                                    </div>
                                </div>

                                {{-- What they chose and still owe --}}
                                <div class="col-6 col-md-3 col-xl-2">
                                    <div class="act-label">Plan</div>
                                    <div class="small fw-medium text-truncate">{{ $p->planName ?? 'Not chosen yet' }}</div>
                                    @if ($p->amount !== null)<div class="small text-secondary">₹{{ number_format($p->amount) }} to pay</div>@endif
                                </div>

                                {{-- Who invited them and where they sit in that person's tree --}}
                                <div class="col-8 col-md-6 col-xl-2">
                                    <div class="act-label">Invited by</div>
                                    <div class="small fw-medium text-truncate">{{ $p->sponsor?->name ?? 'The company' }}</div>
                                    @if ($p->level)
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <span class="badge {{ $p->leg === 'left' ? 'bg-info-subtle text-info-emphasis' : 'bg-success-subtle text-success' }} fw-normal"><strong>{{ $p->leg === 'left' ? 'L' : 'R' }}</strong> &middot; {{ $p->leg === 'left' ? 'Left leg' : 'Right leg' }}</span>
                                            <span class="badge bg-light text-secondary border fw-normal">Level {{ $p->level }}</span>
                                        </div>
                                        @if ($p->placementParent && $p->placement_id !== $p->sponsor_id)
                                            <div class="text-secondary" style="font-size:.72rem;">Placed under {{ $p->placementParent->name }}</div>
                                        @endif
                                    @endif
                                </div>

                                {{-- How long, and what to do --}}
                                <div class="col-4 col-md-3 col-xl-1">
                                    <div class="act-label">Waiting</div>
                                    <span class="badge bg-{{ $ageTone }}-subtle text-{{ $ageTone }}-emphasis" title="Registered {{ $p->created_at->format('d M Y, h:i A') }}">{{ $p->created_at->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="col-12 col-md-3 col-xl-2 d-flex flex-wrap gap-2 justify-content-md-end">
                                    @if ($p->stage === 'payment_submitted' && $can('admin.payments.index'))
                                        <a href="{{ route('admin.payments.index') }}" data-action="verify" class="btn btn-sm btn-primary"><i class="bi bi-check2-square me-1"></i>Verify payment</a>
                                    @elseif ($p->stage !== 'payment_submitted')
                                        <a href="{{ $p->reminderUrl }}" target="_blank" rel="noopener" data-action="remind" class="btn btn-sm btn-success"><i class="bi bi-whatsapp me-1"></i>Remind</a>
                                    @endif
                                    <a href="tel:{{ $p->phone }}" class="btn btn-sm btn-outline-secondary" title="Call" aria-label="Call {{ $p->name }}"><i class="bi bi-telephone"></i></a>
                                    @if ($can('admin.members.show'))<a href="{{ route('admin.members.show', $p) }}" class="btn btn-sm btn-outline-secondary" title="Open profile" aria-label="Open {{ $p->name }}'s profile"><i class="bi bi-person-lines-fill"></i></a>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                <div class="small text-secondary">Showing {{ $people->firstItem() }}–{{ $people->lastItem() }} of {{ $people->total() }}</div>
                {{ $people->links('pagination::bootstrap-5') }}
            </div>
        @endif
    @endif
@endsection
