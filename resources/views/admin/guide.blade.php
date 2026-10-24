@extends('layouts.admin')

@section('title', 'Business Guide')

@section('content')
    @php
        $m = fn ($n, $d = 0) => '₹' . number_format($n, $d);
        $go = fn (string $r) => auth()->user()->canAccessRoute($r) ? route($r) : null;
        $bv = (float) $sample['bv'];
        $s = $settings;
        $sections = [
            ['overview', 'bi-compass', 'The big picture'],
            ['flow', 'bi-signpost-split', 'Sign-up to payout'],
            ['money', 'bi-cash-stack', 'Where the money goes'],
            ['regions', 'bi-geo-alt', 'Regions'],
            ['tree', 'bi-diagram-3', 'The binary team'],
            ['matching', 'bi-layers-half', 'Matching & levels'],
            ['safety', 'bi-shield-check', 'Safety limits'],
            ['rank', 'bi-trophy', 'Rank pool'],
            ['cycles', 'bi-arrow-repeat', 'Cycles'],
            ['withdrawals', 'bi-wallet2', 'Wallet & withdrawals'],
            ['orders', 'bi-credit-card', 'Orders & payments'],
            ['catalog', 'bi-box-seam', 'Catalog & GST'],
            ['reports', 'bi-bar-chart-line', 'Reports'],
            ['team', 'bi-person-badge', 'Team & access'],
            ['settings', 'bi-gear', 'Settings cheat sheet'],
            ['routine', 'bi-check2-square', 'Admin routine'],
            ['faq', 'bi-question-circle', 'Common questions'],
        ];
    @endphp

    <style>
        .guide-nav .list-group-item { border: 0; border-radius: .6rem; padding: .5rem .8rem; font-size: .85rem; color: var(--bs-secondary-color); display: flex; align-items: center; gap: .6rem; }
        .guide-nav .list-group-item i { width: 1.1rem; text-align: center; }
        .guide-nav .list-group-item:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
        .guide-nav .list-group-item.active { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); font-weight: 600; }
        .g-card { scroll-margin-top: 80px; }
        .g-card > .card-header { background: transparent; border-bottom: 0; padding: 1.1rem 1.4rem .4rem; display: flex; gap: .9rem; align-items: flex-start; }
        .g-card .chip { width: 2.5rem; height: 2.5rem; border-radius: .7rem; display: grid; place-items: center; flex: none; font-size: 1.2rem; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
        .g-card .chip.good { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
        .g-card .chip.warn { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
        .g-card .chip.info { background: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); }
        .g-card h2 { font-size: 1.15rem; font-weight: 700; margin: 0; }
        .g-card .lede { color: var(--bs-secondary-color); font-size: .9rem; margin: .2rem 0 0; max-width: 72ch; line-height: 1.55; }
        .g-card .card-body { padding: .6rem 1.4rem 1.4rem; }
        .g-card h3 { font-size: .95rem; font-weight: 650; margin: 1.3rem 0 .5rem; }
        .g-card p, .g-card li { font-size: .88rem; line-height: 1.6; }
        .example { border: 1px dashed var(--bs-border-color); border-left: 4px solid var(--bs-primary); border-radius: .75rem; background: var(--bs-tertiary-bg); padding: .9rem 1.1rem; margin: .9rem 0; }
        .example .tag { font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-primary-text-emphasis); display: block; margin-bottom: .3rem; }
        .rule { background: var(--bs-warning-bg-subtle); border-radius: .75rem; padding: .8rem 1rem; font-size: .86rem; margin: .9rem 0; line-height: 1.55; }
        .rule.good { background: var(--bs-success-bg-subtle); }
        .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: .6rem; }
        .stat { border: 1px solid var(--bs-border-color); border-radius: .8rem; padding: .7rem .85rem; background: var(--bs-body-bg); }
        .stat .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); }
        .stat .v { font-size: 1.2rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .stat .n { font-size: .72rem; color: var(--bs-secondary-color); }
        /* flow timeline */
        .flow { position: relative; display: grid; gap: .7rem; }
        .flow-step { display: grid; grid-template-columns: 2.6rem 1fr; gap: .9rem; align-items: start; position: relative; }
        .flow-step .num { width: 2.6rem; height: 2.6rem; border-radius: 50%; display: grid; place-items: center; font-weight: 700; background: var(--bs-primary); color: #fff; z-index: 1; }
        .flow-step.admin .num { background: #6d28d9; }
        .flow-step.system .num { background: #0f766e; }
        .flow-step:not(:last-child)::before { content: ''; position: absolute; left: 1.3rem; top: 2.6rem; bottom: -.7rem; width: 2px; background: var(--bs-border-color); }
        .flow-box { border: 1px solid var(--bs-border-color); border-radius: .85rem; padding: .75rem 1rem; background: var(--bs-body-bg); }
        .who { font-size: .66rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; padding: .12rem .5rem; border-radius: 99px; margin-left: .4rem; vertical-align: middle; }
        .who.member { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
        .who.admin { background: #ede9fe; color: #5b21b6; }
        .who.system { background: #ccfbf1; color: #115e59; }
        /* tree */
        .tree { display: flex; flex-direction: column; align-items: center; gap: .5rem; overflow-x: auto; padding: .5rem 0; }
        .tree .row-l { display: flex; justify-content: center; gap: 1.4rem; flex-wrap: nowrap; }
        .tnode { min-width: 9.5rem; border: 1.5px solid var(--bs-border-color); border-radius: .8rem; padding: .5rem .7rem; text-align: center; background: var(--bs-body-bg); font-size: .78rem; }
        .tnode b { display: block; font-size: .85rem; }
        .tnode.root { border-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); }
        .tnode.l { border-color: var(--bs-info); } .tnode.r { border-color: var(--bs-success); }
        .tlink { color: var(--bs-secondary-color); font-size: .75rem; }
        /* level bars */
        .lvl { display: grid; grid-template-columns: 4.3rem 1fr 7.5rem; gap: .7rem; align-items: center; font-size: .84rem; margin-bottom: .4rem; }
        .lvl .bar { height: 14px; background: var(--bs-tertiary-bg); border-radius: 99px; overflow: hidden; }
        .lvl .bar > span { display: block; height: 100%; background: #6d28d9; border-radius: 99px; }
        .pill { display: inline-block; font-size: .72rem; font-weight: 600; padding: .2rem .65rem; border-radius: 99px; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); }
        .pill.on { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); border-color: var(--bs-success-border-subtle); }
        .arrow { color: var(--bs-secondary-color); }
        .goto { font-size: .78rem; }
        @media (max-width: 991.98px) { .guide-nav-wrap { display: none; } }
    </style>

    <div class="row g-4">
        <div class="col-lg-3 guide-nav-wrap">
            <div class="list-group guide-nav sticky-top" id="guideNav" style="top: 80px; max-height: calc(100vh - 100px); overflow-y: auto;">
                @foreach ($sections as [$id, $icon, $label])
                    <a href="#g-{{ $id }}" class="list-group-item list-group-item-action {{ $loop->first ? 'active' : '' }}" data-section="g-{{ $id }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="col-lg-9 d-grid gap-4">

            {{-- ======================================================= 1. OVERVIEW ======================================================= --}}
            <section class="card border g-card" id="g-overview">
                <div class="card-header">
                    <div class="chip"><i class="bi bi-compass"></i></div>
                    <div>
                        <h2>The big picture</h2>
                        <p class="lede">BeyondSure+ sells wellness plan bundles. Members who buy and introduce others earn income from a fixed pool of money called <strong>BV</strong>. You control every rate; the system does the maths and keeps a ledger of who earned what.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="stat-row mb-3">
                        <div class="stat"><div class="k">GST</div><div class="v">Per product</div><div class="n">added on top, never yours</div></div>
                        <div class="stat"><div class="k">Self income</div><div class="v">{{ $s['self'] }}%</div><div class="n">of BV, to the buyer</div></div>
                        <div class="stat"><div class="k">Sponsor income</div><div class="v">{{ $s['sponsor'] }}%</div><div class="n">of BV, to the introducer</div></div>
                        <div class="stat"><div class="k">Matching levels</div><div class="v">{{ $s['depth'] }} · {{ $s['levelsTotal'] }}%</div><div class="n">levels · total of BV</div></div>
                        <div class="stat"><div class="k">Rank pool</div><div class="v">{{ $s['rankPool'] }}%</div><div class="n">of the cycle's BV</div></div>
                        <div class="stat"><div class="k">Circuit breaker</div><div class="v">{{ $s['breaker'] }}%</div><div class="n">per layer, per cycle</div></div>
                    </div>
                    <p class="mb-2"><strong>The five ideas to hold on to:</strong></p>
                    <ol class="mb-0 ps-3 d-grid gap-1">
                        <li><strong>Price → BV.</strong> Every plan has a <em>price</em> (what the member pays, plus GST) and a <em>BV</em> (the slice of that sale reserved for paying members). BV is set by you per plan and is independent of price.</li>
                        <li><strong>Income is paid in a cycle.</strong> Nothing is paid the moment someone buys. When you approve a <em>cycle</em>, the system calculates everyone's income from all paid orders and posts it to their wallets.</li>
                        <li><strong>Four kinds of income:</strong> Self (buyer), Sponsor (introducer), Matching (balanced left/right teams, paid up to {{ $s['depth'] }} levels) and Rank pool (shared by achievers).</li>
                        <li><strong>Safety limits</strong> — circuit breaker and weekly/monthly caps — stop payouts running away from what was actually collected.</li>
                        <li><strong>Withdrawals are manual by design.</strong> Members request, you approve, you pay from your bank and record the reference. Nothing leaves without you.</li>
                    </ol>
                </div>
            </section>

            {{-- ======================================================= 2. FLOW ======================================================= --}}
            <section class="card border g-card" id="g-flow">
                <div class="card-header">
                    <div class="chip info"><i class="bi bi-signpost-split"></i></div>
                    <div>
                        <h2>From sign-up to payout</h2>
                        <p class="lede">The complete journey of one purchase, in order. <span class="who member">Member</span> steps happen in the member portal, <span class="who admin">Admin</span> steps are yours, <span class="who system">System</span> steps run automatically.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="flow">
                        @php
                            $steps = [
                                ['member', 'Member joins', 'Registers with a mobile number and OTP. A sponsor invite link carries the sponsor\'s code and the <strong>leg</strong> (left or right) they were invited into.' . ($s['requireSponsor'] ? ' Right now a sponsor code is <strong>required</strong>.' : ' A sponsor code is optional right now.'), 'admin.members.index', 'Members'],
                                ['member', 'Member buys a plan', 'Picks a plan bundle. An order is created as <em>pending payment</em> for <strong>price + GST</strong>. GST is calculated once, at this moment, and saved on the order.', 'admin.orders.index', 'Orders'],
                                ['system', 'Payment is confirmed', 'Pay online with Razorpay: confirmed automatically. Pay by UPI / bank transfer: the member submits the reference and <span class="who admin">Admin</span> verifies it. Either way the order becomes <em>paid</em> and the member becomes <em>active</em>.', 'admin.payments.index', 'Payments'],
                                ['admin', 'You approve a cycle', 'All paid, not-yet-settled orders are settled together. This is the moment income is calculated and posted. <strong>It cannot be undone</strong>, so verify payments first.', 'admin.cycles.index', 'Cycles'],
                                ['system', 'Income lands in wallets', 'Self, Sponsor, Matching and Rank income are written to each member\'s ledger. Orders become <em>settled</em>.', 'admin.members.index', 'Members'],
                                ['member', 'Member requests a withdrawal', 'Needs saved bank details and an amount up to the <em>available</em> balance. The amount is reserved straight away so it can\'t be requested twice.', 'admin.withdrawals.index', 'Withdrawals'],
                                ['admin', 'You approve the request', 'Check it and approve (or reject, which releases the reserved amount).', 'admin.withdrawals.index', 'Withdrawals'],
                                ['admin', 'You group approved requests into a dispatch batch', 'Create a batch, add the approved withdrawals, then pay them from your bank.', 'admin.dispatch.index', 'Dispatch'],
                                ['admin', 'You release the batch', 'Enter the bank reference (UTR) for each one and release. Each withdrawal becomes <em>paid</em> and the money is deducted from the wallet.', 'admin.dispatch.index', 'Dispatch'],
                            ];
                        @endphp
                        @foreach ($steps as $i => [$who, $title, $text, $route, $label])
                            <div class="flow-step {{ $who }}">
                                <div class="num">{{ $i + 1 }}</div>
                                <div class="flow-box">
                                    <div class="fw-semibold">{{ $title }} <span class="who {{ $who }}">{{ $who }}</span></div>
                                    <div class="text-secondary" style="font-size: .85rem;">{!! $text !!}</div>
                                    @if ($go($route))
                                        <a href="{{ $go($route) }}" class="goto text-decoration-none">Open {{ $label }} <i class="bi bi-arrow-right"></i></a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="rule mt-3"><strong>Why nothing is automatic after payment:</strong> you stay in control of when money is committed. A purchase makes a member <em>active</em> immediately, but <em>income</em> only appears when you approve a cycle.</div>
                </div>
            </section>

            {{-- ======================================================= 3. MONEY ======================================================= --}}
            <section class="card border g-card" id="g-money">
                <div class="card-header">
                    <div class="chip good"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <h2>Where the money goes</h2>
                        <p class="lede">Every sale is split into four buckets. Pick a plan below to see the real figures.</p>
                    </div>
                </div>
                <div class="card-body">
                    @if ($planData->isNotEmpty())
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <label for="planPick" class="fw-semibold small mb-0">Plan:</label>
                            <select id="planPick" class="form-select form-select-sm" style="max-width: 240px;">
                                @foreach ($planData as $p)
                                    <option value="{{ $p['id'] }}" @selected($p['id'] === $sample['id'])>{{ $p['name'] }} — {{ $m($p['price']) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="stat-row">
                        <div class="stat"><div class="k">Member pays</div><div class="v" id="pTotal">{{ $m($sample['total']) }}</div><div class="n">price + GST</div></div>
                        <div class="stat"><div class="k">GST → government</div><div class="v" id="pGst">{{ $m($sample['gst']) }}</div><div class="n">not your money</div></div>
                        <div class="stat"><div class="k">Product cost</div><div class="v" id="pCost">{{ $m($sample['cost']) }}</div><div class="n">what the goods cost you</div></div>
                        <div class="stat"><div class="k">BV pool</div><div class="v" id="pBv">{{ $m($sample['bv']) }}</div><div class="n">reserved for members</div></div>
                        <div class="stat"><div class="k">Left for the company</div><div class="v" id="pRest">{{ $m($sample['rest']) }}</div><div class="n">before running costs</div></div>
                    </div>
                    <div class="example">
                        <span class="tag">Worked example — {{ $sample['name'] }}</span>
                        Price <strong>{{ $m($sample['price']) }}</strong> + GST <strong>{{ $m($sample['gst']) }}</strong> = the member pays <strong>{{ $m($sample['total']) }}</strong>.
                        Of the {{ $m($sample['price']) }} you actually earn, {{ $m($sample['cost']) }} covers the products and {{ $m($sample['bv']) }} is set aside as BV.
                        What remains, <strong>{{ $m($sample['rest']) }}</strong>, is yours <em>if members are paid no more than the BV</em>. Gateway fees and your running costs come out of this.
                    </div>
                    <h3>What BV pays out (per sale)</h3>
                    <div class="stat-row">
                        <div class="stat"><div class="k">Self ({{ $s['self'] }}%)</div><div class="v" id="pSelf">{{ $m($sample['self'], 2) }}</div></div>
                        <div class="stat"><div class="k">Sponsor ({{ $s['sponsor'] }}%)</div><div class="v" id="pSponsor">{{ $m($sample['sponsor'], 2) }}</div></div>
                        <div class="stat"><div class="k">Levels, if fully matched ({{ $s['levelsTotal'] }}%)</div><div class="v" id="pLevels">{{ $m($sample['levels'], 2) }}</div></div>
                        <div class="stat"><div class="k">Rank pool ({{ $s['rankPool'] }}%)</div><div class="v">{{ $m($bv * $s['rankPool'] / 100, 2) }}</div><div class="n">shared, not per sale</div></div>
                    </div>
                    <div class="rule">
                        <strong>Targets vs. reality.</strong> Settings holds a target split of taxable value — <strong>{{ $s['costPct'] }}% cost · {{ $s['poolPct'] }}% pool · {{ $s['companyPct'] }}% company</strong>. It's a guide that warns you when plan numbers drift; the actual money moves according to each plan's real cost and BV, which you set per plan.
                    </div>
                    <div class="rule good"><strong>GST is never part of your income or the pool.</strong> It's collected for the government. The Business overview report shows it separately so your profit figure is never inflated by tax.</div>
                </div>
            </section>

            {{-- ======================================================= REGIONS ======================================================= --}}
            <section class="card border g-card" id="g-regions">
                <div class="card-header">
                    <div class="chip info"><i class="bi bi-geo-alt"></i></div>
                    <div>
                        <h2>Regions</h2>
                        <p class="lede">A region is a separate market with <strong>its own tree</strong> and <strong>its own plans</strong>. Use one for each state, city or country you operate in.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive rounded-3 border mb-3">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light"><tr><th class="ps-3">What</th><th>Rule</th></tr></thead>
                            <tbody>
                                <tr><td class="ps-3 fw-semibold">Tree</td><td>One tree per region. A member, their sponsor and everyone placed under them are always in the same region. Matching and levels never cross into another region.</td></tr>
                                <tr><td class="ps-3 fw-semibold">Root</td><td>The first member to join a region is its root. Anyone who registers without a sponsor joins under that root.</td></tr>
                                <tr><td class="ps-3 fw-semibold">Plans</td><td>Each plan belongs to one region. Members only see and can only buy their own region's plans. A plan that already has orders cannot move region.</td></tr>
                                <tr><td class="ps-3 fw-semibold">Mobile number</td><td>One number, one region, ever. If it is already registered elsewhere the sign-up stops with a message naming that region.</td></tr>
                                <tr><td class="ps-3 fw-semibold">Sub-admin access</td><td>On the sub-admin's page you can limit them to chosen regions. They then see only those regions' members, orders, payments, plans and reports, and cannot approve cycles, release payout batches or create regions (those touch every region).</td></tr>
                                <tr><td class="ps-3 fw-semibold">Shared</td><td>Products, GST, income rates, cycles, withdrawals and reports work the same in every region.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <h3>Which link does what</h3>
                    <ul class="ps-3">
                        <li><strong>Member invite link</strong> (<code>/register?sponsor=CODE&amp;side=left</code>): the new person joins the sponsor's region and the chosen leg. The region is read from the sponsor, so it can never mismatch.</li>
                        <li><strong>Region link</strong> (<code>/register?region=CODE</code>, on each region card): joins directly under that region's root. Use it for ads and the website.</li>
                        <li><strong>Buy link</strong> (<code>/buy/PLAN</code>): a new visitor joins the plan's region. A sponsor from a different region is refused.</li>
                        <li><strong>No link at all:</strong> if only one region is open it is used; if several are open the visitor must choose one.</li>
                    </ul>
                    <div class="example">
                        <span class="tag">Worked example</span>
                        Gujarat and Maharashtra are both open. Ravi (Gujarat) shares his left link; Meena opens it and joins Gujarat under Ravi. Later Meena's number is tried on a Maharashtra sign-up: it is refused with "already registered in the Gujarat region". Ravi's matching income is calculated only from Gujarat members.
                    </div>
                    @if ($go('admin.regions.index'))<a href="{{ $go('admin.regions.index') }}" class="goto text-decoration-none">Open Regions <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </section>

            {{-- ======================================================= 4. TREE ======================================================= --}}
            <section class="card border g-card" id="g-tree">
                <div class="card-header">
                    <div class="chip"><i class="bi bi-diagram-3"></i></div>
                    <div>
                        <h2>The binary team</h2>
                        <p class="lede">Every member has a <strong>left</strong> and a <strong>right</strong> side. Two different relationships are tracked, and they are not the same thing.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="stat h-100"><div class="k">Sponsor (who introduced me)</div><div class="n" style="font-size:.85rem">Decides <strong>Sponsor income</strong> ({{ $s['sponsor'] }}% of BV on each purchase of someone they personally introduced). Also called a "direct".</div></div></div>
                        <div class="col-md-6"><div class="stat h-100"><div class="k">Placement (where I sit in the tree)</div><div class="n" style="font-size:.85rem">Decides the <strong>left/right legs</strong> and who earns <strong>Matching</strong>. The sponsor chooses the leg with their invite link.</div></div></div>
                    </div>
                    <h3>How a new member is placed</h3>
                    <ul class="ps-3 mb-0">
                        <li>The sponsor shares a <strong>Left</strong> or <strong>Right</strong> invite link (members find them above their tree).</li>
                        <li>If the slot directly under the sponsor on that side is empty, the new member takes it.</li>
                        <li>If it's taken, they go to the <strong>next open spot inside that same leg</strong> — never into the other leg.</li>
                        <li>A link with no side fills the left slot first.</li>
                    </ul>
                    <div class="example">
                        <span class="tag">Worked example — a small team (each buys {{ $sample['name'] }}, BV {{ $m($bv) }})</span>
                        <div class="tree">
                            <div class="tnode root"><b>Tejas</b>sponsor of XYZ &amp; ABC</div>
                            <div class="tlink">↙ left leg &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; right leg ↘</div>
                            <div class="row-l">
                                <div class="tnode l"><b>XYZ</b>BV {{ $m($bv) }}<br>sponsor: Tejas</div>
                                <div class="tnode r"><b>ABC</b>BV {{ $m($bv) }}<br>sponsor: Tejas</div>
                            </div>
                            <div class="tlink" style="margin-left: 11rem;">↓ placed under ABC</div>
                            <div class="row-l" style="margin-left: 11rem;">
                                <div class="tnode r"><b>down ABC</b>BV {{ $m($bv) }}<br>sponsor: <u>ABC</u></div>
                            </div>
                        </div>
                        <ul class="mb-0 ps-3 mt-2">
                            <li><strong>Tejas's left leg</strong> = {{ $m($bv) }} (XYZ). <strong>Right leg</strong> = {{ $m($bv * 2) }} (ABC + down ABC).</li>
                            <li><strong>Sponsor income:</strong> Tejas introduced XYZ and ABC, so he earns {{ $s['sponsor'] }}% × {{ $m($bv) }} = <strong>{{ $m($bv * $s['sponsor'] / 100, 2) }}</strong> on each — {{ $m($bv * $s['sponsor'] / 100 * 2, 2) }} total. down ABC was introduced by ABC, so that {{ $m($bv * $s['sponsor'] / 100, 2) }} goes to ABC, not Tejas.</li>
                            <li>down ABC's purchase <em>still counts in Tejas's right leg</em> — placement, not sponsorship, builds legs.</li>
                        </ul>
                    </div>
                    @if ($go('admin.members.index'))<a href="{{ $go('admin.members.index') }}" class="goto text-decoration-none">See any member's tree: Members → Network <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </section>

            {{-- ======================================================= 5. MATCHING ======================================================= --}}
            <section class="card border g-card" id="g-matching">
                <div class="card-header">
                    <div class="chip warn"><i class="bi bi-layers-half"></i></div>
                    <div>
                        <h2>Matching &amp; levels</h2>
                        <p class="lede">Matching rewards balanced growth: you earn when your left and right legs both have volume. Volume waits on the heavier side until the other side catches up.</p>
                    </div>
                </div>
                <div class="card-body">
                    <h3>Step 1 — matched volume</h3>
                    <p>Each leg's total BV is tracked for ever. <strong>Matched BV = the smaller leg</strong>. Only <em>newly</em> matched volume is paid, and volume is never matched twice.</p>
                    <div class="example">
                        <span class="tag">Worked example — continuing the team above</span>
                        Tejas: left {{ $m($bv) }}, right {{ $m($bv * 2) }}. Matched = {{ $m($bv) }} (the smaller side).
                        The extra {{ $m($bv) }} on the right is <strong>carry-forward</strong> — it never expires and matches the moment {{ $m($bv) }} of new volume lands on his left.
                    </div>
                    <h3>Step 2 — the level cascade</h3>
                    <p>When a member's legs match, that member is paid as <strong>Level 1</strong>, their placement parent as <strong>Level 2</strong>, and so on up to <strong>Level {{ $s['depth'] }}</strong>. Each level has its own percentage of the matched BV:</p>
                    @php $maxPct = max(1, max(array_values($levelPcts) ?: [1])); @endphp
                    @foreach ($cascade as $row)
                        <div class="lvl">
                            <div class="fw-semibold">Level {{ $row['level'] }}</div>
                            <div class="bar"><span style="width: {{ $row['pct'] / $maxPct * 100 }}%"></span></div>
                            <div class="text-end"><strong>{{ $row['pct'] }}%</strong> · {{ $m($row['gross'], 2) }}</div>
                        </div>
                    @endforeach
                    <div class="small text-secondary mb-2">Amounts shown for {{ $m($bv) }} of matched BV ({{ $sample['name'] }}). Total if every level exists: <strong>{{ $s['levelsTotal'] }}%</strong> = {{ $m($gross, 2) }}.</div>
                    <div class="rule good">
                        <strong>"What if someone joins at the 8th level — or the 20th?"</strong> Nothing breaks and nobody is excluded. The cascade always follows <em>the member whose legs matched</em>, counting upward from them — never downward from the admin. A member at any depth is always Level 1 of their own matches; the {{ $s['depth'] }}-level limit only means a single match pays the nearest {{ $s['depth'] }} people above it. Anyone further up simply isn't paid <em>from that one match</em>, and that money stays with the company.
                    </div>
                    <h3>Direct income vs. matching income</h3>
                    <ul class="ps-3 mb-0">
                        <li><strong>Direct (Sponsor) income</strong> — paid on every purchase made by someone you personally introduced. Needs no matching.</li>
                        <li><strong>Matching income</strong> — paid only when both of your legs have volume.</li>
                        <li><strong>Self income</strong> — what a member earns on their own purchases ({{ $s['self'] }}% of BV).</li>
                    </ul>
                    @if ($go('admin.reports.levels'))<div class="mt-2"><a href="{{ $go('admin.reports.levels') }}" class="goto text-decoration-none">See it for real: Reports → Level-wise income <i class="bi bi-arrow-right"></i></a></div>@endif
                </div>
            </section>

            {{-- ======================================================= 6. SAFETY ======================================================= --}}
            <section class="card border g-card" id="g-safety">
                <div class="card-header">
                    <div class="chip warn"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <h2>Safety limits</h2>
                        <p class="lede">Three protections keep payouts in line with what was collected. They only ever <em>reduce</em> a payout, never increase one.</p>
                    </div>
                </div>
                <div class="card-body">
                    <h3>1. Circuit breaker — {{ $s['breaker'] }}%</h3>
                    <p>Matching can use old carry-forward volume, so one purchase can match a lot at once. The breaker caps what one layer's cascade can pay in a cycle at <strong>{{ $s['breaker'] }}% of that layer's <em>new</em> BV</strong> (this cycle's fresh sales only). If the cascade would pay more, <strong>every person in it is reduced by the same proportion</strong>.</p>
                    <div class="example">
                        <span class="tag">Worked example — {{ $sample['name'] }}, {{ $m($bv) }} of new BV matching old volume</span>
                        Full cascade would pay <strong>{{ $m($gross, 2) }}</strong> ({{ $s['levelsTotal'] }}% of {{ $m($bv) }}). The cap is {{ $s['breaker'] }}% × {{ $m($bv) }} = <strong>{{ $m($cap, 2) }}</strong>.
                        @if ($breakerBinds)
                            The cascade is over the cap, so everyone is scaled to <strong>{{ round($cap / max($gross, 0.01) * 100, 1) }}%</strong> of their share and the total paid is <strong>{{ $m($cascadePaid, 2) }}</strong>. The {{ $m($gross - $cascadePaid, 2) }} difference stays with the company.
                        @else
                            Your current rates are <strong>under</strong> the cap, so everyone is paid in full ({{ $m($cascadePaid, 2) }}). The breaker would step in if you raised the level percentages or lowered the breaker.
                        @endif
                    </div>
                    <div class="rule"><strong>Know this:</strong> the breaker limits each single layer's cascade, not the company-wide total. One purchase can match several people at once and each runs its own capped cascade, so overall matching can exceed {{ $s['breaker'] }}% of BV in deep, narrow teams. Watch the <em>Effective matching rate</em> on the Business overview.</div>
                    <h3>2. Weekly and monthly caps per member</h3>
                    <p>No single member can earn more than <strong>{{ $m($s['weeklyCap']) }} matching income per week</strong> or <strong>{{ $m($s['monthlyCap']) }} per month</strong> (0 switches a cap off). Both apply at once; members see their usage on their Wallet page.</p>
                    <h3>3. The rank pool is paid last, from what's left</h3>
                    <p>After Self, Sponsor and Matching are paid, the rank pool can only use what remains of that cycle's BV — so it can never push total payouts past the BV.</p>
                </div>
            </section>

            {{-- ======================================================= 7. RANK ======================================================= --}}
            <section class="card border g-card" id="g-rank">
                <div class="card-header">
                    <div class="chip warn"><i class="bi bi-trophy"></i></div>
                    <div>
                        <h2>Rank pool</h2>
                        <p class="lede">A bonus shared among members who have built a strong, balanced team <em>and</em> are still buying.</p>
                    </div>
                </div>
                <div class="card-body">
                    <h3>How a rank is decided</h3>
                    <p>At every cycle approval, each active member who <strong>bought this month</strong> is given the <strong>highest rank where both are true</strong>: their lifetime matched BV reaches the rank's threshold, <em>and</em> they have enough active directs (people they personally introduced who also bought this month).</p>
                    <div class="table-responsive rounded-3 border">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light"><tr><th class="ps-3">Rank</th><th class="text-end">Matched BV needed</th><th class="text-end">Active directs</th><th class="text-end pe-3">Pool points</th></tr></thead>
                            <tbody>
                                @forelse ($ranks as $rk)
                                    <tr><td class="ps-3 fw-semibold">{{ $rk->name }}</td><td class="text-end">{{ $m($rk->matched_bv_threshold) }}</td><td class="text-end">{{ $rk->min_active_directs }}</td><td class="text-end pe-3">{{ $rk->share }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-tertiary py-3">No ranks set up.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <h3>How the pool is shared</h3>
                    <p>The pool is <strong>{{ $s['rankPool'] }}% of the cycle's total BV</strong> (limited to what's left after other income). It's divided by <em>points</em>: each qualifier gets <em>pool ÷ everyone's total points × their own points</em>.</p>
                    @php
                        $r1 = $ranks->first(); $r2 = $ranks->skip(1)->first();
                        $poolEx = 100000 * $s['rankPool'] / 100;
                        $p1 = $r1?->share ?? 1; $p2 = $r2?->share ?? 2;
                        $perPoint = $poolEx / ($p1 + $p2);
                    @endphp
                    <div class="example">
                        <span class="tag">Worked example</span>
                        A cycle has {{ $m(100000) }} of BV → rank pool {{ $m($poolEx) }}. Two members qualify: one <strong>{{ $r1?->name ?? 'Silver' }}</strong> ({{ $p1 }} point{{ $p1 > 1 ? 's' : '' }}) and one <strong>{{ $r2?->name ?? 'Gold' }}</strong> ({{ $p2 }} points). Total {{ $p1 + $p2 }} points → {{ $m($perPoint, 2) }} per point.
                        {{ $r1?->name ?? 'Silver' }} receives <strong>{{ $m($perPoint * $p1, 2) }}</strong>; {{ $r2?->name ?? 'Gold' }} receives <strong>{{ $m($perPoint * $p2, 2) }}</strong>.
                    </div>
                    <p class="small text-secondary mb-0">Edit the ladder in Settings → Ranks (rename, add, remove, change thresholds and points). Changes apply from the next cycle.</p>
                </div>
            </section>

            {{-- ======================================================= 8. CYCLES ======================================================= --}}
            <section class="card border g-card" id="g-cycles">
                <div class="card-header">
                    <div class="chip"><i class="bi bi-arrow-repeat"></i></div>
                    <div>
                        <h2>Cycles</h2>
                        <p class="lede">A cycle is the moment you tell the system "work out everyone's income". It's the heart of the business and the only thing that creates income.</p>
                    </div>
                </div>
                <div class="card-body">
                    <h3>Using it</h3>
                    <ol class="ps-3">
                        <li><strong>Check Payments first</strong> — only <em>paid</em> orders are included; unverified ones wait for the next cycle.</li>
                        <li>In <strong>Cycles</strong>, create a cycle (the dates are just a label for your records) — it starts as a <span class="pill">draft</span>.</li>
                        <li>Click <strong>Approve &amp; run compensation</strong>. It becomes <span class="pill on">approved</span> and income is posted.</li>
                    </ol>
                    <h3>What happens when you approve (in this order)</h3>
                    <div class="flow">
                        @foreach ([
                            ['Settle the orders', 'Every paid, not-yet-settled order is gathered and marked settled. Their total BV is this cycle\'s pool.'],
                            ['Self &amp; Sponsor', 'For each order: Self income ' . $s['self'] . '% of BV to the buyer, Sponsor income ' . $s['sponsor'] . '% to their introducer.'],
                            ['Build the legs', 'Each order\'s BV is added to the correct (left or right) leg of <em>every</em> member above the buyer in the placement tree.'],
                            ['Matching', 'For every member whose legs now hold unmatched volume on both sides: pay the level cascade on the newly matched BV, scaled by the circuit breaker and held to the weekly/monthly caps.'],
                            ['Rank pool', 'Active qualifying members are ranked and share the pool from what is left of the BV.'],
                            ['Lock', 'The cycle is marked approved with its totals. It cannot be edited or rerun.'],
                        ] as $i => [$t, $d])
                            <div class="flow-step system">
                                <div class="num" style="width:2.2rem;height:2.2rem;font-size:.85rem;">{{ $i + 1 }}</div>
                                <div class="flow-box py-2"><div class="fw-semibold">{!! $t !!}</div><div class="text-secondary" style="font-size:.84rem">{!! $d !!}</div></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="example">
                        <span class="tag">Worked example</span>
                        Three members each buy {{ $sample['name'] }} (BV {{ $m($bv) }}) and all are paid. You approve a cycle: pool = {{ $m($bv * 3) }}. Self: 3 × {{ $m($bv * $s['self'] / 100, 2) }}. Sponsor: 3 × {{ $m($bv * $s['sponsor'] / 100, 2) }} to their introducers. Then matching and rank on top, within the safety limits.
                    </div>
                    <div class="rule"><strong>Cycles are final.</strong> There is no "undo". If a payment was wrongly verified, fix it <em>before</em> approving. Settings changes (rates, levels, GST) apply to the <em>next</em> cycle; past cycles are never recalculated.</div>
                    @if ($go('admin.cycles.index'))<a href="{{ $go('admin.cycles.index') }}" class="goto text-decoration-none">Open Cycles <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </section>

            {{-- ======================================================= 9. WITHDRAWALS ======================================================= --}}
            <section class="card border g-card" id="g-withdrawals">
                <div class="card-header">
                    <div class="chip good"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <h2>Wallet &amp; withdrawals</h2>
                        <p class="lede">Each member has a wallet that is simply the total of their ledger. Income adds to it; a paid withdrawal subtracts from it.</p>
                    </div>
                </div>
                <div class="card-body">
                    <h3>The life of a withdrawal</h3>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="pill">1 · Pending</span><span class="arrow">→</span>
                        <span class="pill">2 · Approved</span><span class="arrow">→</span>
                        <span class="pill">3 · In a draft batch</span><span class="arrow">→</span>
                        <span class="pill on">4 · Paid</span>
                        <span class="ms-2 small text-secondary">or <span class="pill">Rejected</span> at step 1–2 (amount released)</span>
                    </div>
                    <ul class="ps-3">
                        <li><strong>Request:</strong> the member needs bank details saved and asks for an amount up to their <em>available</em> balance. It's reserved immediately.</li>
                        <li><strong>Available balance</strong> = wallet − everything already requested but not yet paid. This is what stops the same money being requested twice.</li>
                        <li><strong>Approve / reject:</strong> in Withdrawals. Rejecting frees the reserved amount at once.</li>
                        <li><strong>Dispatch batch:</strong> create a batch for a payout date and add approved requests. The member's bank details are <em>saved on the request</em>, so a later profile edit can't redirect money.</li>
                        <li><strong>Release:</strong> enter the bank reference (UTR) for every item, then release. Only now does each withdrawal become <em>paid</em> and the wallet is debited. A batch can't be released with a missing reference.</li>
                    </ul>
                    <div class="example">
                        <span class="tag">Worked example</span>
                        Wallet {{ $m(5000) }}. Member requests {{ $m(3000) }} → available becomes {{ $m(2000) }} (wallet still shows {{ $m(5000) }}). You approve, add it to a batch, pay {{ $m(3000) }} from your bank and release with the UTR → status <em>paid</em>, wallet becomes <strong>{{ $m(2000) }}</strong>. If you had rejected it instead, available would return to {{ $m(5000) }}.
                    </div>
                    <div class="rule good"><strong>There is no automatic bank transfer.</strong> The system records and controls the process; you move the money from your bank and enter the reference. Release is the single moment you commit.</div>
                    @if ($go('admin.withdrawals.index'))<a href="{{ $go('admin.withdrawals.index') }}" class="goto text-decoration-none me-3">Open Withdrawals <i class="bi bi-arrow-right"></i></a>@endif
                    @if ($go('admin.dispatch.index'))<a href="{{ $go('admin.dispatch.index') }}" class="goto text-decoration-none">Open Dispatch <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </section>

            {{-- ======================================================= 10. ORDERS ======================================================= --}}
            <section class="card border g-card" id="g-orders">
                <div class="card-header">
                    <div class="chip info"><i class="bi bi-credit-card"></i></div>
                    <div>
                        <h2>Orders &amp; payments</h2>
                        <p class="lede">An order moves through four states. Knowing them tells you exactly what to do next.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive rounded-3 border mb-3">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light"><tr><th class="ps-3">Status</th><th>Meaning</th><th>Your action</th></tr></thead>
                            <tbody>
                                <tr><td class="ps-3"><span class="pill">pending payment</span></td><td>Member placed the order but hasn't paid (or the reference isn't verified yet).</td><td>Verify the payment, or cancel the order if abandoned.</td></tr>
                                <tr><td class="ps-3"><span class="pill on">paid</span></td><td>Payment confirmed. Member is <em>active</em>. Income not yet calculated.</td><td>Approve a cycle to settle it.</td></tr>
                                <tr><td class="ps-3"><span class="pill on">settled</span></td><td>A cycle has paid income for this order. Final.</td><td>None — it can't be cancelled.</td></tr>
                                <tr><td class="ps-3"><span class="pill">cancelled</span></td><td>Cancelled before payment. Never earns income.</td><td>None.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <ul class="ps-3 mb-0">
                        <li><strong>Razorpay:</strong> the payment signature is checked and the order is marked paid automatically — no action from you.</li>
                        <li><strong>UPI / bank transfer / cash:</strong> the member enters the reference; you compare it to your bank statement in <em>Payments</em> and verify or reject.</li>
                        <li><strong>New vs. repurchase:</strong> an order is a <em>repurchase</em> if the member already had a paid order. It earns exactly the same income; it's tracked so you can report on repeat buying.</li>
                    </ul>
                </div>
            </section>

            {{-- ======================================================= 11. CATALOG ======================================================= --}}
            <section class="card border g-card" id="g-catalog">
                <div class="card-header">
                    <div class="chip"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <h2>Catalog &amp; GST</h2>
                        <p class="lede">Categories group products; products go into plans; members buy plans.</p>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="ps-3">
                        <li><strong>Categories</strong> — tidy grouping. A "Free" category holds complimentary benefits (no cost, no price).</li>
                        <li><strong>Products</strong> — each has a cost price, a retail value and a <strong>GST rate of its own</strong>, required on every product (0% if exempt). There is no company-wide rate.</li>
                        <li><strong>Plans</strong> — a bundle of products with a <strong>price (excl. GST)</strong> and a <strong>BV</strong>. Price and BV are independent: you choose how much of each sale is reserved for members.</li>
                    </ul>
                    <h3>How GST is worked out</h3>
                    <p>GST is added <em>on top</em> of the plan price. Because one plan can hold products taxed at different rates, the bundle's GST is a <strong>weighted blend</strong>: each product gets a share of the price in proportion to its retail value, and its own rate is applied to that share.</p>
                    <div class="example">
                        <span class="tag">Worked example</span>
                        {{ $sample['name'] }}: price {{ $m($sample['price']) }} + GST {{ $m($sample['gst']) }} = <strong>{{ $m($sample['total']) }}</strong> charged to the member. The GST amount is saved on that order the moment it's placed — changing a product's rate later never rewrites old orders.
                    </div>
                    <div class="rule">Plans show a <strong>cost-cap warning</strong> when the real cost of the products exceeds the target share of the price. That's your early signal that a plan is under-priced.</div>
                </div>
            </section>

            {{-- ======================================================= 12. REPORTS ======================================================= --}}
            <section class="card border g-card" id="g-reports">
                <div class="card-header">
                    <div class="chip info"><i class="bi bi-bar-chart-line"></i></div>
                    <div>
                        <h2>Reports</h2>
                        <p class="lede">Each report answers one question. Every table can be sorted, filtered and downloaded as CSV.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive rounded-3 border">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light"><tr><th class="ps-3">Report</th><th>The question it answers</th><th class="pe-3"></th></tr></thead>
                            <tbody>
                                @foreach ([
                                    ['admin.reports.overview', 'Business overview', 'How much came in, how much went out as income, what GST was collected, and what is left? Includes the effective matching rate and new vs. repurchase.'],
                                    ['admin.reports.sales', 'Sales & products', 'Which plans sell, what is the margin after product cost, who buys most, and exactly who bought which products?'],
                                    ['admin.reports.repurchase', 'Repurchase', 'How much of our business is repeat buying, and who are the repeat buyers?'],
                                    ['admin.reports.payouts', 'Payouts', 'What did we pay members by type, who earned most, how does each cycle compare to its pool?'],
                                    ['admin.reports.levels', 'Level-wise income', 'How is income spread across levels — and member by member?'],
                                    ['admin.reports.pending', 'Pending & cash', 'What is waiting on me: unverified payments, unsettled orders, withdrawals to dispatch?'],
                                    ['admin.reports.compliance', 'Compliance / audit', 'Did any cycle pay out more than its pool? Any plan over its cost cap?'],
                                ] as [$route, $name, $q])
                                    <tr>
                                        <td class="ps-3 fw-semibold text-nowrap">{{ $name }}</td>
                                        <td>{{ $q }}</td>
                                        <td class="pe-3 text-end text-nowrap">@if ($go($route))<a href="{{ $go($route) }}" class="goto text-decoration-none">Open <i class="bi bi-arrow-right"></i></a>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ======================================================= 13. TEAM ======================================================= --}}
            <section class="card border g-card" id="g-team">
                <div class="card-header">
                    <div class="chip"><i class="bi bi-person-badge"></i></div>
                    <div>
                        <h2>Team &amp; access</h2>
                        <p class="lede">You (the Super Admin) can share the work without sharing the money.</p>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="ps-3">
                        <li><strong>Super Admin</strong> sees and does everything, including creating sub-admins.</li>
                        <li><strong>Sub-admins</strong> sign in at the same admin login, see the same console, and only the sections you tick. They are shown as "Administrator" with nothing to mark them as limited.</li>
                        <li><strong>Finance &amp; money flow</strong> is the one switch for profit and cost: Business overview, Sales margin, Payouts, Level-wise income, Compliance, product costs, plan BV, GST, cycle totals, Settings and this guide. Off unless you grant it.</li>
                        <li><strong>The catalog is view-only</strong> for a sub-admin. Adding or editing products and categories needs <em>Catalog — add &amp; edit</em>; editing plans also needs Finance.</li>
                        <li><strong>Passwords</strong> can't be viewed by anyone. Generate a new one for a sub-admin and it is shown once so you can pass it on. Members have no password — they sign in with an OTP.</li>
                    </ul>
                    @if ($go('admin.team.index'))<a href="{{ $go('admin.team.index') }}" class="goto text-decoration-none">Open Sub-admins <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </section>

            {{-- ======================================================= 14. SETTINGS ======================================================= --}}
            <section class="card border g-card" id="g-settings">
                <div class="card-header">
                    <div class="chip warn"><i class="bi bi-gear"></i></div>
                    <div>
                        <h2>Settings cheat sheet</h2>
                        <p class="lede">Every setting, what it controls and what it is right now. All changes apply from the next cycle; the page checks your numbers live as you type.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive rounded-3 border">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light"><tr><th class="ps-3">Setting</th><th>What it controls</th><th class="text-end pe-3">Now</th></tr></thead>
                            <tbody>
                                <tr><td class="ps-3 fw-semibold">Sponsor required</td><td>Whether anyone can register without a sponsor code.</td><td class="text-end pe-3">{{ $s['requireSponsor'] ? 'Required' : 'Optional' }}</td></tr>
                                <tr><td class="ps-3 fw-semibold">GST</td><td>Not a setting: entered on each product (Products page).</td><td class="text-end pe-3">Per product</td></tr>
                                <tr><td class="ps-3 fw-semibold">Revenue split</td><td>Target cost / pool / company share. A guide and warning, must total 100%.</td><td class="text-end pe-3">{{ $s['costPct'] }} / {{ $s['poolPct'] }} / {{ $s['companyPct'] }}</td></tr>
                                <tr><td class="ps-3 fw-semibold">Self income</td><td>% of BV paid to the buyer on their own purchase.</td><td class="text-end pe-3">{{ $s['self'] }}%</td></tr>
                                <tr><td class="ps-3 fw-semibold">Sponsor income</td><td>% of BV paid to the introducer on each purchase by their direct.</td><td class="text-end pe-3">{{ $s['sponsor'] }}%</td></tr>
                                <tr><td class="ps-3 fw-semibold">Rank pool</td><td>% of the cycle's BV shared among rank achievers.</td><td class="text-end pe-3">{{ $s['rankPool'] }}%</td></tr>
                                <tr><td class="ps-3 fw-semibold">Level-wise matching</td><td>% of matched BV paid to each level above a match, and how many levels.</td><td class="text-end pe-3">{{ $s['depth'] }} levels · {{ $s['levelsTotal'] }}%</td></tr>
                                <tr><td class="ps-3 fw-semibold">Circuit breaker</td><td>Max matching one layer pays per cycle, as % of that layer's new BV. 100 = off.</td><td class="text-end pe-3">{{ $s['breaker'] }}%</td></tr>
                                <tr><td class="ps-3 fw-semibold">Matching caps</td><td>Most matching a member can earn per week / month. 0 = off.</td><td class="text-end pe-3">{{ $m($s['weeklyCap']) }} / {{ $m($s['monthlyCap']) }}</td></tr>
                                <tr><td class="ps-3 fw-semibold">Ranks</td><td>Rank names, matched-BV thresholds, active directs and pool points.</td><td class="text-end pe-3">{{ $ranks->count() }} ranks</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="rule mt-3"><strong>Rule of thumb for safe settings:</strong> add Self + Sponsor + Rank + the level total and keep the real-world payout comfortably under 100% of BV — leave a cushion because overlapping matches can pay more than the simple sum. Today that simple sum is <strong>{{ $s['self'] + $s['sponsor'] + $s['rankPool'] + $s['levelsTotal'] }}%</strong> of BV.</div>
                    @if ($go('admin.settings.edit'))<a href="{{ $go('admin.settings.edit') }}" class="goto text-decoration-none">Open Settings <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </section>

            {{-- ======================================================= 15. ROUTINE ======================================================= --}}
            <section class="card border g-card" id="g-routine">
                <div class="card-header">
                    <div class="chip good"><i class="bi bi-check2-square"></i></div>
                    <div>
                        <h2>Admin routine</h2>
                        <p class="lede">A simple rhythm that keeps the business running smoothly.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><div class="stat h-100"><div class="k">Every day</div><ul class="ps-3 mb-0 mt-1 small"><li>Verify new payments</li><li>Approve or reject withdrawal requests</li><li>Reply to messages</li></ul></div></div>
                        <div class="col-md-4"><div class="stat h-100"><div class="k">Each cycle (weekly / monthly)</div><ul class="ps-3 mb-0 mt-1 small"><li>Clear every pending payment</li><li>Approve the cycle</li><li>Build and release a dispatch batch with UTRs</li></ul></div></div>
                        <div class="col-md-4"><div class="stat h-100"><div class="k">After each cycle</div><ul class="ps-3 mb-0 mt-1 small"><li>Business overview: is the margin healthy?</li><li>Compliance: any cycle over its pool?</li><li>Check the effective matching rate</li></ul></div></div>
                    </div>
                </div>
            </section>

            {{-- ======================================================= 16. FAQ ======================================================= --}}
            <section class="card border g-card" id="g-faq">
                <div class="card-header">
                    <div class="chip info"><i class="bi bi-question-circle"></i></div>
                    <div>
                        <h2>Common questions</h2>
                        <p class="lede">The things people ask most.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAcc">
                        @foreach ([
                            ['A member paid but sees no income.', 'Income is only created when a cycle is approved. Check the order is <em>paid</em>, then approve a cycle — until then it shows as paid but unsettled in Pending &amp; cash.'],
                            ['Why did matching pay less than the percentage says?', 'One of four things: the volume was carry-forward and the <strong>circuit breaker</strong> scaled the cascade, a <strong>weekly/monthly cap</strong> was reached, only the smaller leg is matched, or the member hasn\'t bought this month (for rank).'],
                            ['Can I undo or rerun a cycle?', 'No. Approved cycles are final, which is why you verify payments first. Settings changes only affect future cycles.'],
                            ['Does a member at level 8 or deeper earn?', 'Yes. The cascade counts upward from whoever matched, so depth from the admin never excludes anyone. See Matching &amp; levels.'],
                            ['Is GST part of my profit?', 'No. It is collected for the government and reported separately. Profit is measured on the price excluding GST.'],
                            ['Why can\'t a member withdraw their whole wallet?', 'Money already requested (pending or approved but not yet paid) is reserved. Only the <em>available</em> balance can be requested.'],
                            ['A withdrawal was rejected — where did the money go?', 'Nowhere: it was only reserved. Rejecting releases it, so the full amount is available again.'],
                            ['Where is my profit?', 'Reports → Business overview: taxable revenue minus what was paid to members, plus the product-level margins in Sales &amp; products. Running costs (rent, staff, ads) are not tracked in the system.'],
                            ['Can I see a member\'s or sub-admin\'s password?', 'No — passwords are stored scrambled. For a sub-admin, generate a new one (shown once). Members use an OTP and have no password.'],
                            ['A new member joined but isn\'t in the leg I expected.', 'Check the invite link: it must include the side. If that slot was full, they were placed at the next open spot inside the same leg.'],
                        ] as $i => [$q, $a])
                            <div class="accordion-item">
                                <h3 class="accordion-header m-0" style="font-size:1rem">
                                    <button class="accordion-button {{ $i ? 'collapsed' : '' }} fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">{{ $q }}</button>
                                </h3>
                                <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqAcc"><div class="accordion-body small">{!! $a !!}</div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Plan explorer: recalculate the money breakdown for the chosen plan.
            const plans = @json($planData);
            const pick = document.getElementById('planPick');
            const inr = (n, d = 0) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d });
            if (pick) {
                const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
                pick.addEventListener('change', () => {
                    const p = plans.find(x => String(x.id) === pick.value);
                    if (!p) return;
                    set('pTotal', inr(p.total)); set('pGst', inr(p.gst)); set('pCost', inr(p.cost)); set('pBv', inr(p.bv)); set('pRest', inr(p.rest));
                    set('pSelf', inr(p.self, 2)); set('pSponsor', inr(p.sponsor, 2)); set('pLevels', inr(p.levels, 2));
                });
            }

            // Keep the left menu in step with the section being read.
            const links = document.querySelectorAll('#guideNav [data-section]');
            const cards = Array.from(links).map(l => document.getElementById(l.dataset.section)).filter(Boolean);
            const setActive = id => links.forEach(l => l.classList.toggle('active', l.dataset.section === id));
            const spy = () => {
                const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
                const cur = atBottom ? cards[cards.length - 1] : cards.filter(c => c.getBoundingClientRect().top <= 150).pop() || cards[0];
                if (cur) setActive(cur.id);
            };
            let tick = false;
            ['scroll', 'resize'].forEach(ev => window.addEventListener(ev, () => { if (!tick) { tick = true; requestAnimationFrame(() => { tick = false; spy(); }); } }, { passive: true }));
            links.forEach(l => l.addEventListener('click', () => setActive(l.dataset.section)));
            spy();
        });
    </script>
@endsection
