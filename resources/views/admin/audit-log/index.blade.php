@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
    @if ($setupNeeded ?? false)
        @include('partials.setup-needed', ['what' => 'The audit log'])
    @else
    @php
        $query = array_filter([
            'q' => $filters['q'], 'user' => $filters['user'], 'category' => $filters['category'], 'outcome' => $filters['outcome'],
            'from' => $filters['from']?->toDateString(), 'to' => $filters['to']?->toDateString(),
        ]);
        $filtered = $query !== [];
        $outcomeTone = ['success' => 'success', 'failed' => 'danger', 'denied' => 'warning'];
        $categoryTone = ['sign-in' => 'primary', 'security' => 'danger', 'settings' => 'info', 'cycles' => 'info', 'money' => 'success', 'catalog' => 'secondary', 'members' => 'secondary', 'staff' => 'warning', 'content' => 'secondary', 'export' => 'dark', 'other' => 'secondary'];
        $browser = function (?string $ua) {
            if (! $ua) { return null; }
            foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $needle => $name) {
                if (str_contains($ua, $needle)) { return $name; }
            }
            return \Illuminate\Support\Str::limit($ua, 24);
        };
    @endphp

    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
        <div style="max-width: 700px;">
            <h2 class="h5 fw-bold mb-1">Audit log</h2>
            <p class="text-secondary small mb-0">
                The track record of everyone who works in the admin panel, Super Admin included: every sign-in and sign-out, every refused attempt,
                every change with what it was and what it became, and every download. Newest first. Lines can never be edited or deleted.
                Passwords, bank details and free text people typed are never written down.
            </p>
        </div>
        <a href="{{ route('admin.audit-log.csv', $query) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Download{{ $filtered ? ' these lines' : '' }} (CSV)</a>
    </div>

    @unless ($extended)
        <div class="alert alert-warning small" role="alert">
            <i class="bi bi-database-gear me-1"></i><strong>One database update is needed</strong> to also record roles, categories, outcomes and the browser,
            and to protect the log in the database itself. Lines are still being recorded. Run <code>php artisan migrate --force</code> once.
        </div>
    @endunless

    {{-- The last 24 hours --}}
    <div class="row g-3 mb-3">
        @foreach ([
            ['Events in 24 hours', $summary['events'], 'bi-activity', 'primary'],
            ['Failed sign-ins', $summary['failed_sign_ins'], 'bi-shield-exclamation', $summary['failed_sign_ins'] ? 'danger' : 'success'],
            ['Refused attempts', $summary['denied'], 'bi-slash-circle', $summary['denied'] ? 'warning' : 'success'],
            ['Staff active', $summary['staff_active'], 'bi-people', 'info'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-6 col-lg-3">
                <div class="border rounded-3 p-3 bg-body h-100 d-flex align-items-center gap-3">
                    <span class="stat-icon bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis" style="width:38px;height:38px;"><i class="bi {{ $icon }}"></i></span>
                    <div><div class="small text-secondary">{{ $label }}</div><div class="fs-4 fw-bold lh-1">{{ $value }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Who has signed in, and when --}}
    <details class="card border mb-3" @if (count($staff) <= 6) open @endif>
        <summary class="card-header bg-light-subtle py-2 fw-semibold" style="cursor:pointer;"><i class="bi bi-person-badge me-2 text-primary"></i>Staff sign-ins</summary>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light small text-secondary"><tr><th class="ps-3">Person</th><th>Role</th><th>Last sign-in</th><th>From</th><th class="pe-3 text-end">Activity</th></tr></thead>
                <tbody>
                    @foreach ($staff as $s)
                        <tr>
                            <td class="ps-3 fw-medium">{{ $s['name'] }} @unless ($s['active'])<span class="badge bg-secondary-subtle text-secondary fw-normal">Switched off</span>@endunless</td>
                            <td class="small">{{ $s['role'] === 'admin' ? 'Super Admin' : 'Sub-admin' }}</td>
                            <td class="small">@if ($s['last_at']){{ $s['last_at']->format('d M Y, h:i A') }} <span class="text-secondary">({{ $s['last_at']->diffForHumans() }})</span>@else<span class="text-body-tertiary">Never signed in</span>@endif</td>
                            <td class="small text-secondary">{{ $s['last_ip'] ?? '—' }}</td>
                            <td class="pe-3 text-end"><a href="{{ route('admin.audit-log.index', ['user' => $s['id']]) }}" class="small">See what they did</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.audit-log.index') }}" class="card border mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-xl-3">
                    <label class="form-label small mb-1" for="q">Search</label>
                    <input id="q" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Person, action, item, change or address">
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small mb-1" for="user">Person</label>
                    <select id="user" name="user" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Everyone</option>
                        @foreach ($staffList as $u)<option value="{{ $u->id }}" @selected($filters['user'] === $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                @if ($extended)
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label small mb-1" for="category">Kind</label>
                        <select id="category" name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All kinds</option>
                            @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="outcome">Result</label>
                        <select id="outcome" name="outcome" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Any</option>
                            <option value="success" @selected($filters['outcome'] === 'success')>Worked</option>
                            <option value="failed" @selected($filters['outcome'] === 'failed')>Failed sign-in</option>
                            <option value="denied" @selected($filters['outcome'] === 'denied')>Refused</option>
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-3 col-xl-1">
                    <label class="form-label small mb-1" for="from">From</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from']?->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                    <label class="form-label small mb-1" for="to">To</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to']?->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                    @if ($filtered)<a href="{{ route('admin.audit-log.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>@endif
                </div>
            </div>
        </div>
    </form>

    <div class="card border">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr><th class="ps-3">When</th><th>Who</th><th>Kind</th><th>What</th><th>Change</th><th class="pe-3">From</th></tr>
                </thead>
                <tbody>
                    @forelse ($entries as $e)
                        <tr>
                            <td class="ps-3 small text-nowrap">{{ $e->created_at->format('d M Y, h:i:s A') }}</td>
                            <td class="small">
                                {{ $e->user_name ?? '—' }}
                                @if ($extended && $e->user_role)<div class="text-secondary" style="font-size:.7rem;">{{ $e->user_role === 'admin' ? 'Super Admin' : 'Sub-admin' }}</div>@endif
                            </td>
                            <td class="small text-nowrap">
                                @if ($extended)
                                    <span class="badge bg-{{ $categoryTone[$e->category] ?? 'secondary' }}-subtle text-{{ $categoryTone[$e->category] ?? 'secondary' }}-emphasis fw-normal">{{ $categories[$e->category] ?? 'Other' }}</span>
                                    @if ($e->outcome && $e->outcome !== 'success')<span class="badge bg-{{ $outcomeTone[$e->outcome] ?? 'secondary' }} fw-normal">{{ $e->outcome === 'failed' ? 'Failed' : 'Refused' }}</span>@endif
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td class="small">
                                <div class="font-monospace">{{ $e->action }}@if ($e->method)<span class="text-secondary"> · {{ $e->method }}</span>@endif</div>
                                @if ($e->subject)<div class="text-secondary">{{ $e->subject }}</div>@endif
                            </td>
                            <td class="small" style="max-width: 360px; overflow-wrap: anywhere;">
                                @if (str_starts_with($e->action, 'change.'))
                                    <span class="text-body">{{ $e->details ?? '—' }}</span>
                                @else
                                    <span class="text-secondary">{{ $e->details ? 'Fields sent: '.$e->details : '—' }}</span>
                                @endif
                            </td>
                            <td class="pe-3 small text-secondary">{{ $e->ip }}@if ($extended && $browser($e->user_agent))<div style="font-size:.7rem;">{{ $browser($e->user_agent) }}</div>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-tertiary py-5">Nothing recorded{{ $filtered ? ' for those filters' : ' yet' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())<div class="card-footer bg-white">{{ $entries->links('pagination::bootstrap-5') }}</div>@endif
    </div>
    @endif
@endsection
