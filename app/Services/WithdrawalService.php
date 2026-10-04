<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    public function request(Member $member, float $amount): Withdrawal
    {
        if (! $member->hasBankDetails()) {
            throw new \RuntimeException('Add your bank details before requesting a withdrawal.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Withdrawal amount must be positive.');
        }

        // Lock the member row so two concurrent requests can't both read the same available
        // balance before either commits — otherwise both could pass validation against the same
        // money. availableBalance() already excludes every pending/approved request still
        // unpaid; without the lock, a race between two simultaneous requests could still slip
        // past it.
        return DB::transaction(function () use ($member, $amount) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($amount > $locked->availableBalance()) {
                throw new \RuntimeException('Withdrawal amount must not exceed your available wallet balance (pending and approved requests already reserve part of it).');
            }

            // Snapshot bank details at request time so a later profile edit never changes
            // what admin actually pays out for this specific request.
            return Withdrawal::create([
                'member_id' => $locked->id,
                'amount' => $amount,
                'status' => 'pending',
                'bank_account_name' => $locked->bank_account_name,
                'bank_account_number' => $locked->bank_account_number,
                'bank_ifsc' => $locked->bank_ifsc,
                'bank_name' => $locked->bank_name,
                'upi_id' => $locked->upi_id,
            ]);
        });
    }

    public function approve(Withdrawal $withdrawal, User $admin): void
    {
        $withdrawal->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(Withdrawal $withdrawal, User $admin): void
    {
        $withdrawal->update([
            'status' => 'rejected',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * The moment money actually leaves: debits the member's wallet ledger and marks the
     * withdrawal paid. Requires a payout reference — called directly for a single
     * withdrawal, or by DispatchService when a batch is released.
     */
    public function markPaid(Withdrawal $withdrawal, string $payoutReference): void
    {
        if ($withdrawal->status !== 'approved') {
            throw new \RuntimeException('Only an approved withdrawal can be marked paid.');
        }

        if (trim($payoutReference) === '') {
            throw new \RuntimeException('A payout reference is required to mark a withdrawal paid.');
        }

        DB::transaction(function () use ($withdrawal, $payoutReference) {
            $withdrawal->update([
                'status' => 'paid',
                'payout_reference' => $payoutReference,
                'paid_at' => now(),
            ]);

            LedgerEntry::create([
                'member_id' => $withdrawal->member_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => 'withdrawal',
                'amount' => -abs((float) $withdrawal->amount),
                'description' => "Withdrawal paid — ref {$payoutReference}",
            ]);
        });
    }
}
