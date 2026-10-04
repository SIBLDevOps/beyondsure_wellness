<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — BeyondSure</title>
    @vite(['resources/sass/admin.scss', 'resources/js/admin.js'])
</head>
<body>
    @php
        $unreadMessages = \App\Models\ContactMessage::whereNull('read_at')->count();
        $navSections = [
            'Overview' => [
                'admin.dashboard' => ['Dashboard', 'bi-grid-1x2', null],
            ],
            'Catalog & Commerce' => [
                'admin.categories.index' => ['Categories', 'bi-tag', null],
                'admin.products.index' => ['Products', 'bi-box-seam', null],
                'admin.plans.index' => ['Plans', 'bi-layers', null],
            ],
            'Transactions' => [
                'admin.orders.index' => ['Orders', 'bi-bag-check', null],
                'admin.payments.index' => ['Payments', 'bi-credit-card', null],
                'admin.cycles.index' => ['Cycles', 'bi-arrow-repeat', null],
            ],
            'Payouts' => [
                'admin.withdrawals.index' => ['Withdrawals', 'bi-cash-coin', null],
                'admin.dispatch.index' => ['Dispatch', 'bi-truck', null],
            ],
            'Network' => [
                'admin.members.index' => ['Members', 'bi-people', null],
                'admin.reports.overview' => ['Reports', 'bi-bar-chart-line', null],
            ],
            'Content & Config' => [
                'admin.messages.index' => ['Messages', 'bi-envelope', $unreadMessages ?: null],
                'admin.site-content.edit' => ['Homepage', 'bi-file-earmark-text', null],
                'admin.testimonials.index' => ['Testimonials', 'bi-star', null],
                'admin.settings.edit' => ['Settings', 'bi-gear', null],
            ],
        ];
    @endphp

    <div class="d-flex">
        {{-- Desktop sidebar --}}
        <aside class="admin-sidebar d-none d-lg-flex flex-column p-3 flex-shrink-0 sticky-top" style="height: 100vh; max-height: 100vh;">
            <div class="d-flex align-items-center justify-content-between mb-3 px-1 flex-shrink-0">
                <span class="brand">BeyondSure<span class="accent">+</span></span>
                <span class="badge bg-white bg-opacity-10 text-indigo-100 small">Admin</span>
            </div>

            <div class="admin-profile-card mb-2 flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-circle avatar-circle-sm bg-primary border border-white border-opacity-25">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-white text-truncate small">{{ auth()->user()->name }}</div>
                        <div class="text-white-50" style="font-size: .7rem;">Administrator</div>
                    </div>
                </div>
            </div>

            <nav class="nav flex-column flex-nowrap gap-1 overflow-y-auto overflow-x-hidden flex-grow-1 pe-1">
                @foreach ($navSections as $sectionLabel => $items)
                    <div class="nav-section-label">{{ $sectionLabel }}</div>
                    @foreach ($items as $route => [$label, $icon, $badge])
                        @php 
                            $routePrefix = implode('.', array_slice(explode('.', $route), 0, 2));
                            $active = $route === 'admin.dashboard' 
                                ? request()->routeIs($route) 
                                : (request()->routeIs($route) || request()->routeIs($routePrefix . '.*'));
                        @endphp
                        <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}">
                            <i class="bi {{ $icon }}"></i>
                            <span class="flex-grow-1">{{ $label }}</span>
                            @if ($badge)
                                <span class="badge bg-success rounded-pill">{{ $badge }}</span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="pt-2 mt-auto border-top border-white border-opacity-10 flex-shrink-0">
                <a href="{{ route('home') }}" target="_blank" class="nav-link py-1 small text-white-50">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Public Website</span>
                </a>
            </div>
        </aside>

        {{-- Mobile offcanvas sidebar --}}
        <div class="offcanvas offcanvas-start admin-sidebar d-lg-none" tabindex="-1" id="adminSidebar">
            <div class="offcanvas-header">
                <span class="brand">BeyondSure<span class="accent">+</span></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column">
                <nav class="nav flex-column flex-nowrap gap-1 overflow-y-auto overflow-x-hidden flex-grow-1">
                    @foreach ($navSections as $sectionLabel => $items)
                        <div class="nav-section-label">{{ $sectionLabel }}</div>
                        @foreach ($items as $route => [$label, $icon, $badge])
                            @php 
                                $routePrefix = implode('.', array_slice(explode('.', $route), 0, 2));
                                $active = $route === 'admin.dashboard' 
                                    ? request()->routeIs($route) 
                                    : (request()->routeIs($route) || request()->routeIs($routePrefix . '.*'));
                            @endphp
                            <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}">
                                <i class="bi {{ $icon }}"></i>
                                <span class="flex-grow-1">{{ $label }}</span>
                                @if ($badge)
                                    <span class="badge bg-success rounded-pill">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="flex-grow-1 min-w-0 d-flex flex-column min-vh-100">
            <header class="bg-white border-bottom px-3 px-lg-4 py-2 d-flex align-items-center gap-3 sticky-top" style="z-index: 1020;">
                <button class="btn btn-outline-secondary d-lg-none border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar">
                    <i class="bi bi-list fs-4"></i>
                </button>

                <h1 class="h6 mb-0 fw-bold text-truncate" style="max-width: 260px;">@yield('title', 'Dashboard')</h1>

                <form method="GET" action="{{ route('admin.members.index') }}" class="d-none d-md-block ms-3" style="max-width: 260px; width: 100%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input name="search" class="form-control bg-light border-start-0" placeholder="Search members…">
                    </div>
                </form>

                <div class="ms-auto d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-0" type="button" data-bs-toggle="dropdown">
                            <span class="avatar-circle avatar-circle-sm bg-primary">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="d-none d-sm-inline text-dark small">{{ auth()->user()->name }}</span>
                            <i class="bi bi-chevron-down text-muted small"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="{{ route('home') }}" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>View site</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="p-3 p-lg-4 flex-grow-1">
                @if (session('status'))
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2" role="alert">
                        <i class="bi bi-check-circle"></i> {{ session('status') }}
                    </div>
                @endif

                @if (session('calculation_warning'))
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                        <div>{{ session('calculation_warning') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger py-2" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
