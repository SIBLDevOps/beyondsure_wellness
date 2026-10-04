<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Withdrawal;
use App\Support\Gst;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    /**
     * Paginate any in-memory collection cleanly with preserved query string.
     */
    public function paginateCollection(Collection $items, int $perPage = 15, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage($pageName);
        $total = $items->count();
        $results = $items->forPage($page, $perPage)->values();

        return (new ConcreteLengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        ))->withQueryString();
    }

    public function range(?string $from, ?string $to): array
    {
        return [
            'from' => $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth(),
            'to' => $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth(),
        ];
    }

    /**
     * Driver-agnostic month grouping expression (supports SQLite and MySQL).
     */
    protected function dateFormatMonth(string $column = 'created_at'): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public function businessOverview(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to])->whereIn('status', ['paid', 'settled'])->with('plan.products')->get();
        $revenue = (float) $orders->sum('amount');
        $bv = (float) $orders->sum('bv');
        $paidOut = (float) LedgerEntry::whereBetween('created_at', [$from, $to])->where('amount', '>', 0)->sum('amount');
        $matchingPaid = (float) LedgerEntry::whereBetween('created_at', [$from, $to])->where('type', 'matching')->where('amount', '>', 0)->sum('amount');
        $breakerPct = (float) Setting::get('circuit_breaker_pct', 60);

        $costPct = (float) Setting::get('split_cost_pct', 40);
        $poolPct = (float) Setting::get('split_pool_pct', 40);
        $companyPct = (float) Setting::get('split_company_pct', 20);
        // Each order's GST is allocated per the actual product(s) in its plan (Settings -> 0. Tax
        // assumption sets the fallback rate; a product with its own rate overrides that), so this
        // total is the real sum, not revenue split by one blended guess.
        $gstCollected = round($orders->sum(fn (Order $o) => $o->gstAmount()), 2);
        $taxableRevenue = round($revenue - $gstCollected, 2);
        $gstPct = $taxableRevenue > 0 ? round($gstCollected / $taxableRevenue * 100, 2) : Gst::pct();

        // v2 §2B: New vs. Repurchase — reported for visibility only. Self/Sponsor/Matching pay
        // identical rates either way (CompensationEngine never checks this flag).
        $newOrders = $orders->where('is_repurchase', false);
        $repurchaseOrders = $orders->where('is_repurchase', true);
        $repurchaseRevenue = (float) $repurchaseOrders->sum('amount');
        $repurchaseRatio = $revenue > 0 ? round($repurchaseRevenue / $revenue * 100, 2) : 0;

        $monthSql = $this->dateFormatMonth('created_at');
        $trend = Order::whereIn('status', ['paid', 'settled'])
            ->whereBetween('created_at', [now()->subMonths(5)->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("{$monthSql} as month, COUNT(*) as orders, SUM(amount) as revenue, SUM(bv) as bv")
            ->groupBy('month')->orderBy('month')->get();

        $previousRevenue = null;
        $trend = $trend->map(function ($row) use (&$previousRevenue) {
            $row->avg_order_value = $row->orders > 0 ? $row->revenue / $row->orders : 0;
            $row->growth_pct = $previousRevenue !== null && $previousRevenue > 0
                ? round(($row->revenue - $previousRevenue) / $previousRevenue * 100, 1)
                : null;
            $previousRevenue = (float) $row->revenue;

            return $row;
        });

        $trendAll = $trend;
        $trendPaginated = $this->paginateCollection($trendAll, 12, 'trend_page');

        return [
            'revenue' => $revenue,
            'bv' => $bv,
            'paid_out' => $paidOut,
            'payout_pct' => $revenue > 0 ? round($paidOut / $revenue * 100, 2) : 0,
            'net_margin' => $taxableRevenue - $paidOut,
            'avg_order_value' => $orders->count() > 0 ? $revenue / $orders->count() : 0,
            'order_count' => $orders->count(),
            'target_split' => ['cost' => $costPct, 'pool' => $poolPct, 'company' => $companyPct],
            'trend' => $trendPaginated,
            'trend_all' => $trendAll,
            'gst_pct' => $gstPct,
            'gst_collected' => $gstCollected,
            'taxable_revenue' => $taxableRevenue,
            'new_business_count' => $newOrders->count(),
            'new_business_revenue' => (float) $newOrders->sum('amount'),
            'repurchase_count' => $repurchaseOrders->count(),
            'repurchase_revenue' => $repurchaseRevenue,
            'repurchase_ratio' => $repurchaseRatio,
            // v2 §10: matching actually paid ÷ new BV generated. NOTE: the circuit breaker caps
            // each individual layer-event at breaker_pct of that layer's own new BV — it does not
            // bound this network-wide aggregate. One purchase can make several ancestors newly
            // matched at once, each running its own capped cascade, so this ratio can exceed
            // breaker_pct even though no single event ever did. Confirmed empirically: a 9-deep
            // placement chain produced 70.69% here against a 50% breaker.
            'matching_paid' => $matchingPaid,
            'effective_matching_rate' => $bv > 0 ? round($matchingPaid / $bv * 100, 2) : 0,
            'breaker_pct' => $breakerPct,
        ];
    }

    public function salesAndProducts(Carbon $from, Carbon $to): array
    {
        $byPlan = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'settled'])
            ->selectRaw('plan_id, COUNT(*) as orders, SUM(amount) as revenue, SUM(bv) as bv,
                SUM(COALESCE(taxable_amount, amount)) as taxable_value,
                SUM(COALESCE(gst_amount, 0)) as gst_collected')
            ->groupBy('plan_id')->with('plan.products')->get();

        $byPlan = $byPlan->map(function ($row) {
            $unitCost = $row->plan ? $row->plan->costForQty() : 0;
            $totalCost = $unitCost * $row->orders;
            $row->cost = $totalCost;
            // Summed straight from each order's own snapshotted tax breakdown — not recomputed
            // from current product rates, so a later rate change never reshapes past revenue.
            $row->taxable_value = (float) $row->taxable_value;
            $row->gst_collected = (float) $row->gst_collected;
            $row->margin = $row->taxable_value - $totalCost;
            $row->margin_pct = $row->taxable_value > 0 ? round($row->margin / $row->taxable_value * 100, 1) : 0;
            $row->avg_order_value = $row->orders > 0 ? $row->revenue / $row->orders : 0;

            return $row;
        })->sortByDesc('revenue')->values();

        $byPlanAll = $byPlan;
        $byPlanPaginated = $this->paginateCollection($byPlanAll, 10, 'plans_page');

        $topBuyersQuery = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'settled'])
            ->selectRaw('member_id, COUNT(*) as orders, SUM(amount) as total_spent')
            ->groupBy('member_id')->orderByDesc('total_spent')->with('member');

        $topBuyers = (clone $topBuyersQuery)->paginate(15, pageName: 'buyers_page')->withQueryString();
        $topBuyersAll = (clone $topBuyersQuery)->get();

        $purchases = $this->memberPurchasesPaginated($from, $to);

        return [
            'by_plan' => $byPlanPaginated,
            'by_plan_all' => $byPlanAll,
            'purchases' => $purchases,
            'top_buyers' => $topBuyers,
            'top_buyers_all' => $topBuyersAll,
            'total_revenue' => $byPlanAll->sum('revenue'),
            'total_gst_collected' => $byPlanAll->sum('gst_collected'),
            'total_taxable_value' => $byPlanAll->sum('taxable_value'),
            'total_margin' => $byPlanAll->sum('margin'),
        ];
    }

    /** One row per settled order: which member bought which plan, and exactly which products that plan contains. */
    public function memberPurchases(Carbon $from, Carbon $to): Collection
    {
        return $this->memberPurchasesQuery($from, $to)->get()->map(fn (Order $order) => $this->mapPurchaseRow($order));
    }

    /** Same report, paginated for on-screen display — CSV export uses the unpaginated memberPurchases() above. */
    public function memberPurchasesPaginated(Carbon $from, Carbon $to, int $perPage = 50): LengthAwarePaginator
    {
        return $this->memberPurchasesQuery($from, $to)
            ->paginate($perPage, pageName: 'purchases_page')
            ->withQueryString()
            ->through(fn (Order $order) => $this->mapPurchaseRow($order));
    }

    private function memberPurchasesQuery(Carbon $from, Carbon $to): Builder
    {
        return Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'settled'])
            ->with(['member', 'plan.products'])
            ->orderByDesc('created_at');
    }

    private function mapPurchaseRow(Order $order): array
    {
        return [
            'order_code' => $order->order_code,
            'date' => $order->created_at,
            'member' => $order->member,
            'plan' => $order->plan,
            'products' => $order->plan ? $order->plan->products->map(fn ($p) => "{$p->pivot->qty}x {$p->name}")->implode(', ') : '',
            'amount' => (float) $order->amount,
            'gst' => $order->gstAmount(),
            'taxable_value' => $order->netOfGst(),
            'bv' => (float) $order->bv,
            'status' => $order->status,
        ];
    }

    public function repurchaseReport(Carbon $from, Carbon $to): array
    {
        $allOrdersInRange = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'settled'])
            ->get();

        $totalRevenueAll = (float) $allOrdersInRange->sum('amount');
        $totalOrdersCount = $allOrdersInRange->count();

        // Repurchase orders in this range
        $repurchaseQuery = Order::whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['paid', 'settled'])
            ->where('is_repurchase', true);

        $repurchaseOrdersAll = (clone $repurchaseQuery)->with(['member', 'plan.products'])->orderByDesc('created_at')->get();
        $repurchaseOrdersPaginated = (clone $repurchaseQuery)->with(['member', 'plan.products'])->orderByDesc('created_at')
            ->paginate(15, pageName: 'orders_page')->withQueryString()
            ->through(fn (Order $order) => $this->mapPurchaseRow($order));

        $repurchaseOrdersMappedAll = $repurchaseOrdersAll->map(fn (Order $order) => $this->mapPurchaseRow($order));

        $repurchaseCount = $repurchaseOrdersAll->count();
        $repurchaseRevenue = (float) $repurchaseOrdersAll->sum('amount');
        $repurchaseBv = (float) $repurchaseOrdersAll->sum('bv');
        $repurchaseGst = round($repurchaseOrdersAll->sum(fn (Order $o) => $o->gstAmount()), 2);
        $repurchaseTaxable = round($repurchaseRevenue - $repurchaseGst, 2);
        $repurchaseRatio = $totalRevenueAll > 0 ? round($repurchaseRevenue / $totalRevenueAll * 100, 2) : 0.0;
        $avgRepurchaseValue = $repurchaseCount > 0 ? round($repurchaseRevenue / $repurchaseCount, 2) : 0.0;

        // Top repeat buyers
        $buyersGrouped = $repurchaseOrdersAll->groupBy('member_id')->map(function (Collection $orders, $memberId) {
            $member = $orders->first()?->member;
            $firstOrder = Order::where('member_id', $memberId)->whereIn('status', ['paid', 'settled'])->oldest('created_at')->first();

            return (object) [
                'member' => $member,
                'repurchase_orders' => $orders->count(),
                'total_spent' => (float) $orders->sum('amount'),
                'total_bv' => (float) $orders->sum('bv'),
                'first_order_date' => $firstOrder?->created_at,
                'latest_order_date' => $orders->max('created_at'),
            ];
        })->sortByDesc('total_spent')->values();

        $buyersPaginated = $this->paginateCollection($buyersGrouped, 15, 'buyers_page');

        // Repurchase by plan
        $byPlanGrouped = $repurchaseOrdersAll->groupBy('plan_id')->map(function (Collection $orders) use ($repurchaseRevenue) {
            $plan = $orders->first()?->plan;
            $rev = (float) $orders->sum('amount');

            return (object) [
                'plan' => $plan,
                'orders' => $orders->count(),
                'revenue' => $rev,
                'bv' => (float) $orders->sum('bv'),
                'avg_value' => $orders->count() > 0 ? round($rev / $orders->count(), 2) : 0.0,
                'share_pct' => $repurchaseRevenue > 0 ? round($rev / $repurchaseRevenue * 100, 1) : 0.0,
            ];
        })->sortByDesc('revenue')->values();

        $byPlanPaginated = $this->paginateCollection($byPlanGrouped, 10, 'plans_page');

        return [
            'total_revenue_all' => $totalRevenueAll,
            'total_orders_count' => $totalOrdersCount,
            'repurchase_count' => $repurchaseCount,
            'repurchase_revenue' => $repurchaseRevenue,
            'repurchase_taxable' => $repurchaseTaxable,
            'repurchase_gst' => $repurchaseGst,
            'repurchase_bv' => $repurchaseBv,
            'repurchase_ratio' => $repurchaseRatio,
            'avg_repurchase_value' => $avgRepurchaseValue,
            'unique_repeat_buyers' => $buyersGrouped->count(),
            'orders' => $repurchaseOrdersPaginated,
            'orders_all' => $repurchaseOrdersMappedAll,
            'top_buyers' => $buyersPaginated,
            'top_buyers_all' => $buyersGrouped,
            'by_plan' => $byPlanPaginated,
            'by_plan_all' => $byPlanGrouped,
        ];
    }

    public function payouts(Carbon $from, Carbon $to): array
    {
        $cyclesQuery = Cycle::whereBetween('period_end', [$from, $to])->orderByDesc('period_end');
        $cycles = (clone $cyclesQuery)->paginate(10, pageName: 'cycles_page')->withQueryString();
        $cyclesAll = (clone $cyclesQuery)->get();

        $byType = LedgerEntry::whereBetween('created_at', [$from, $to])
            ->where('amount', '>', 0)
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('type')->get();

        $earningsByMemberQuery = LedgerEntry::whereBetween('created_at', [$from, $to])
            ->where('amount', '>', 0)
            ->selectRaw('member_id, SUM(amount) as total')
            ->groupBy('member_id')->orderByDesc('total');

        $allEarnings = (clone $earningsByMemberQuery)->get();
        $topEarners = (clone $earningsByMemberQuery)->with('member')->paginate(15, pageName: 'earners_page')->withQueryString();
        $topEarnersAll = (clone $earningsByMemberQuery)->with('member')->get();

        $totalEarned = $allEarnings->sum('total');
        $top10Count = max(1, (int) ceil($allEarnings->count() * 0.1));
        $top10Concentration = $totalEarned > 0
            ? round($allEarnings->take($top10Count)->sum('total') / $totalEarned * 100, 2)
            : 0;

        $walletLiability = (float) LedgerEntry::sum('amount');

        return [
            'cycles' => $cycles,
            'cycles_all' => $cyclesAll,
            'by_type' => $byType,
            'top_earners' => $topEarners,
            'top_earners_all' => $topEarnersAll,
            'top10_concentration' => $top10Concentration,
            'wallet_liability' => $walletLiability,
        ];
    }

    public function pendingAndCash(): array
    {
        $pendingPaymentsQuery = Payment::where('status', 'pending')->with('order.member')->latest();
        $allPendingPayments = (clone $pendingPaymentsQuery)->get();
        $pendingPayments = (clone $pendingPaymentsQuery)->paginate(15, pageName: 'payments_page')->withQueryString();

        $agingBuckets = $allPendingPayments->groupBy(function (Payment $p) {
            $days = $p->created_at->diffInDays(now());

            return match (true) {
                $days <= 2 => '0-2 days',
                $days <= 7 => '3-7 days',
                default => '8+ days',
            };
        })->map->count();

        $withdrawalsQuery = Withdrawal::where('status', 'approved')
            ->whereDoesntHave('batches', fn ($q) => $q->where('status', 'draft'))
            ->with('member')->latest();
        $withdrawalsAwaitingDispatchAll = (clone $withdrawalsQuery)->get();
        $withdrawalsAwaitingDispatch = (clone $withdrawalsQuery)->paginate(15, pageName: 'withdrawals_page')->withQueryString();

        $unsettledOrdersQuery = Order::where('status', 'paid')->whereNull('cycle_id')->with('member', 'plan')->latest();
        $unsettledOrdersAll = (clone $unsettledOrdersQuery)->get();
        $unsettledOrders = (clone $unsettledOrdersQuery)->paginate(15, pageName: 'unsettled_page')->withQueryString();

        return [
            'pending_payments' => $pendingPayments,
            'pending_payments_all' => $allPendingPayments,
            'aging_buckets' => $agingBuckets,
            'withdrawals_awaiting_dispatch' => $withdrawalsAwaitingDispatch,
            'withdrawals_awaiting_dispatch_all' => $withdrawalsAwaitingDispatchAll,
            'unsettled_orders' => $unsettledOrders,
            'unsettled_orders_all' => $unsettledOrdersAll,
        ];
    }

    public function complianceSummary(Carbon $from, Carbon $to): array
    {
        $monthSql = $this->dateFormatMonth('created_at');
        $byMonth = LedgerEntry::whereBetween('created_at', [$from, $to])
            ->selectRaw("{$monthSql} as month, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as distributed, SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) as withdrawn")
            ->groupBy('month')->orderBy('month')->get();

        $byMonthPaginated = $this->paginateCollection($byMonth, 12, 'months_page');

        $overpaidQuery = Cycle::whereColumn('total_paid', '>', 'pool_bv')->latest('period_end');
        $overpaidCycles = (clone $overpaidQuery)->paginate(10, pageName: 'overpaid_page')->withQueryString();
        $overpaidCyclesAll = (clone $overpaidQuery)->get();

        $costCapBreachesAll = Plan::with('products')->get()->filter(function (Plan $plan) {
            $costPct = (float) Setting::get('split_cost_pct', 40);
            $costTarget = (float) $plan->price * $costPct / 100;

            return $plan->is_active && $plan->costForQty() > $costTarget;
        })->values();

        $costCapBreaches = $this->paginateCollection($costCapBreachesAll, 10, 'cost_cap_page');

        return [
            'by_month' => $byMonthPaginated,
            'by_month_all' => $byMonth,
            'overpaid_cycles' => $overpaidCycles,
            'overpaid_cycles_all' => $overpaidCyclesAll,
            'cost_cap_breaches' => $costCapBreaches,
            'cost_cap_breaches_all' => $costCapBreachesAll,
        ];
    }

    public function levelIncomeDistribution(Carbon $from, Carbon $to): array
    {
        $levelPcts = Setting::getLevelPercentages();
        $cascadeDepth = count($levelPcts);
        $selfPct = (float) Setting::get('self_pct', 10);
        $sponsorPct = (float) Setting::get('sponsor_pct', 20);
        $matchingPct = (float) Setting::get('matching_pct', 10);
        $rankPoolPct = (float) Setting::get('rank_pool_pct', 3);
        $splitCostPct = (float) Setting::get('split_cost_pct', 40);
        $splitPoolPct = (float) Setting::get('split_pool_pct', 40);
        $splitCompanyPct = (float) Setting::get('split_company_pct', 20);
        $monthlyCap = (float) Setting::get('match_cap_per_cycle', 600000);
        $weeklyCap = (float) Setting::get('match_cap_per_week', 150000);
        $breakerPct = (float) Setting::get('circuit_breaker_pct', 60);
        $totalBv = (float) Order::whereBetween('created_at', [$from, $to])->whereIn('status', ['paid', 'settled'])->sum('bv');

        // Simulation for active plans
        $plans = Plan::where('is_active', true)->orderBy('price')->get()->map(function (Plan $plan) use ($levelPcts, $selfPct, $sponsorPct) {
            $selfAmt = round($plan->bv * ($selfPct / 100), 2);
            $sponsorAmt = round($plan->bv * ($sponsorPct / 100), 2);

            $levelAmounts = [];
            $totalCascade = 0.0;
            foreach ($levelPcts as $lvl => $pct) {
                $amt = round($plan->bv * ($pct / 100), 2);
                $levelAmounts[$lvl] = $amt;
                $totalCascade += $amt;
            }
            $maxPayout = round($selfAmt + $sponsorAmt + $totalCascade, 2);

            return [
                'plan' => $plan,
                'self_amount' => $selfAmt,
                'sponsor_amount' => $sponsorAmt,
                'matching_per_level' => $levelAmounts[1] ?? 0,
                'level_amounts' => $levelAmounts,
                'total_cascade' => $totalCascade,
                'max_payout' => $maxPayout,
                'payout_pct' => $plan->bv > 0 ? round(($maxPayout / $plan->bv) * 100, 1) : 0,
            ];
        });

        // Actual distributed entries in date range
        $allEntries = LedgerEntry::whereBetween('created_at', [$from, $to])
            ->where('amount', '>', 0)
            ->with(['member', 'sourceMember'])
            ->get();

        $selfEntries = $allEntries->where('type', 'self');
        $sponsorEntries = $allEntries->where('type', 'sponsor');
        $matchingEntries = $allEntries->where('type', 'matching');
        $rankEntries = $allEntries->where('type', 'rank');

        $totalSelf = (float) $selfEntries->sum('amount');
        $totalSponsor = (float) $sponsorEntries->sum('amount');
        $totalMatching = (float) $matchingEntries->sum('amount');
        $totalRank = (float) $rankEntries->sum('amount');
        $totalDistributed = $totalSelf + $totalSponsor + $totalMatching + $totalRank;

        // Map matching entries to their cascade level. The engine (CompensationEngine::payMatching)
        // writes the exact level into every entry's description as "Matching cascade (Level N) from
        // {code}" — read that back directly instead of re-deriving it by walking the placement tree,
        // which previously undercounted every level by one (it started counting one step too late).
        $matchingByLevel = [];
        foreach (array_keys($levelPcts) as $lvl) {
            $matchingByLevel[$lvl] = [
                'amount' => 0.0,
                'entries' => 0,
                'earners' => [],
            ];
        }

        foreach ($matchingEntries as $entry) {
            $earnerId = $entry->member_id;

            $level = 1;
            if (preg_match('/Level (\d+)/', (string) $entry->description, $m)) {
                $level = (int) $m[1];
            }

            if (! isset($matchingByLevel[$level])) {
                $matchingByLevel[$level] = [
                    'amount' => 0.0,
                    'entries' => 0,
                    'earners' => [],
                ];
            }

            $matchingByLevel[$level]['amount'] += (float) $entry->amount;
            $matchingByLevel[$level]['entries']++;
            $matchingByLevel[$level]['earners'][$earnerId] = true;
        }

        // Level-by-level summary rows. Walk every level that is either currently configured OR
        // has real historical ledger entries (e.g. a level paid out under a cascade depth that
        // was later shortened) — showing only the configured range would silently drop real,
        // already-paid money from the table.
        $allLevelNumbers = collect(array_keys($levelPcts))
            ->merge(array_keys($matchingByLevel))
            ->unique()
            ->sort()
            ->values();

        $levelRows = [];
        foreach ($allLevelNumbers as $lvl) {
            $pct = $levelPcts[$lvl] ?? 0;
            $matchData = $matchingByLevel[$lvl] ?? ['amount' => 0.0, 'entries' => 0, 'earners' => []];
            $matchAmt = (float) $matchData['amount'];
            $sponAmt = ($lvl === 1) ? $totalSponsor : 0.0;
            $lvlTotal = $matchAmt + $sponAmt;
            $uniqueEarnersCount = ($lvl === 1)
                ? count(array_unique(array_merge(array_keys($matchData['earners'] ?? []), $sponsorEntries->pluck('member_id')->all())))
                : count($matchData['earners'] ?? []);
            $isActiveDepth = isset($levelPcts[$lvl]);

            $levelRows[] = [
                'level' => $lvl,
                'label' => $lvl === 1 ? 'Level 1 (Direct Referral & 1st Match)' : "Level {$lvl} (Generation {$lvl})",
                'matching_rate' => $pct,
                'sponsor_rate' => ($lvl === 1) ? $sponsorPct : 0,
                'sponsor_paid' => $sponAmt,
                'matching_paid' => $matchAmt,
                'total_paid' => $lvlTotal,
                'entries_count' => ($lvl === 1 ? $sponsorEntries->count() : 0) + ($matchData['entries'] ?? 0),
                'unique_earners' => $uniqueEarnersCount,
                'share_pct' => $totalDistributed > 0 ? round(($lvlTotal / $totalDistributed) * 100, 1) : 0,
                'is_active_depth' => $isActiveDepth,
            ];
        }

        return [
            'rates' => [
                'cascade_depth' => $cascadeDepth,
                'self_pct' => $selfPct,
                'sponsor_pct' => $sponsorPct,
                'matching_pct' => $matchingPct,
                'level_pcts' => $levelPcts,
                'total_level_pct' => array_sum($levelPcts),
                'rank_pool_pct' => $rankPoolPct,
                'split_cost_pct' => $splitCostPct,
                'split_pool_pct' => $splitPoolPct,
                'split_company_pct' => $splitCompanyPct,
                'monthly_cap' => $monthlyCap,
                'weekly_cap' => $weeklyCap,
                'breaker_pct' => $breakerPct,
                'max_cascade_pct' => array_sum($levelPcts),
                'total_compensation_pct' => round($selfPct + $sponsorPct + array_sum($levelPcts) + $rankPoolPct, 2),
            ],
            'plans' => $plans,
            'summary' => [
                'total_distributed' => $totalDistributed,
                'total_level_income' => $totalSponsor + $totalMatching,
                'total_sponsor' => $totalSponsor,
                'total_matching' => $totalMatching,
                'total_self' => $totalSelf,
                'total_rank' => $totalRank,
                // v2 §10: matching paid ÷ new BV generated. The breaker caps each layer-event,
                // not this network-wide ratio — see the longer note in businessOverview() above.
                'effective_matching_rate' => $totalBv > 0 ? round($totalMatching / $totalBv * 100, 2) : 0,
            ],
            'level_rows' => collect($levelRows),
        ];
    }

    /**
     * The same money as levelIncomeDistribution(), viewed per member instead of per level —
     * every member who earned anything in range, with their full Self/Sponsor/Matching/Rank
     * split and how deep they sit in the placement tree. Depth is counted from the member
     * themself, not from the admin/root: a member's own cascade always reaches the same number
     * of upline generations regardless of how many generations exist above them to the root —
     * depth here is shown for context only, it does not gate eligibility.
     */
    public function memberIncomeBreakdownPaginated(Carbon $from, Carbon $to, int $perPage = 50): LengthAwarePaginator
    {
        return $this->memberIncomeBreakdownQuery($from, $to)
            ->paginate($perPage, pageName: 'earners_page')
            ->withQueryString()
            ->through(fn (LedgerEntry $row) => $this->mapMemberIncomeRow($row));
    }

    /** Unpaginated — every earner in range, for CSV export. */
    public function memberIncomeBreakdown(Carbon $from, Carbon $to): Collection
    {
        return $this->memberIncomeBreakdownQuery($from, $to)->get()->map(fn (LedgerEntry $row) => $this->mapMemberIncomeRow($row));
    }

    private function memberIncomeBreakdownQuery(Carbon $from, Carbon $to): Builder
    {
        return LedgerEntry::whereBetween('created_at', [$from, $to])
            ->where('amount', '>', 0)
            ->selectRaw('member_id,
                SUM(CASE WHEN type = "self" THEN amount ELSE 0 END) as self_income,
                SUM(CASE WHEN type = "sponsor" THEN amount ELSE 0 END) as sponsor_income,
                SUM(CASE WHEN type = "matching" THEN amount ELSE 0 END) as matching_income,
                SUM(CASE WHEN type = "rank" THEN amount ELSE 0 END) as rank_income,
                SUM(amount) as total_income,
                COUNT(*) as entries')
            ->groupBy('member_id')
            ->orderByDesc('total_income')
            ->with('member');
    }

    private function mapMemberIncomeRow(LedgerEntry $row): array
    {
        $member = $row->member;
        $depth = $member && $member->path ? substr_count(trim($member->path, '.'), '.') + 1 : 0;

        return [
            'member' => $member,
            'depth' => $depth,
            'self_income' => (float) $row->self_income,
            'sponsor_income' => (float) $row->sponsor_income,
            'matching_income' => (float) $row->matching_income,
            'rank_income' => (float) $row->rank_income,
            'total_income' => (float) $row->total_income,
            'entries' => (int) $row->entries,
        ];
    }

    public function csv(Collection|array $rows, array $headers): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
