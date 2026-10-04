<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Member Portal') — BeyondSure+</title>
    @vite(['resources/sass/member.scss', 'resources/js/member.js'])
</head>
<body>
    @php
        /** @var \App\Models\Member|null $currentMember */
        $currentMember = auth('member')->user();
        $liveWalletBalance = $currentMember ? $currentMember->walletBalance() : 0;
        $pendingOrdersCount = $currentMember ? $currentMember->orders()->where('status', 'pending_payment')->count() : 0;

        $navSections = [
            'Overview & Shopping' => [
                'member.dashboard' => ['Dashboard', 'bi-grid-1x2', null],
                'member.plans.index' => ['Plans & Bundles', 'bi-layers', null],
                'member.orders.index' => ['My Orders', 'bi-bag-check', $pendingOrdersCount ?: null],
            ],
            'Wallet & Earnings' => [
                'member.wallet.index' => ['Wallet & Payouts', 'bi-wallet2', null],
                'member.reports.index' => ['Earnings Report', 'bi-graph-up-arrow', null],
            ],
            'My Network & Genealogy' => [
                'member.downline.index' => ['Binary Tree', 'bi-diagram-3', null],
                'member.team.index' => ['Direct Team', 'bi-people', null],
                'member.levels.index' => ['Level Income', 'bi-bar-chart-steps', null],
            ],
        ];
    @endphp

    <div class="d-flex">
        {{-- Desktop Customer Sidebar --}}
        <aside class="member-sidebar d-none d-lg-flex flex-column p-3 flex-shrink-0 sticky-top" style="height: 100vh;">
            <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                <a href="{{ route('member.dashboard') }}" class="brand">BeyondSure<span class="accent">+</span></a>
                <span class="badge bg-white bg-opacity-10 text-white fw-normal" style="font-size: .68rem;">Member Portal</span>
            </div>

            @if ($currentMember)
                <div class="member-profile-card mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="avatar-circle avatar-circle-sm bg-success border border-white border-opacity-25">
                            {{ strtoupper(substr($currentMember->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-white text-truncate small">{{ $currentMember->name }}</div>
                            <div class="font-monospace text-white-50" style="font-size: .72rem;">{{ $currentMember->member_code }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-10 small">
                        <span class="badge rounded-pill {{ $currentMember->status === 'active' ? 'bg-success text-white' : 'bg-secondary text-white' }} text-capitalize" style="font-size: .65rem;">
                            {{ $currentMember->status }}
                        </span>
                        <span class="badge rounded-pill bg-warning text-dark text-capitalize" style="font-size: .65rem;">
                            <i class="bi bi-award-fill me-1"></i>{{ $currentMember->rank }}
                        </span>
                    </div>
                </div>
            @endif

            <nav class="nav flex-column flex-nowrap gap-1 overflow-y-auto overflow-x-hidden flex-grow-1 pe-1">
                @foreach ($navSections as $sectionLabel => $items)
                    <div class="nav-section-label">{{ $sectionLabel }}</div>
                    @foreach ($items as $route => [$label, $icon, $badge])
                        @php
                            $routePrefix = implode('.', array_slice(explode('.', $route), 0, 2));
                            $active = request()->routeIs($route) || ($routePrefix !== 'member.dashboard' && request()->routeIs($routePrefix . '.*'));
                        @endphp
                        <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}">
                            <i class="bi {{ $icon }}"></i>
                            <span class="flex-grow-1">{{ $label }}</span>
                            @if ($badge)
                                <span class="badge bg-warning text-dark rounded-pill">{{ $badge }}</span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="pt-3 mt-2 border-top border-white border-opacity-10">
                <a href="{{ route('home') }}" target="_blank" class="nav-link py-1 small text-white-50">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Public Website</span>
                </a>
            </div>
        </aside>

        {{-- Mobile Offcanvas Sidebar --}}
        <div class="offcanvas offcanvas-start member-sidebar d-lg-none" tabindex="-1" id="memberSidebar">
            <div class="offcanvas-header border-bottom border-white border-opacity-10">
                <span class="brand">BeyondSure<span class="accent">+</span></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column">
                @if ($currentMember)
                    <div class="member-profile-card mb-3">
                        <div class="fw-semibold text-white small">{{ $currentMember->name }}</div>
                        <div class="font-monospace text-white-50 small">{{ $currentMember->member_code }} · <span class="text-capitalize">{{ $currentMember->rank }}</span></div>
                    </div>
                @endif
                <nav class="nav flex-column gap-1">
                    @foreach ($navSections as $sectionLabel => $items)
                        <div class="nav-section-label">{{ $sectionLabel }}</div>
                        @foreach ($items as $route => [$label, $icon, $badge])
                            @php
                                $routePrefix = implode('.', array_slice(explode('.', $route), 0, 2));
                                $active = request()->routeIs($route) || ($routePrefix !== 'member.dashboard' && request()->routeIs($routePrefix . '.*'));
                            @endphp
                            <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : '' }}">
                                <i class="bi {{ $icon }}"></i>
                                <span class="flex-grow-1">{{ $label }}</span>
                                @if ($badge)
                                    <span class="badge bg-warning text-dark rounded-pill">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </div>
        </div>

        {{-- Main Content Column --}}
        <div class="flex-grow-1 min-w-0 d-flex flex-column min-vh-100">
            <header class="member-header px-3 px-lg-4 py-2 d-flex align-items-center gap-3 sticky-top" style="z-index: 1020;">
                <button class="btn btn-outline-secondary d-lg-none border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberSidebar">
                    <i class="bi bi-list fs-4"></i>
                </button>

                <h1 class="h6 h5-md mb-0 fw-bold text-truncate">@yield('title', 'Dashboard')</h1>

                <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
                    @if ($currentMember)
                        <button type="button"
                            class="btn btn-sm btn-light border d-none d-md-inline-flex align-items-center gap-1 font-monospace small"
                            data-copy-text="{{ $currentMember->member_code }}"
                            title="Click to copy your Sponsor Code">
                            <i class="bi bi-person-badge text-success"></i>
                            <span>{{ $currentMember->member_code }}</span>
                            <i class="bi bi-copy text-secondary ms-1" style="font-size: .7rem;"></i>
                        </button>

                        <a href="{{ route('member.wallet.index') }}" class="btn btn-sm bg-success-subtle text-success fw-semibold rounded-pill px-3">
                            <i class="bi bi-wallet2 me-1"></i>₹{{ number_format($liveWalletBalance, 2) }}
                        </a>

                        <a href="{{ route('member.plans.index') }}" class="btn btn-sm btn-success d-none d-sm-inline-flex align-items-center gap-1">
                            <i class="bi bi-cart-plus"></i>
                            <span>Plans</span>
                        </a>

                        <div class="dropdown">
                            <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-0" type="button" data-bs-toggle="dropdown">
                                <span class="avatar-circle avatar-circle-sm bg-success">{{ strtoupper(substr($currentMember->name, 0, 1)) }}</span>
                                <span class="d-none d-md-inline text-dark small fw-medium">{{ $currentMember->name }}</span>
                                <i class="bi bi-chevron-down text-muted small"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="fw-semibold small">{{ $currentMember->name }}</div>
                                    <div class="font-monospace text-body-tertiary" style="font-size: .75rem;">{{ $currentMember->member_code }}</div>
                                </li>
                                <li><a class="dropdown-item small" href="{{ route('member.dashboard') }}"><i class="bi bi-grid-1x2 me-2 text-secondary"></i>Dashboard</a></li>
                                <li><a class="dropdown-item small" href="{{ route('member.wallet.index') }}"><i class="bi bi-bank me-2 text-secondary"></i>Bank &amp; Wallet KYC</a></li>
                                <li><a class="dropdown-item small" href="{{ route('home') }}" target="_blank"><i class="bi bi-box-arrow-up-right me-2 text-secondary"></i>Public Website</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="dropdown-item small text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endif
                </div>
            </header>

            <main class="p-3 p-lg-4 flex-grow-1">
                @if (session('status'))
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2" role="alert">
                        <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger py-2" role="alert">
                        @foreach ($errors->all() as $error)
                            <div><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>

