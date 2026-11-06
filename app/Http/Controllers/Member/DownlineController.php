<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Setting;
use App\Services\NetworkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DownlineController extends Controller
{
    public function __construct(private readonly NetworkService $network) {}

    /** The admin decides whether people who have not paid yet show in the downline (Settings, "Non-active members"). */
    private function hideUnpaid(): bool
    {
        return ! \App\Support\CompensationSettings::showInactiveInDownline();
    }

    /**
     * Deliberately takes no {member} route parameter — always resolves to the logged-in
     * member from the session, so a member can never view another member's subtree by
     * guessing an ID in the URL (Phase 2 §3.2).
     */
    public function index(Request $request): View
    {
        $member = Auth::guard('member')->user();
        $depth = (int) $request->input('depth', 4);
        $depth = max(2, min(7, $depth));

        return view('member.downline', [
            'member' => $member,
            'tree' => $this->network->buildTree($member, $depth, activeOnly: $this->hideUnpaid()),
            'depth' => $depth,
            'legTotal' => $member->legTotal,
            'levelBreakdown' => $this->network->levelBreakdown($member, $depth, activeOnly: $this->hideUnpaid()),
        ]);
    }

    public function team(): View
    {
        $member = Auth::guard('member')->user();
        $nowMonth = now()->month;
        $nowYear = now()->year;

        // Everyone the member sponsored, with those who have not paid labelled "Not active", unless the admin chose to
        // show only people who have paid (they are always on the Pending page either way).
        $directs = Member::where('sponsor_id', $member->id)
            ->when($this->hideUnpaid(), fn ($q) => $q->where('status', 'active'))
            ->with(['placementParent', 'legTotal'])
            ->withCount('sponsoredMembers')
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->whereIn('status', ['paid', 'settled'])])
            ->withSum(['orders as personal_bv' => fn ($q) => $q->whereIn('status', ['paid', 'settled'])], 'bv')
            ->withExists(['orders as active_this_month' => fn ($q) => $q
                ->whereIn('status', ['paid', 'settled'])
                ->whereMonth('created_at', $nowMonth)
                ->whereYear('created_at', $nowYear)])
            ->latest()
            ->get();

        return view('member.team', [
            'directs' => $directs,
        ]);
    }

    public function levels(Request $request): View
    {
        $member = Auth::guard('member')->user();
        $depth = (int) $request->input('depth', 7);
        $depth = max(2, min(12, $depth));
        $cascadeDepth = (int) Setting::get('cascade_depth', 7);
        $levels = $this->network->levelBreakdown($member, $depth, activeOnly: $this->hideUnpaid());

        $report = $this->incomeReport($member, $levels);

        // Every rupee, line by line: what it was, from whom, and the working. Filter by type; anything else means all.
        $type = $request->query('type');
        $type = (is_string($type) && in_array($type, ['self', 'sponsor', 'matching', 'rank'], true)) ? $type : null;
        $entries = \App\Models\LedgerEntry::where('member_id', $member->id)->whereIn('type', ['self', 'sponsor', 'matching', 'rank'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->with('sourceMember:id,name,member_code', 'order:id,order_code,bv');
        $statementTotal = (float) (clone $entries)->sum('amount');
        $statement = (clone $entries)->latest('id')->paginate(15)->withQueryString()->through(fn ($e) => [
            'date' => $e->created_at,
            'type' => $e->type,
            'from' => $e->sourceMember,
            'amount' => (float) $e->amount,
            'how' => $this->explainIncome($e->type, (string) $e->description, (float) $e->amount, $e->order?->bv, $e->order?->order_code, $e->sourceMember?->name, $e->source_member_id === $member->id),
        ]);

        foreach ($report['lost']['rows'] as $row) {
            $row->how = $this->explainIncome($row->type, (string) $row->description, (float) $row->amount, $row->order?->bv, $row->order?->order_code, $row->sourceMember?->name, $row->source_member_id === $member->id);
        }

        return view('member.levels', [
            'statement' => $statement,
            'statementType' => $type,
            'statementTotal' => $statementTotal,
            'report' => $report,
            'levels' => $levels,
            'depth' => $depth,
            'cascadeDepth' => $cascadeDepth,
            'levelPcts' => Setting::getLevelPercentages(),
            'sponsorPct' => (float) Setting::get('sponsor_pct', 20),
            'matchingPct' => (float) Setting::get('matching_pct', 10),
            'totalSponsor' => collect($levels)->sum('sponsor_income'),
            'totalMatching' => collect($levels)->sum('matching_income'),
            'totalLevelIncome' => collect($levels)->sum('total_income'),
        ]);
    }

    /**
     * The working behind one income line, in plain words with the real numbers. Self and sponsor income are a rate on
     * the BV of an order. Matching income is the level's rate on the volume that matched; that volume is worked back
     * from the amount paid (amount divided by the rate), because the rate and volume are not stored with the line.
     */
    private function explainIncome(string $type, string $description, float $amount, mixed $orderBv, ?string $orderCode, ?string $sourceName, bool $sourceIsMember): string
    {
        $n = fn (float $v) => number_format($v, $v == floor($v) ? 0 : 2);
        $money = fn (float $v) => '₹'.number_format($v, 2);
        $rates = Setting::getLevelPercentages();

        switch ($type) {
            case 'self':
                $pct = (float) Setting::get('self_pct', 10);

                return $orderBv !== null
                    ? "{$pct}% of {$n((float) $orderBv)} BV on your own order {$orderCode} = {$money($amount)}"
                    : "{$pct}% of the BV of your own order = {$money($amount)}";

            case 'sponsor':
                $pct = (float) Setting::get('sponsor_pct', 20);
                $who = $sourceName ? " bought by {$sourceName}" : '';

                return $orderBv !== null
                    ? "{$pct}% of {$n((float) $orderBv)} BV on order {$orderCode}{$who}, a person you sponsored = {$money($amount)}"
                    : "{$pct}% of the BV of an order bought by a person you sponsored = {$money($amount)}";

            case 'matching':
                preg_match('/level (\d+)/i', $description, $m);
                $level = (int) ($m[1] ?? 0);
                $pct = (float) ($rates[$level] ?? Setting::get('matching_pct', 10));
                $matched = $pct > 0 ? round($amount / ($pct / 100), 2) : 0.0;
                $root = str_starts_with($description, 'Root matching');
                $where = $sourceIsMember ? 'on your own two legs' : ($sourceName ? "on {$sourceName}'s two legs" : 'below you');
                $position = $root ? 'you hold the last slot as the root of the tree'
                    : ($level === 1 ? 'you are the one whose legs matched' : 'you are '.($level - 1).' '.($level - 1 === 1 ? 'step' : 'steps').' above them');

                return "{$pct}% (level {$level}) of {$n($matched)} BV matched {$where}, {$position} = {$money($amount)}";

            case 'rank':
                return "Your share of the rank pool = {$money($amount)}";
        }

        return $description;
    }
    /**
     * Everything the member needs to understand their income in one place: what was earned (by type, adding up to the
     * wallet), what was lost (income the company kept because they were not active at the time), and what is only
     * waiting (volume on the stronger leg that has no match yet).
     */
    private function incomeReport(Member $member, array $levels): array
    {
        $ledger = \App\Models\LedgerEntry::where('member_id', $member->id);
        $byType = (clone $ledger)->whereIn('type', ['self', 'sponsor', 'matching', 'rank'])->selectRaw('type, sum(amount) as s')->groupBy('type')->pluck('s', 'type');
        $matching = (float) ($byType['matching'] ?? 0);
        $ownMatching = (float) (clone $ledger)->where('type', 'matching')->where('source_member_id', $member->id)->sum('amount');
        $earned = (float) $byType->sum();

        $parts = [
            'self' => (float) ($byType['self'] ?? 0),
            'sponsor' => (float) ($byType['sponsor'] ?? 0),
            'matching_team' => round($matching - $ownMatching, 2),
            'matching_own' => $ownMatching,
            'rank' => (float) ($byType['rank'] ?? 0),
        ];

        $lostRows = collect();
        $lost = ['total' => 0.0, 'sponsor' => 0.0, 'matching' => 0.0, 'rows' => $lostRows];
        if (\App\Models\LockedIncome::ready()) {
            $all = \App\Models\LockedIncome::where('member_id', $member->id);
            $lost['sponsor'] = (float) (clone $all)->where('type', 'sponsor')->sum('amount');
            $lost['matching'] = (float) (clone $all)->where('type', 'matching')->sum('amount');
            $lost['total'] = $lost['sponsor'] + $lost['matching'];
            $lost['rows'] = (clone $all)->with('sourceMember:id,name,member_code', 'order:id,order_code,bv')->latest('id')->limit(25)->get();
        }

        $legs = $member->legTotal;
        $left = (float) ($legs->left_bv ?? 0);
        $right = (float) ($legs->right_bv ?? 0);
        $level1 = (float) (\App\Models\Setting::getLevelPercentages()[1] ?? \App\Models\Setting::get('matching_pct', 10));
        // Their own match waits while a member placed directly under them is not active: say who, and how much.
        $blocked = collect();
        $blockedAmount = 0.0;
        if (\App\Support\BusinessDay::ownMatchReady() && $member->status === 'active') {
            $pending = min($left, $right) - (float) ($legs->own_matched_bv ?? 0);
            $heads = Member::where('placement_id', $member->id)->whereIn('position', ['left', 'right'])->get();
            $inactive = $heads->where('status', '!=', 'active');
            if ($pending > 0 && $inactive->isNotEmpty()) {
                $blocked = $inactive->map(fn ($h) => ['name' => $h->name, 'side' => $h->position])->values();
                $blockedAmount = round($pending * $level1 / 100, 2);
            }
        }

        $waiting = [
            'blocked' => $blocked->all(),
            'blocked_amount' => $blockedAmount,
            'bv' => abs($left - $right),
            'strong_side' => $left > $right ? 'left' : ($right > $left ? 'right' : null),
            'weak_side' => $left > $right ? 'right' : ($right > $left ? 'left' : null),
            'left' => $left,
            'right' => $right,
            'matched' => (float) ($legs->matched_bv ?? 0),
            'per_1000' => round(1000 * $level1 / 100, 2),
        ];

        return $parts + [
            'earned' => $earned,
            'other' => round($earned - array_sum($parts), 2),
            'lost' => $lost,
            'waiting' => $waiting,
            'from_team_table' => (float) collect($levels)->sum('total_income'),
            'rules' => [
                'self' => (float) \App\Models\Setting::get('self_pct', 10),
                'sponsor' => (float) \App\Models\Setting::get('sponsor_pct', 20),
                'levels' => \App\Models\Setting::getLevelPercentages(),
            ],
        ];
    }
    /**
     * The people this member invited who have not paid yet, each with the step they are at, the plan they chose and a
     * ready-made WhatsApp reminder. They join the team the moment their payment is approved.
     */
    public function pending(): View
    {
        $member = Auth::guard('member')->user();
        $brand = \App\Support\Site::name();

        $people = Member::where('sponsor_id', $member->id)
            ->where('status', '!=', 'active')
            ->with(['interestedPlan', 'placementParent', 'orders.payment', 'orders.plan'])
            ->latest()
            ->get();

        // Which level below you, and which of your two legs, each person was placed in.
        $where = $this->network->locate($member, $people);

        $people->each(function (Member $m) use ($brand, $where, $member) {
            $m->stage = $m->activationStage();
            $plan = $m->orders->where('status', 'pending_payment')->first()?->plan ?? $m->interestedPlan;
            $m->planName = $plan?->name;
            $m->reminderUrl = \App\Support\Reminder::whatsappUrl($m, $plan?->name, $brand);
            $m->level = $where[$m->id]['level'] ?? null;
            $m->leg = ($where[$m->id]['leg'] ?? '') ?: null;
            $m->placedUnder = $m->placement_id === $member->id ? 'you' : $m->placementParent?->name;
        });

        return view('member.pending', ['people' => $people]);
    }
}
