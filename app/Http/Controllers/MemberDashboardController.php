<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MemberDashboardController extends Controller
{
    public function index(): View
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();
        $member->load(['sponsor', 'placementParent', 'legTotal']);

        $earningsByType = $member->ledgerEntries()
            ->where('amount', '>', 0)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $directIncome = (float) ($earningsByType['sponsor'] ?? 0);
        $matchingIncome = (float) ($earningsByType['matching'] ?? 0);
        $selfIncome = (float) ($earningsByType['self'] ?? 0);
        $rankIncome = (float) ($earningsByType['rank'] ?? 0);
        $totalEarned = (float) $member->ledgerEntries()->where('amount', '>', 0)->sum('amount');

        return view('member.dashboard', [
            'member' => $member,
            'walletBalance' => $member->walletBalance(),
            'totalEarned' => $totalEarned,
            'directIncome' => $directIncome,
            'matchingIncome' => $matchingIncome,
            'selfIncome' => $selfIncome,
            'rankIncome' => $rankIncome,
            'earningsByType' => $earningsByType,
            'totalWithdrawn' => (float) $member->withdrawals()->where('status', 'paid')->sum('amount'),
            'personalBv' => (float) $member->orders()->whereIn('status', ['paid', 'settled'])->sum('bv'),
            'orderCount' => $member->orders()->whereIn('status', ['paid', 'settled'])->count(),
            'pendingOrderCount' => $member->orders()->where('status', 'pending_payment')->count(),
            'directCount' => $member->sponsoredMembers()->count(),
            'activeDirectCount' => $member->sponsoredMembers()->where('status', 'active')->count(),
            'legTotal' => $member->legTotal,
            'recentOrders' => $member->orders()->with('plan')->latest()->limit(5)->get(),
            'recentLedger' => $member->ledgerEntries()->latest()->limit(8)->get(),
        ]);
    }
}
