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
        $withdrawal = DB::transaction(function () use ($member, $amount) {
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

        app(MemberMailer::class)->withdrawalRequested($withdrawal);

        return $withdrawal;
    }

    /** Only a pending request can be approved. Approving anything else (paid, rejected) would rewind its history. */
    public function approve(Withdrawal $withdrawal, User $admin): void
    {
        $this->transition($withdrawal, ['pending'], 'approved', 'Only a pending withdrawal can be approved.', [
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    /** A pending or approved (not yet paid) request can be rejected. Once paid, the money has left and it stays paid. */
    public function reject(Withdrawal $withdrawal, User $admin): void
    {
        $this->transition($withdrawal, ['pending', 'approved'], 'rejected', 'Only a pending or approved withdrawal can be rejected. A paid one cannot be undone here.', [
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Moves a withdrawal to a new status only from the allowed ones. The row is re-read under a lock, so a
     * repeated or simultaneous request sees the current status and cannot slip through on stale data.
     */
    private function transition(Withdrawal $withdrawal, array $from, string $to, string $error, array $extra = []): void
    {
        DB::transaction(function () use ($withdrawal, $from, $to, $error, $extra) {
            $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $from, true)) {
                throw new \RuntimeException($error);
            }

            $locked->update(['status' => $to] + $extra);
            $withdrawal->setRawAttributes($locked->fresh()->getAttributes(), true);
        });

        app(MemberMailer::class)->withdrawalDecision($withdrawal);
    }

    /**
     * The moment money actually leaves: debits the member's wallet ledger and marks the
     * withdrawal paid. Requires a payout reference — called directly for a single
     * withdrawal, or by DispatchService when a batch is released.
     */
    public function markPaid(Withdrawal $withdrawal, string $payoutReference): void
    {
        if (trim($payoutReference) === '') {
            throw new \RuntimeException('A payout reference is required to mark a withdrawal paid.');
        }

        DB::transaction(function () use ($withdrawal, $payoutReference) {
            // Re-check under a lock so the same withdrawal can never be paid (and debited) twice.
            $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw new \RuntimeException('Only an approved withdrawal can be marked paid.');
            }

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

        app(MemberMailer::class)->withdrawalDecision($withdrawal->fresh());
    }
}
