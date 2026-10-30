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
        return $this->attempt(fn () => $this->withdrawals->approve($withdrawal, Auth::user()), 'Withdrawal approved.');
    }

    public function reject(Withdrawal $withdrawal): RedirectResponse
    {
        return $this->attempt(fn () => $this->withdrawals->reject($withdrawal, Auth::user()), 'Withdrawal rejected.');
    }

    public function markPaid(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $data = $request->validate(['payout_reference' => ['required', 'string', 'max:255']]);

        return $this->attempt(fn () => $this->withdrawals->markPaid($withdrawal, $data['payout_reference']), 'Withdrawal marked paid.');
    }

    /** Runs a status change and turns a refused one (wrong current status) into a clear message instead of an error page. */
    private function attempt(callable $change, string $success): RedirectResponse
    {
        try {
            $change();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
