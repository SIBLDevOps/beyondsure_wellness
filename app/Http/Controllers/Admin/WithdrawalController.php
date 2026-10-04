<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    public function index(): View
    {
        return view('admin.withdrawals.index', [
            'withdrawals' => Withdrawal::with('member')->latest()->paginate(25),
        ]);
    }

    public function approve(Withdrawal $withdrawal): RedirectResponse
    {
        $this->withdrawals->approve($withdrawal, Auth::user());

        return back()->with('status', 'Withdrawal approved.');
    }

    public function reject(Withdrawal $withdrawal): RedirectResponse
    {
        $this->withdrawals->reject($withdrawal, Auth::user());

        return back()->with('status', 'Withdrawal rejected.');
    }

    public function markPaid(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $data = $request->validate(['payout_reference' => ['required', 'string', 'max:255']]);

        $this->withdrawals->markPaid($withdrawal, $data['payout_reference']);

        return back()->with('status', 'Withdrawal marked paid.');
    }
}
