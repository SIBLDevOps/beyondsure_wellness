<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberWeeklyTotal;
use App\Models\Setting;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    public function index(): View
    {
        $member = Auth::guard('member')->user();

        $isoYear = (int) now()->format('o');
        $isoWeek = (int) now()->format('W');

        $weeklyMatched = (float) MemberWeeklyTotal::where('member_id', $member->id)
            ->where('iso_year', $isoYear)->where('iso_week', $isoWeek)->value('matched_total') ?? 0;

        $monthlyMatched = (float) $member->ledgerEntries()
            ->where('type', 'matching')
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
            ->sum('amount');

        $weeklyCap = (float) Setting::get('match_cap_per_week', 150000);
        $monthlyCap = (float) Setting::get('match_cap_per_cycle', 600000);

        return view('member.wallet', [
            'member' => $member,
            'balance' => $member->walletBalance(),
            'availableBalance' => $member->availableBalance(),
            'ledgerEntries' => $member->ledgerEntries()->latest()->limit(50)->get(),
            'withdrawals' => $member->withdrawals()->latest()->get(),
            'weeklyPct' => $weeklyCap > 0 ? round($weeklyMatched / $weeklyCap * 100, 1) : 0,
            'monthlyPct' => $monthlyCap > 0 ? round($monthlyMatched / $monthlyCap * 100, 1) : 0,
        ]);
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        $member = Auth::guard('member')->user();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ], [
            'amount.required' => 'Please enter a withdrawal amount.',
            'amount.numeric' => 'Withdrawal amount must be a number.',
            'amount.min' => 'Minimum withdrawal amount is ₹1.00.',
        ]);

        try {
            $this->withdrawals->request($member, (float) $data['amount']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Withdrawal requested — awaiting admin approval.');
    }

    public function updateBankDetails(Request $request): RedirectResponse
    {
        $member = Auth::guard('member')->user();

        if ($request->filled('upi_id')) {
            $request->merge(['upi_id' => strtolower(trim((string) $request->input('upi_id')))]);
        }
        if ($request->filled('bank_ifsc')) {
            $request->merge(['bank_ifsc' => strtoupper(trim((string) $request->input('bank_ifsc')))]);
        }
        if ($request->filled('bank_account_number')) {
            $request->merge(['bank_account_number' => trim((string) $request->input('bank_account_number'))]);
        }

        $data = $request->validate([
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'min:8', 'max:34', 'regex:/^[0-9]+$/'],
            'bank_ifsc' => ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'],
            'bank_name' => ['required', 'string', 'max:255'],
            'upi_id' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/'],
        ], [
            'bank_account_name.required' => 'Account holder name is required.',
            'bank_account_number.required' => 'Bank account number is required.',
            'bank_account_number.min' => 'Bank account number must be at least 8 digits.',
            'bank_account_number.regex' => 'Account number must contain digits only.',
            'bank_ifsc.required' => 'Bank IFSC code is required.',
            'bank_ifsc.size' => 'IFSC code must be exactly 11 characters.',
            'bank_ifsc.regex' => 'Enter a valid 11-character IFSC code (e.g. HDFC0001234).',
            'bank_name.required' => 'Bank name is required.',
            'upi_id.regex' => 'Please enter a valid UPI ID (e.g. yourname@okhdfcbank or 9876543210@upi).',
        ]);

        $data['bank_ifsc'] = strtoupper($data['bank_ifsc']);
        $member->update($data);

        return back()->with('status', 'Bank details saved.');
    }
}
