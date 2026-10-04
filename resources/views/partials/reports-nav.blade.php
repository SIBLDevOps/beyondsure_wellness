<ul class="nav nav-underline-tabs mb-3">
    @foreach ([
        'admin.reports.overview' => 'Business overview',
        'admin.reports.sales' => 'Sales & products',
        'admin.reports.repurchase' => 'Repurchase',
        'admin.reports.payouts' => 'Payouts',
        'admin.reports.levels' => 'Level-wise income',
        'admin.reports.pending' => 'Pending & cash',
        'admin.reports.compliance' => 'Compliance / audit',
    ] as $route => $label)
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

@if (isset($from) && isset($to))
    <div class="card border bg-light-subtle mb-4">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <span class="small fw-semibold text-secondary"><i class="bi bi-calendar-range me-1 text-success"></i>Date Range:</span>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-secondary">From</span>
                        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control">
                    </div>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-secondary">To</span>
                        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control">
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-dark btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
                </div>
            </form>
        </div>
    </div>
@endif
