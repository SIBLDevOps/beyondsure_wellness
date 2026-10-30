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
            // Announcements need their table; until the database update has been run the dashboard simply shows none.
            'announcements' => \Illuminate\Support\Facades\Schema::hasTable('announcements') ? \App\Models\Announcement::visibleTo($member)->latest()->limit(3)->get() : collect(),
            'onboarding' => app(\App\Services\OnboardingService::class)->progress($member),
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
            // Only people whose payment was approved are real directs; the rest are "pending" until they pay.
            'directCount' => $member->sponsoredMembers()->where('status', 'active')->count(),
            'pendingCount' => $member->sponsoredMembers()->where('status', '!=', 'active')->count(),
            'activeDirectCount' => $member->sponsoredMembers()->where('status', 'active')->count(),
            // Plans the sponsor can pick for the person they invite: this region's active plans only.
            'invitePlans' => \App\Models\Plan::where('region_id', $member->region_id)->where('is_active', true)->orderBy('price')->get(),
            'legTotal' => $member->legTotal,
            'recentOrders' => $member->orders()->with('plan')->latest()->limit(5)->get(),
            'recentLedger' => $member->ledgerEntries()->latest()->limit(8)->get(),
        ]);
    }
}
