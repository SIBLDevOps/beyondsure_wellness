<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class NetworkService
{
    /**
     * Every downline query — member portal or admin — must go through this method.
     * Scopes strictly to the given root's own subtree via the materialized path column,
     * so a member can never see another member's subtree by guessing an ID.
     */
    public function subtreeOf(Member $root, bool $includeRoot = false): Builder
    {
        $prefix = ($root->path ?? '').$root->id.'.';

        return Member::query()->where(function (Builder $q) use ($prefix, $root, $includeRoot) {
            $q->where('path', 'like', $prefix.'%');

            if ($includeRoot) {
                $q->orWhere('id', $root->id);
            }
        });
    }

    /** Build a nested tree array (placement tree) rooted at $root, down to $depth levels. */
    public function buildTree(Member $root, int $depth = 6): array
    {
        $root->loadMissing(['sponsor', 'legTotal']);

        return $this->node($root, $depth, 1);
    }

    private function node(Member $member, int $maxDepth, int $level): array
    {
        $personalBv = (float) Order::where('member_id', $member->id)
            ->whereIn('status', ['paid', 'settled'])
            ->sum('bv');

        $node = [
            'member' => $member,
            'level' => $level,
            'active_this_month' => $member->isActiveThisMonth(),
            'personal_bv' => $personalBv,
            'left_bv' => (float) ($member->legTotal?->left_bv ?? 0),
            'right_bv' => (float) ($member->legTotal?->right_bv ?? 0),
            'matched_bv' => (float) ($member->legTotal?->matched_bv ?? 0),
            'left' => null,
            'right' => null,
        ];

        if ($level >= $maxDepth) {
            return $node;
        }

        $children = Member::with(['sponsor', 'legTotal'])
            ->where('placement_id', $member->id)
            ->get()
            ->keyBy('position');

        if ($left = $children->get('left')) {
            $node['left'] = $this->node($left, $maxDepth, $level + 1);
        }

        if ($right = $children->get('right')) {
            $node['right'] = $this->node($right, $maxDepth, $level + 1);
        }

        return $node;
    }

    /**
     * Per-generation counts (Level 1 = direct placements, Level 2 = their children, ...)
     * down to $depth levels: member count, active-this-month count, BV contributed,
     * and income earned by $root from members at each level.
     */
    public function levelBreakdown(Member $root, int $depth = 6): array
    {
        $levels = [];
        $frontier = [$root];

        for ($level = 1; $level <= $depth; $level++) {
            $next = [];
            foreach ($frontier as $node) {
                foreach (Member::where('placement_id', $node->id)->get() as $child) {
                    $next[] = $child;
                }
            }

            if (empty($next)) {
                break;
            }

            $ids = array_map(fn (Member $m) => $m->id, $next);
            $activeCount = collect($next)->filter(fn (Member $m) => $m->isActiveThisMonth())->count();
            $bv = Order::whereIn('member_id', $ids)
                ->whereIn('status', ['paid', 'settled'])
                ->sum('bv');

            $matchingIncome = (float) LedgerEntry::where('member_id', $root->id)
                ->where('type', 'matching')
                ->whereIn('source_member_id', $ids)
                ->sum('amount');

            $sponsorIncome = (float) LedgerEntry::where('member_id', $root->id)
                ->where('type', 'sponsor')
                ->whereIn('source_member_id', $ids)
                ->sum('amount');

            $membersAtLevel = collect($next)->map(function (Member $m) use ($root) {
                $earned = (float) LedgerEntry::where('member_id', $root->id)
                    ->whereIn('type', ['matching', 'sponsor'])
                    ->where('source_member_id', $m->id)
                    ->sum('amount');
                $mBv = (float) Order::where('member_id', $m->id)
                    ->whereIn('status', ['paid', 'settled'])
                    ->sum('bv');

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'member_code' => $m->member_code,
                    'position' => $m->position,
                    'is_active' => $m->isActiveThisMonth(),
                    'bv' => $mBv,
                    'income_to_root' => $earned,
                ];
            });

            $levels[] = [
                'level' => $level,
                'count' => count($next),
                'active_count' => $activeCount,
                'bv' => (float) $bv,
                'matching_income' => $matchingIncome,
                'sponsor_income' => $sponsorIncome,
                'total_income' => $matchingIncome + $sponsorIncome,
                'members' => $membersAtLevel,
            ];

            $frontier = $next;
        }

        return $levels;
    }

    /** Sponsor chain (who introduced whom) from $member up to the root sponsor. */
    public function sponsorChain(Member $member): array
    {
        $chain = [];
        $current = $member->sponsor;

        while ($current) {
            $chain[] = $current;
            $current = $current->sponsor;
        }

        return $chain;
    }

    /** Placement chain (binary tree ancestry) from $member up to the root of the tree. */
    public function placementChain(Member $member): array
    {
        $chain = [];
        $current = $member->placementParent;

        while ($current) {
            $chain[] = $current;
            $current = $current->placementParent;
        }

        return $chain;
    }
}
