@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    @php
        // Links to sections this person can't open become inert instead of leading to a 403.
        $go = fn (string $r) => auth()->user()->canAccessRoute($r) ? route($r) : '#';
        // Commission totals and the finance shortcuts only show for people granted Finance access.
        $finance = auth()->user()->canSeeFinance();
    @endphp
    @php
        $can = fn (string $r) => auth()->user()->canAccessRoute($r);
        $hour = (int) now()->format('G');
        $hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');

        // What is waiting on this person. Only things they are allowed to act on are shown.
        $todo = collect([
            ['n' => $pendingPayments, 'route' => 'admin.payments.index', 'icon' => 'bi-credit-card', 'tone' => 'warning', 'btn' => 'Check payments',
                'text' => $pendingPayments.' '.\Illuminate\Support\Str::plural('payment', $pendingPayments).' '.($pendingPayments === 1 ? 'is' : 'are').' waiting for you to check',
                'hint' => 'Compare each one with your bank statement, then verify or reject it.'],
            ['n' => $unsettledOrders, 'route' => 'admin.cycles.index', 'icon' => 'bi-arrow-repeat', 'tone' => 'primary', 'btn' => 'Open cycles',
                'text' => $unsettledOrders.' paid '.\Illuminate\Support\Str::plural('order', $unsettledOrders).' '.($unsettledOrders === 1 ? 'is' : 'are').' waiting for a cycle',
                'hint' => 'Members only earn income after you approve a cycle.'],
            ['n' => $pendingWithdrawals, 'route' => 'admin.withdrawals.index', 'icon' => 'bi-cash-coin', 'tone' => 'danger', 'btn' => 'Review requests',
                'text' => $pendingWithdrawals.' withdrawal '.\Illuminate\Support\Str::plural('request', $pendingWithdrawals).' '.($pendingWithdrawals === 1 ? 'needs' : 'need').' approval',
                'hint' => 'Approve to make it payable, or reject to give the money back to the member.'],
            ['n' => $withdrawalsToProcess, 'route' => 'admin.dispatch.index', 'icon' => 'bi-truck', 'tone' => 'success', 'btn' => 'Make a payout batch',
                'text' => $withdrawalsToProcess.' approved '.\Illuminate\Support\Str::plural('withdrawal', $withdrawalsToProcess).' '.($withdrawalsToProcess === 1 ? 'is' : 'are').' ready to pay',
                'hint' => 'Group them in a batch, pay from your bank, then enter the reference for each.'],
            ['n' => $unreadMessages, 'route' => 'admin.messages.index', 'icon' => 'bi-envelope', 'tone' => 'info', 'btn' => 'Read messages',
                'text' => $unreadMessages.' new '.\Illuminate\Support\Str::plural('message', $unreadMessages).' from your website',
                'hint' => 'Visitors who used the contact form are waiting for an answer.'],
            ['n' => $noPurchaseMembers, 'route' => 'admin.members.index', 'query' => ['onboarding' => 'no_purchase'], 'icon' => 'bi-person-plus', 'tone' => 'secondary', 'btn' => 'See who',
                'text' => $noPurchaseMembers.' '.\Illuminate\Support\Str::plural('member', $noPurchaseMembers).' '.($noPurchaseMembers === 1 ? 'has' : 'have').' not bought a plan yet',
                'hint' => 'A friendly follow-up often turns a sign-up into a first order.'],
        ])->filter(fn ($t) => $t['n'] > 0 && $can($t['route']))->values();

        // New-install guide.
        $steps = collect([
            ['done' => $setup['products'] > 0, 'title' => 'Add your products', 'text' => 'What you sell, with its cost and GST rate.', 'route' => 'admin.products.create', 'btn' => 'Add a product'],
            ['done' => $setup['plans'] > 0, 'title' => 'Create your plans', 'text' => 'Bundle products into plans and set the price and BV.', 'route' => 'admin.plans.create', 'btn' => 'Create a plan'],
            ['done' => $setup['members'] > 0, 'title' => 'Share your first-member link', 'text' => 'The first person to join becomes the root of the team. Find the link on the Regions page.', 'route' => 'admin.regions.index', 'btn' => 'Open regions'],
            ['done' => $setup['products'] > 0 && $setup['productsWithoutGst'] === 0, 'title' => 'Set a GST rate on every product',
                'text' => $setup['productsWithoutGst'] > 0
                    ? $setup['productsWithoutGst'].' '.\Illuminate\Support\Str::plural('product', $setup['productsWithoutGst']).' '.($setup['productsWithoutGst'] === 1 ? 'has' : 'have').' no GST rate, so '.($setup['productsWithoutGst'] === 1 ? 'it is' : 'they are').' charged no tax.'
                    : 'Every product needs its own rate (0% if exempt).',
                'route' => 'admin.products.index', 'btn' => 'Open products'],
        ]);
        $stepsDone = $steps->where('done', true)->count();
        $showSetup = $stepsDone < $steps->count() && $can('admin.products.create');
    @endphp

    {{-- Greeting --}}
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold">{{ $hello }}, {{ $firstName }}</h2>
            <p class="text-secondary mb-0">{{ now()->format('l, j F Y') }} · here is what is going on in your business.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($can('admin.cycles.index'))<a href="{{ $go('admin.cycles.index') }}" class="btn btn-primary"><i class="bi bi-play-circle me-1"></i>Approve a cycle</a>@endif
            @if ($can('admin.plans.create'))<a href="{{ $go('admin.plans.create') }}" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>New plan</a>@endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- To-do --}}
        <div class="col-lg-{{ $showSetup ? '7' : '12' }}">
            <div class="card border h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>What needs you today</h2>
                        <div class="text-secondary small">Do these in order, top to bottom. Money flows: payment, cycle, withdrawal, payout.</div>
                    </div>
                    @if ($todo->isNotEmpty())<span class="badge rounded-pill bg-danger">{{ $todo->count() }} to do</span>@endif
                </div>
                <div class="card-body">
                    @forelse ($todo as $t)
                        <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded-3 border border-{{ $t['tone'] }}-subtle bg-{{ $t['tone'] }}-subtle bg-opacity-50">
                            <div class="stat-icon bg-{{ $t['tone'] }}-subtle text-{{ $t['tone'] }}-emphasis flex-shrink-0" style="width:44px;height:44px;font-size:1.2rem;"><i class="bi {{ $t['icon'] }}"></i></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold">{{ $t['text'] }}</div>
                                <div class="text-secondary small">{{ $t['hint'] }}</div>
                            </div>
                            <a href="{{ route($t['route'], $t['query'] ?? []) }}" class="btn btn-sm btn-primary flex-shrink-0">{{ $t['btn'] }} <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="bi bi-check2-circle fs-1 text-success"></i>
                            <div class="fw-bold mt-1">You're all caught up</div>
                            <div class="text-secondary small">Nothing is waiting on you right now. New payments, requests and messages will show up here.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Setup guide (new installs only) --}}
        @if ($showSetup)
            <div class="col-lg-5">
                <div class="card border h-100" style="border-color: var(--bs-primary-border-subtle) !important;">
                    <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-rocket-takeoff me-2 text-primary"></i>Set up your business</h2>
                        <div class="text-secondary small">{{ $stepsDone }} of {{ $steps->count() }} steps done</div>
                        <div class="progress mt-2" style="height: 6px;"><div class="progress-bar" style="width: {{ $stepsDone / $steps->count() * 100 }}%"></div></div>
                    </div>
                    <div class="card-body">
                        @foreach ($steps as $i => $step)
                            <div class="d-flex gap-3 mb-3">
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 {{ $step['done'] ? 'bg-success text-white' : 'bg-body-secondary text-secondary' }}" style="width:28px;height:28px;font-size:.8rem;font-weight:700;">
                                    @if ($step['done'])<i class="bi bi-check-lg"></i>@else{{ $i + 1 }}@endif
                                </span>
                                <div class="min-w-0">
                                    <div class="fw-semibold small {{ $step['done'] ? 'text-decoration-line-through text-secondary' : '' }}">{{ $step['title'] }}</div>
                                    @unless ($step['done'])
                                        <div class="text-secondary" style="font-size:.78rem;">{{ $step['text'] }}</div>
                                        @if ($can($step['route']))<a href="{{ route($step['route']) }}" class="small fw-semibold text-decoration-none">{{ $step['btn'] }} &rarr;</a>@endif
                                    @endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Performance & Financial Metrics Strip --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="text-secondary small">Total Platform Members</div>
                        <div class="fs-4 fw-bold lh-1 mt-1">{{ number_format($memberCount) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">{{ number_format($activeMemberCount) }} active (made &ge;1 purchase)</div>
                    </div>
                </div>
            </div>
        </div>

    @if ($finance)
        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="text-secondary small">Commissions Posted Today</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-success">₹{{ number_format($todayIncome, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">Credits posted via cycles</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card border h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="text-secondary small">Commissions This Month</div>
                        <div class="fs-4 fw-bold lh-1 mt-1 text-info-emphasis">₹{{ number_format($monthIncome, 0) }}</div>
                        <div class="text-body-tertiary" style="font-size: .72rem;">All compensation streams combined</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    </div>

    {{-- Tables Row 1: Recent Orders & Payments Verification Queue --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bag-check me-2 text-primary"></i>Recent Plan Purchases</h2>
                        <div class="text-body-tertiary small">Latest orders placed by network partners</div>
                    </div>
                    <a href="{{ $go('admin.orders.index') }}" class="small link-primary text-decoration-none">View all &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th>Plan Bundle</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentOrders as $order)
                                @php
                                    $statusColor = match ($order->status) {
                                        'settled' => 'success',
                                        'paid' => 'info',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark">{{ $order->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $order->member->member_code }}</div>
                                    </td>
                                    <td>{{ $order->plan->name }}</td>
                                    <td class="text-end fw-semibold">₹{{ number_format($order->amount, 0) }}</td>
                                    <td class="text-center pe-3">
                                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} rounded-pill text-capitalize">
                                            {{ str_replace('_', ' ', $order->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-4">No recent orders.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card-2-front me-2 text-warning"></i>Payments Awaiting Verification</h2>
                        <div class="text-body-tertiary small">Bank/UPI receipts requiring manual confirmation</div>
                    </div>
                    <a href="{{ $go('admin.payments.index') }}" class="small link-primary text-decoration-none">Review queue &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th>Order</th>
                                <th>Method</th>
                                <th class="pe-3">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentPayments as $payment)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-medium text-dark">{{ $payment->order->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $payment->order->member->member_code }}</div>
                                    </td>
                                    <td class="font-monospace small text-primary">{{ $payment->order->order_code }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-capitalize">
                                            {{ str_replace('_', ' ', $payment->method) }}
                                        </span>
                                    </td>
                                    <td class="text-body-tertiary small pe-3">{{ $payment->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-tertiary py-4">
                                        <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                                        All payments are currently verified.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Tables Row 2: Recent Withdrawals & Core Navigation Shortcuts --}}
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border h-100">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-bank me-2 text-success"></i>Recent Withdrawal Activity</h2>
                        <div class="text-body-tertiary small">Latest member requests &amp; dispatched batches</div>
                    </div>
                    <a href="{{ $go('admin.withdrawals.index') }}" class="small link-primary text-decoration-none">Manage payouts &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Member</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="pe-3">Date</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($recentWithdrawals as $w)
                                @php
                                    $statusColor = match ($w->status) {
                                        'paid' => 'success',
                                        'approved' => 'info',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-medium text-dark">{{ $w->member->name }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size: .7rem;">{{ $w->member->member_code }}</div>
                                    </td>
                                    <td class="text-end fw-semibold">₹{{ number_format($w->amount, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} rounded-pill text-capitalize">
                                            {{ $w->status }}
                                        </span>
                                    </td>
                                    <td class="text-body-tertiary small pe-3">{{ $w->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-4">No recent withdrawals.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @php
                $tiles = collect([
                    ['route' => 'admin.plans.create', 'icon' => 'bi-plus-circle', 'tone' => 'primary', 'title' => 'New plan', 'hint' => 'Bundle products and set the price'],
                    ['route' => 'admin.products.create', 'icon' => 'bi-box-seam', 'tone' => 'success', 'title' => 'Add a product', 'hint' => 'Cost, price and GST rate'],
                    ['route' => 'admin.members.index', 'icon' => 'bi-people', 'tone' => 'info', 'title' => 'Members', 'hint' => 'Profiles, teams and trees'],
                    ['route' => 'admin.orders.index', 'icon' => 'bi-bag-check', 'tone' => 'warning', 'title' => 'Orders', 'hint' => 'Every plan purchase'],
                    ['route' => 'admin.regions.index', 'icon' => 'bi-geo-alt', 'tone' => 'info', 'title' => 'Regions', 'hint' => 'Markets, trees and links'],
                    ['route' => 'admin.site-content.edit', 'icon' => 'bi-globe2', 'tone' => 'info', 'title' => 'Website', 'hint' => 'Logo, colours and homepage text'],
                    ['route' => 'admin.plans.index', 'icon' => 'bi-layers', 'tone' => 'primary', 'title' => 'Plans & BV Matrix', 'hint' => 'Bundles, cost limits and BV payouts', 'finance' => true],
                    ['route' => 'admin.reports.levels', 'icon' => 'bi-bar-chart-steps', 'tone' => 'success', 'title' => 'Level-Wise Distribution', 'hint' => 'Who earns what, level by level', 'finance' => true],
                    ['route' => 'admin.settings.edit', 'icon' => 'bi-gear', 'tone' => 'warning', 'title' => 'Compensation Rules', 'hint' => 'Percentages, caps and level income', 'finance' => true],
                    ['route' => 'admin.team.index', 'icon' => 'bi-person-badge', 'tone' => 'secondary', 'title' => 'Sub-admins', 'hint' => 'Who can see and do what'],
                ])->filter(fn ($t) => $can($t['route']) && (! ($t['finance'] ?? false) || $finance))->values();
            @endphp
            <div class="card border h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                    <h2 class="h6 mb-0 fw-bold"><i class="bi bi-lightning-charge me-2 text-primary"></i>Quick actions</h2>
                    <div class="text-secondary small">Jump straight to the things you do most.</div>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach ($tiles as $t)
                            <div class="col-sm-6">
                                <a href="{{ route($t['route']) }}" class="d-flex align-items-center gap-3 p-3 border rounded-3 text-decoration-none h-100 hover-lift">
                                    <div class="stat-icon bg-{{ $t['tone'] }}-subtle text-{{ $t['tone'] }}-emphasis flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;"><i class="bi {{ $t['icon'] }}"></i></div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold small text-body">{{ $t['title'] }}</div>
                                        <div class="text-secondary" style="font-size:.74rem;">{{ $t['hint'] }}</div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
