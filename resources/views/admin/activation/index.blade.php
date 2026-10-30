@extends('layouts.admin')

@section('title', 'Pending activation')

@section('content')
    @php
        $can = fn (string $r) => auth()->user()->canAccessRoute($r);
        $steps = [
            'registered' => ['Registered', 'bi-person-plus', 'secondary', 'No order yet'],
            'order_placed' => ['Order placed', 'bi-bag', 'warning', 'Payment not sent'],
            'payment_submitted' => ['Payment submitted', 'bi-credit-card', 'info', 'Waiting for you to verify'],
        ];
        $filters = array_filter(['search' => $search, 'region' => $region, 'sort' => $sort === 'newest' ? 'newest' : null]);
        $tabUrl = fn (?string $s) => route('admin.activation.index', $filters + ($s ? ['stage' => $s] : []));
        $filtered = $stage !== 'all' || $search !== '' || $region;
    @endphp

    <div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
        <div style="max-width: 680px;">
            <h2 class="h5 fw-bold mb-1">Pending activation</h2>
            <p class="text-secondary small mb-0">
                Everyone who registered but has <strong>not paid yet</strong>, by step. A person becomes an active member, and shows in their sponsor's team,
                only after you verify their payment.
            </p>
        </div>
        @if ($can('admin.payments.index') && $counts['payment_submitted'] > 0)
            <a href="{{ route('admin.payments.index') }}" class="btn btn-primary flex-shrink-0"><i class="bi bi-check2-square me-1"></i>Verify {{ $counts['payment_submitted'] }} {{ \Illuminate\Support\Str::plural('payment', $counts['payment_submitted']) }}</a>
        @endif
    </div>

    {{-- The steps, with counts for everybody. Click one to list only that step. --}}
    <div class="row g-3 mb-3">
        @foreach ($steps as $key => [$title, $icon, $tone, $hint])
            <div class="col-md-4">
                <a href="{{ $tabUrl($stage === $key ? null : $key) }}" class="text-decoration-none">
                    <div class="border rounded-3 p-3 h-100 bg-body {{ $stage === $key ? 'border-primary border-2' : '' }}">
                        <div class="d-flex align-items-center gap-2">
                            <span class="stat-icon bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis" style="width:38px;height:38px;"><i class="bi {{ $icon }}"></i></span>
                            <div>
                                <div class="small text-secondary">{{ $loop->iteration }}. {{ $title }}</div>
                                <div class="fs-3 fw-bold lh-1 text-body">{{ $counts[$key] }}</div>
                            </div>
                            <div class="ms-auto small text-secondary text-end d-none d-xl-block">{{ $hint }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if ($counts['total'] === 0)
        <div class="card border text-center py-5"><div class="card-body">
            <i class="bi bi-check2-circle fs-1 text-success"></i>
            <h3 class="h6 fw-bold mt-2">Everyone who registered has paid</h3>
            <p class="text-secondary small mb-0">New sign-ups who have not paid yet will appear here.</p>
        </div></div>
    @else
        <div class="card border">
            <div class="card-header bg-light-subtle border-bottom py-3">
                <form method="GET" action="{{ route('admin.activation.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="stage">Step</label>
                        <select id="stage" name="stage" class="form-select form-select-sm">
                            <option value="all" @selected($stage === 'all')>All steps ({{ $counts['total'] }})</option>
                            @foreach ($steps as $key => [$title])
                                <option value="{{ $key }}" @selected($stage === $key)>{{ $title }} ({{ $counts[$key] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="search">Search</label>
                        <input id="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Name, mobile or code">
                    </div>
                    @if ($regions->count() > 1)
                        <div class="col-md-2">
                            <label class="form-label small mb-1" for="region">Region</label>
                            <select id="region" name="region" class="form-select form-select-sm">
                                <option value="">All regions</option>
                                @foreach ($regions as $r)
                                    <option value="{{ $r->id }}" @selected($region === $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-2">
                        <label class="form-label small mb-1" for="sort">Order</label>
                        <select id="sort" name="sort" class="form-select form-select-sm">
                            <option value="oldest" @selected($sort === 'oldest')>Waiting longest first</option>
                            <option value="newest" @selected($sort === 'newest')>Newest first</option>
                        </select>
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                        @if ($filtered || $sort === 'newest')
                            <a href="{{ route('admin.activation.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </form>
            </div>

            @if ($people->isEmpty())
                <div class="card-body text-center py-5">
                    <i class="bi bi-search fs-2 text-body-tertiary"></i>
                    <h3 class="h6 fw-bold mt-2">No one matches these filters</h3>
                    <a href="{{ route('admin.activation.index') }}" class="btn btn-sm btn-outline-primary mt-1">Show everyone</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th class="ps-3">Person</th>
                                <th>Step</th>
                                <th>Plan</th>
                                <th>Invited by</th>
                                <th>Waiting</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($people as $p)
                                @php [$stageTitle, , $stageTone] = $steps[$p->stage]; @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold">{{ $p->name }}</div>
                                        <div class="text-secondary small"><i class="bi bi-telephone me-1"></i>{{ $p->phone }}
                                            @if ($regions->count() > 1 && $p->region)<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-normal ms-1">{{ $p->region->name }}</span>@endif
                                        </div>
                                    </td>
                                    <td><span class="badge bg-{{ $stageTone }}-subtle text-{{ $stageTone }}-emphasis">{{ $stageTitle }}</span></td>
                                    <td class="small">{{ $p->planName ?? '—' }}</td>
                                    <td class="small">{{ $p->sponsor?->name ?? 'The company' }}</td>
                                    <td class="small text-secondary" title="{{ $p->created_at->format('d M Y, h:i A') }}">{{ $p->created_at->diffForHumans(null, true) }}</td>
                                    <td class="text-end pe-3 text-nowrap">
                                        @if ($p->stage === 'payment_submitted' && $can('admin.payments.index'))
                                            <a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-primary">Verify payment</a>
                                        @elseif ($p->stage !== 'payment_submitted')
                                            <a href="{{ $p->reminderUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-whatsapp me-1"></i>Remind</a>
                                        @endif
                                        @if ($can('admin.members.show'))<a href="{{ route('admin.members.show', $p) }}" class="btn btn-sm btn-outline-secondary">Profile</a>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="small text-secondary">Showing {{ $people->firstItem() }}–{{ $people->lastItem() }} of {{ $people->total() }}</div>
                    {{ $people->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    @endif
@endsection
