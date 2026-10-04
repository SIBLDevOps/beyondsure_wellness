<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\MemberLegTotal;
use App\Models\MemberWeeklyTotal;
use App\Models\Order;
use App\Models\Rank;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CompensationEngine
{
    private float $selfPct;

    private float $sponsorPct;

    private float $matchingPct;

    private int $cascadeDepth;

    /** @var array<int, float> */
    private array $levelPcts;

    private float $rankPoolPct;

    private float $monthlyCap;

    private float $weeklyCap;

    private float $breakerPct;

    /**
     * v2 model §4 (Eligibility): minimum new BV a layer must generate in a cycle before matching
     * applies at all, keyed by layer depth (member's own generation from the root) with a
     * 'fallback' for anything deeper than configured. Empty/zero = gate off. Self and Sponsor
     * are never gated — only matching.
     *
     * @var array<int|string, float>
     */
    private array $eligibilityMinVolumes;

    /**
     * New (non-carry-forward) BV credited to each node's legs during the current run(),
     * keyed by member_id => ['left' => float, 'right' => float]. Powers the v2 circuit
     * breaker, which is measured against a layer's *new* BV, not its matched BV.
     *
     * @var array<int, array{left: float, right: float}>
     */
    private array $newBvThisRun = [];

    public function __construct()
    {
        $this->selfPct = (float) Setting::get('self_pct', 10) / 100;
        $this->sponsorPct = (float) Setting::get('sponsor_pct', 20) / 100;
        $this->matchingPct = (float) Setting::get('matching_pct', 10) / 100;
        $this->cascadeDepth = (int) Setting::get('cascade_depth', 7);
        $this->levelPcts = Setting::getLevelPercentages();
        $this->rankPoolPct = (float) Setting::get('rank_pool_pct', 3) / 100;
        $this->monthlyCap = (float) Setting::get('match_cap_per_cycle', 600000);
        $this->weeklyCap = (float) Setting::get('match_cap_per_week', 150000);
        $this->breakerPct = (float) Setting::get('circuit_breaker_pct', 60) / 100;
        $this->eligibilityMinVolumes = Setting::getEligibilityMinVolumes();
    }

    /**
     * Settle every paid, unassigned order into $cycle: post Self, Sponsor and Matching
     * income to the ledger, update leg totals, then pay the Rank pool from whatever
     * budget is left. R9: matching payouts are capped both per calendar month and per
     * ISO week (Phase 2 §3.4 option 2 — tracked independently of cycle length).
     */
    public function run(Cycle $cycle): void
    {
        DB::transaction(function () use ($cycle) {
            $this->newBvThisRun = [];
            $orders = Order::where('status', 'paid')->whereNull('cycle_id')->with('member.sponsor')->get();

            $poolBv = 0.0;
            $totalPaid = 0.0;

            foreach ($orders as $order) {
                $order->update(['status' => 'settled', 'cycle_id' => $cycle->id]);
                $poolBv += (float) $order->bv;
                $totalPaid += $this->postSelfAndSponsor($order, $cycle);
                $this->creditLegTotals($order);
            }

            $totalPaid += $this->runMatching($cycle, $poolBv, $totalPaid);
            $totalPaid += $this->runRankPool($cycle, $poolBv, $totalPaid);

            $cycle->update([
                'status' => 'approved',
                'pool_bv' => $poolBv,
                'total_paid' => $totalPaid,
                'approved_at' => now(),
            ]);
        });
    }

    private function postSelfAndSponsor(Order $order, Cycle $cycle): float
    {
        $paid = 0.0;
        $bv = (float) $order->bv;

        $selfAmt = round($bv * $this->selfPct, 2);
        if ($selfAmt > 0) {
            LedgerEntry::create([
                'member_id' => $order->member_id,
                'source_member_id' => $order->member_id,
                'order_id' => $order->id,
                'cycle_id' => $cycle->id,
                'type' => 'self',
                'amount' => $selfAmt,
                'description' => "Self income on order {$order->order_code}",
            ]);
            $paid += $selfAmt;
        }

        if ($order->member->sponsor_id) {
            $sponsorAmt = round($bv * $this->sponsorPct, 2);
            if ($sponsorAmt > 0) {
                LedgerEntry::create([
                    'member_id' => $order->member->sponsor_id,
                    'source_member_id' => $order->member_id,
                    'order_id' => $order->id,
                    'cycle_id' => $cycle->id,
                    'type' => 'sponsor',
                    'amount' => $sponsorAmt,
                    'description' => "Sponsor income on order {$order->order_code}",
                ]);
                $paid += $sponsorAmt;
            }
        }

        return $paid;
    }

    /** Walk from the buying member up to the root, crediting each ancestor's left/right leg. */
    private function creditLegTotals(Order $order): void
    {
        $current = $order->member;

        while ($current->placement_id) {
            $parent = $current->placementParent;
            $side = $current->position === 'left' ? 'left' : 'right';
            $field = "{$side}_bv";

            $legTotal = MemberLegTotal::firstOrCreate(['member_id' => $parent->id]);
            $legTotal->increment($field, (float) $order->bv);

            $this->newBvThisRun[$parent->id][$side] = ($this->newBvThisRun[$parent->id][$side] ?? 0.0) + (float) $order->bv;

            $current = $parent;
        }
    }

    /**
     * Dual-Layer Circuit Breaker & Solvency Protection:
     * 1. Layer-Level Breaker: matching paid on a layer cannot exceed breakerPct of that layer's *new* BV.
     * 2. Global Solvency Breaker: aggregate matching across all layers cannot exceed available cycle Pool BV
     *    (poolBv - self/sponsor paid). Guarantees the company NEVER loses money or overpays under any scenario.
     */
    private function runMatching(Cycle $cycle, float $poolBv = INF, float $paidSoFar = 0.0): float
    {
        $totalPaid = 0.0;
        $isoYear = (int) $cycle->period_end->format('o');
        $isoWeek = (int) $cycle->period_end->format('W');
        $pendingPayouts = [];
        $grossTotalAcrossNetwork = 0.0;

        foreach (MemberLegTotal::all() as $legTotal) {
            $newlyMatched = min((float) $legTotal->left_bv, (float) $legTotal->right_bv) - (float) $legTotal->matched_bv;

            if ($newlyMatched <= 0) {
                continue;
            }

            $legTotal->update(['matched_bv' => (float) $legTotal->matched_bv + $newlyMatched]);

            $node = Member::find($legTotal->member_id);

            $newBv = ($this->newBvThisRun[$legTotal->member_id]['left'] ?? 0.0)
                + ($this->newBvThisRun[$legTotal->member_id]['right'] ?? 0.0);

            // v2 §4 Eligibility: below this layer's minimum volume, the match still consumes BV
            // (carry-forward is unaffected) but the payout is forfeited to the company outright —
            // not deferred, not retried next cycle.
            if (! $this->isEligible($node, $newBv)) {
                continue;
            }

            $payees = $this->cascadePayees($node);

            $gross = [];
            $grossTotal = 0.0;
            foreach ($payees as $item) {
                $pct = isset($this->levelPcts[$item['level']]) ? ($this->levelPcts[$item['level']] / 100) : $this->matchingPct;
                $amt = round($newlyMatched * $pct, 2);
                $gross[] = ['member' => $item['member'], 'level' => $item['level'], 'amount' => $amt];
                $grossTotal += $amt;
            }

            if ($grossTotal <= 0) {
                continue;
            }

            // Layer-level circuit breaker: cannot exceed breakerPct of this layer's new BV
            $cap = round($newBv * $this->breakerPct, 2);
            $scale = $grossTotal > $cap ? ($cap > 0 ? $cap / $grossTotal : 0.0) : 1.0;

            foreach ($gross as $g) {
                $scaledAmt = round($g['amount'] * $scale, 2);

                if ($scaledAmt > 0) {
                    $pendingPayouts[] = [
                        'payee' => $g['member'],
                        'source' => $node,
                        'gross' => $scaledAmt,
                        'level' => $g['level'],
                    ];
                    $grossTotalAcrossNetwork += $scaledAmt;
                }
            }
        }

        if (empty($pendingPayouts)) {
            return 0.0;
        }

        // Global Cycle Solvency Circuit Breaker:
        // Total matching paid across the network must never exceed available Pool BV budget.
        $availableBudget = is_infinite($poolBv) ? INF : max(0.0, $poolBv - $paidSoFar);
        $networkScale = ($availableBudget < $grossTotalAcrossNetwork && $grossTotalAcrossNetwork > 0)
            ? ($availableBudget / $grossTotalAcrossNetwork)
            : 1.0;

        foreach ($pendingPayouts as $p) {
            $finalAmt = round($p['gross'] * $networkScale, 2);

            if ($finalAmt > 0) {
                $totalPaid += $this->payMatching($p['payee'], $p['source'], $finalAmt, $p['level'], $cycle, $isoYear, $isoWeek);
            }
        }

        return $totalPaid;
    }

    /** True when the gate is off, or this layer's own new BV this cycle meets its minimum. */
    private function isEligible(Member $node, float $newBv): bool
    {
        if (empty($this->eligibilityMinVolumes)) {
            return true;
        }

        $layer = max(1, $this->memberDepth($node));
        $minRequired = $this->eligibilityMinVolumes[$layer] ?? $this->eligibilityMinVolumes['fallback'] ?? 0.0;

        return $minRequired <= 0 || $newBv >= $minRequired;
    }

    /** Generations below the root, counted from the member's own placement path (root = 0). */
    private function memberDepth(Member $node): int
    {
        return $node->path ? substr_count(trim($node->path, '.'), '.') + 1 : 0;
    }

    /**
     * The matching member (level 1) plus up to (cascadeDepth - 1) placement ancestors above them.
     *
     * @return array<int, array{member: Member, level: int}>
     */
    private function cascadePayees(Member $node): array
    {
        $payees = [['member' => $node, 'level' => 1]];
        $current = $node;

        for ($i = 1; $i < $this->cascadeDepth && $current->placement_id; $i++) {
            $current = $current->placementParent;
            $payees[] = ['member' => $current, 'level' => $i + 1];
        }

        return $payees;
    }

    /** $grossAmt is this payee's share after the v2 circuit breaker has already been applied. */
    private function payMatching(Member $payee, Member $sourceNode, float $grossAmt, int $level, Cycle $cycle, int $isoYear, int $isoWeek): float
    {
        $monthPaid = (float) LedgerEntry::where('member_id', $payee->id)
            ->where('type', 'matching')
            ->whereMonth('created_at', $cycle->period_end->month)
            ->whereYear('created_at', $cycle->period_end->year)
            ->sum('amount');

        $weekly = MemberWeeklyTotal::firstOrCreate([
            'member_id' => $payee->id,
            'iso_year' => $isoYear,
            'iso_week' => $isoWeek,
        ]);

        $monthlyRemaining = $this->monthlyCap > 0 ? max(0, $this->monthlyCap - $monthPaid) : INF;
        $weeklyRemaining = $this->weeklyCap > 0 ? max(0, $this->weeklyCap - (float) $weekly->matched_total) : INF;

        $payAmt = round(min($grossAmt, $monthlyRemaining, $weeklyRemaining), 2);

        if ($payAmt <= 0) {
            return 0.0;
        }

        LedgerEntry::create([
            'member_id' => $payee->id,
            'source_member_id' => $sourceNode->id,
            'cycle_id' => $cycle->id,
            'type' => 'matching',
            'amount' => $payAmt,
            'description' => "Matching cascade (Level {$level}) from {$sourceNode->member_code}",
        ]);

        $weekly->increment('matched_total', $payAmt);

        return $payAmt;
    }

    /** Rank pool: target is rankPoolPct of poolBv, but only what's left after Self+Sponsor+Matching. */
    private function runRankPool(Cycle $cycle, float $poolBv, float $paidSoFar): float
    {
        $target = round($poolBv * $this->rankPoolPct, 2);
        $budget = min($target, max(0, $poolBv - $paidSoFar));

        if ($budget <= 0) {
            return 0.0;
        }

        $ranksDesc = Rank::orderByDesc('matched_bv_threshold')->get();
        $qualifiers = collect();

        foreach (Member::where('status', 'active')->with('legTotal')->get() as $member) {
            if (! $member->legTotal || ! $member->isActiveThisMonth()) {
                continue;
            }

            $best = $ranksDesc->first(fn (Rank $rank) => (float) $member->legTotal->matched_bv >= (float) $rank->matched_bv_threshold
                && $member->activeDirectSponsoredCount() >= $rank->min_active_directs);

            if ($best) {
                $qualifiers->push(['member' => $member, 'rank' => $best]);
                $member->update(['rank' => $best->name]);
            }
        }

        $totalShares = $qualifiers->sum(fn ($q) => $q['rank']->share);

        if ($totalShares <= 0) {
            return 0.0;
        }

        $perShare = $budget / $totalShares;
        $totalPaid = 0.0;

        foreach ($qualifiers as $q) {
            $amt = round($perShare * $q['rank']->share, 2);

            if ($amt <= 0) {
                continue;
            }

            LedgerEntry::create([
                'member_id' => $q['member']->id,
                'cycle_id' => $cycle->id,
                'type' => 'rank',
                'amount' => $amt,
                'description' => "Rank pool — {$q['rank']->name}",
            ]);

            $totalPaid += $amt;
        }

        return $totalPaid;
    }
}
