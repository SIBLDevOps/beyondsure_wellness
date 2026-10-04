<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Services\NetworkService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly NetworkService $network) {}

    public function index(): View
    {
        $member = Auth::guard('member')->user();

        $byType = LedgerEntry::where('member_id', $member->id)
            ->where('amount', '>', 0)
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('type')->get();

        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $trend = LedgerEntry::where('member_id', $member->id)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [now()->subMonths(5)->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("{$monthExpr} as month, SUM(amount) as total")
            ->groupBy('month')->orderBy('month')->get();

        $totalEarned = (float) $byType->sum('total');
        $totalWithdrawn = (float) abs($member->ledgerEntries()->where('amount', '<', 0)->sum('amount'));

        $orders = $member->orders()->whereIn('status', ['paid', 'settled'])->get();

        return view('member.reports', [
            'member' => $member,
            'byType' => $byType,
            'trend' => $trend,
            'totalEarned' => $totalEarned,
            'totalWithdrawn' => $totalWithdrawn,
            'walletBalance' => $member->walletBalance(),
            'orderCount' => $orders->count(),
            'totalSpent' => (float) $orders->sum('amount'),
            'subtreeSize' => $this->network->subtreeOf($member)->count(),
            'activeDirects' => $member->activeDirectSponsoredCount(),
            'legTotal' => $member->legTotal,
        ]);
    }
}
