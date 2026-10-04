<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'memberCount' => Member::count(),
            'activeMemberCount' => Member::where('status', 'active')->count(),
            'pendingPayments' => Payment::where('status', 'pending')->count(),
            'unsettledOrders' => Order::where('status', 'paid')->whereNull('cycle_id')->count(),
            'withdrawalsToProcess' => Withdrawal::where('status', 'approved')
                ->whereDoesntHave('batches', fn ($q) => $q->where('status', 'draft'))
                ->count(),
            'pendingWithdrawals' => Withdrawal::where('status', 'pending')->count(),

            'recentOrders' => Order::with('member', 'plan')->latest()->limit(6)->get(),
            'recentPayments' => Payment::where('status', 'pending')->with('order.member')->latest()->limit(6)->get(),
            'recentWithdrawals' => Withdrawal::where('status', '!=', 'rejected')->with('member')->latest()->limit(6)->get(),
            'todayIncome' => (float) LedgerEntry::where('amount', '>', 0)->whereDate('created_at', today())->sum('amount'),
            'monthIncome' => (float) LedgerEntry::where('amount', '>', 0)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
        ]);
    }
}
