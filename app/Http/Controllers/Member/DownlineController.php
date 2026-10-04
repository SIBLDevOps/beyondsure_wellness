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

    /**
     * Deliberately takes no {member} route parameter — always resolves to the logged-in
     * member from the session, so a member can never view another member's subtree by
     * guessing an ID in the URL (Phase 2 §3.2).
     */
    public function index(Request $request): View
    {
        $member = Auth::guard('member')->user();
        $depth = (int) $request->input('depth', 6);
        $depth = max(2, min(6, $depth));

        return view('member.downline', [
            'member' => $member,
            'tree' => $this->network->buildTree($member, $depth),
            'depth' => $depth,
            'legTotal' => $member->legTotal,
            'levelBreakdown' => $this->network->levelBreakdown($member, $depth),
        ]);
    }

    public function team(): View
    {
        $member = Auth::guard('member')->user();
        $nowMonth = now()->month;
        $nowYear = now()->year;

        $directs = Member::where('sponsor_id', $member->id)
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
        $levels = $this->network->levelBreakdown($member, $depth);

        return view('member.levels', [
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
}
