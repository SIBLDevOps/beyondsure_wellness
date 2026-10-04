<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Setting;
use App\Services\NetworkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function __construct(private readonly NetworkService $network) {}

    public function index(Request $request): View
    {
        $nowMonth = now()->month;
        $nowYear = now()->year;

        $members = Member::query()
            ->with(['sponsor', 'placementParent', 'legTotal'])
            ->withCount('sponsoredMembers')
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->whereIn('status', ['paid', 'settled'])])
            ->withSum(['orders as personal_bv' => fn ($q) => $q->whereIn('status', ['paid', 'settled'])], 'bv')
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereIn('status', ['paid', 'settled'])], 'amount')
            ->withSum('ledgerEntries as wallet_balance', 'amount')
            ->withExists(['orders as active_this_month' => fn ($q) => $q
                ->whereIn('status', ['paid', 'settled'])
                ->whereMonth('created_at', $nowMonth)
                ->whereYear('created_at', $nowYear)])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('member_code', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->rank, fn ($q, $rank) => $q->where('rank', strtolower($rank)))
            ->when($request->activity === 'active_month', fn ($q) => $q->whereHas('orders', fn ($oq) => $oq
                ->whereIn('status', ['paid', 'settled'])
                ->whereMonth('created_at', $nowMonth)
                ->whereYear('created_at', $nowYear)))
            ->when($request->activity === 'inactive_month', fn ($q) => $q->whereDoesntHave('orders', fn ($oq) => $oq
                ->whereIn('status', ['paid', 'settled'])
                ->whereMonth('created_at', $nowMonth)
                ->whereYear('created_at', $nowYear)))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => Member::count(),
            'active' => Member::where('status', 'active')->count(),
            'inactive' => Member::where('status', '!=', 'active')->count(),
            'active_this_month' => Member::whereHas('orders', fn ($q) => $q
                ->whereIn('status', ['paid', 'settled'])
                ->whereMonth('created_at', $nowMonth)
                ->whereYear('created_at', $nowYear))->count(),
            'ranked' => Member::whereNotNull('rank')->where('rank', '!=', 'none')->count(),
        ];

        return view('admin.members.index', compact('members', 'stats'));
    }

    public function show(Member $member): View
    {
        $member->loadMissing(['sponsor', 'placementParent', 'legTotal']);

        $incomeByType = LedgerEntry::where('member_id', $member->id)
            ->whereIn('type', ['self', 'sponsor', 'matching', 'rank'])
            ->selectRaw('type, COUNT(*) as entries, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $totalEarned = (float) LedgerEntry::where('member_id', $member->id)
            ->whereIn('type', ['self', 'sponsor', 'matching', 'rank'])
            ->sum('amount');

        $personalBv = (float) $member->orders()->whereIn('status', ['paid', 'settled'])->sum('bv');
        $totalSpent = (float) $member->orders()->whereIn('status', ['paid', 'settled'])->sum('amount');
        $subtreeSize = $this->network->subtreeOf($member, includeRoot: false)->count();

        return view('admin.members.show', [
            'member' => $member,
            'walletBalance' => $member->walletBalance(),
            'totalEarned' => $totalEarned,
            'incomeByType' => $incomeByType,
            'personalBv' => $personalBv,
            'totalSpent' => $totalSpent,
            'subtreeSize' => $subtreeSize,
            'directReferrals' => $member->sponsoredMembers()->with('legTotal')->latest()->get(),
            'orders' => $member->orders()->with('plan')->latest()->limit(15)->get(),
            'recentLedger' => $member->ledgerEntries()->with(['sourceMember', 'order'])->latest()->limit(15)->get(),
        ]);
    }

    public function network(Request $request, Member $member): View
    {
        $depth = (int) $request->input('depth', 6);
        $depth = max(2, min(6, $depth));

        $subtreeIds = $this->network->subtreeOf($member, includeRoot: true)->pluck('id');

        return view('admin.members.network', [
            'member' => $member,
            'tree' => $this->network->buildTree($member, $depth),
            'depth' => $depth,
            'sponsorChain' => $this->network->sponsorChain($member),
            'placementChain' => $this->network->placementChain($member),
            'subtreeSize' => $subtreeIds->count(),
            'activeCount' => Member::whereIn('id', $subtreeIds)->get()->filter(fn (Member $m) => $m->isActiveThisMonth())->count(),
            'levelBreakdown' => $this->network->levelBreakdown($member, $depth),
            'legTotal' => $member->legTotal,
            'subtreeOrders' => Order::whereIn('member_id', $subtreeIds)->with('member', 'plan')->latest()->limit(50)->get(),
            'sourcedLedger' => LedgerEntry::where('member_id', $member->id)
                ->whereIn('source_member_id', $subtreeIds)
                ->with('sourceMember')->latest()->limit(50)->get(),
        ]);
    }

    public function levels(Request $request, Member $member): View
    {
        $depth = (int) $request->input('depth', 7);
        $depth = max(2, min(12, $depth));
        $cascadeDepth = (int) Setting::get('cascade_depth', 7);
        $levels = $this->network->levelBreakdown($member, $depth);

        $totalMatching = collect($levels)->sum('matching_income');
        $totalSponsor = collect($levels)->sum('sponsor_income');
        $totalLevelIncome = collect($levels)->sum('total_income');

        return view('admin.members.levels', [
            'member' => $member,
            'levels' => $levels,
            'cascadeDepth' => $cascadeDepth,
            'depth' => $depth,
            'levelPcts' => Setting::getLevelPercentages(),
            'matchingPct' => (float) Setting::get('matching_pct', 10),
            'sponsorPct' => (float) Setting::get('sponsor_pct', 20),
            'selfPct' => (float) Setting::get('self_pct', 10),
            'totalMatching' => $totalMatching,
            'totalSponsor' => $totalSponsor,
            'totalLevelIncome' => $totalLevelIncome,
        ]);
    }
}
