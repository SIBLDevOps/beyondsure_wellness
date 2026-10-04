<?php

namespace App\Services;

use App\Models\DispatchBatch;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    /** Pulls in every approved withdrawal not already in an open (draft) batch. */
    public function createBatch(string $scheduledFor, User $admin): DispatchBatch
    {
        return DispatchBatch::create([
            'scheduled_for' => $scheduledFor,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
    }

    public function availableWithdrawals(): Collection
    {
        return Withdrawal::where('status', 'approved')
            ->whereDoesntHave('batches', fn ($q) => $q->where('status', 'draft'))
            ->with('member')
            ->get();
    }

    public function addItem(DispatchBatch $batch, Withdrawal $withdrawal): void
    {
        if (! $batch->isDraft()) {
            throw new \RuntimeException('Cannot modify a released batch.');
        }

        if ($withdrawal->status !== 'approved') {
            throw new \RuntimeException('Only approved withdrawals can be added to a dispatch batch.');
        }

        $batch->withdrawals()->syncWithoutDetaching([$withdrawal->id]);
        $this->recalculateTotals($batch);
    }

    public function removeItem(DispatchBatch $batch, Withdrawal $withdrawal): void
    {
        if (! $batch->isDraft()) {
            throw new \RuntimeException('Cannot modify a released batch.');
        }

        $batch->withdrawals()->detach($withdrawal->id);
        $this->recalculateTotals($batch);
    }

    private function recalculateTotals(DispatchBatch $batch): void
    {
        $withdrawals = $batch->withdrawals()->get();

        $batch->update([
            'total_amount' => $withdrawals->sum('amount'),
            'withdrawal_count' => $withdrawals->count(),
        ]);
    }

    /**
     * This is the moment admin decides money actually moves. Every withdrawal in the
     * batch must already carry a payout reference — release does not proceed otherwise.
     */
    public function release(DispatchBatch $batch, User $admin, array $referencesByWithdrawalId): void
    {
        if (! $batch->isDraft()) {
            throw new \RuntimeException('This batch has already been released.');
        }

        $withdrawals = $batch->withdrawals()->get();

        if ($withdrawals->isEmpty()) {
            throw new \RuntimeException('Cannot release an empty batch.');
        }

        foreach ($withdrawals as $withdrawal) {
            if (trim($referencesByWithdrawalId[$withdrawal->id] ?? '') === '') {
                throw new \RuntimeException("Withdrawal #{$withdrawal->id} is missing a payout reference.");
            }
        }

        DB::transaction(function () use ($batch, $withdrawals, $admin, $referencesByWithdrawalId) {
            foreach ($withdrawals as $withdrawal) {
                $this->withdrawals->markPaid($withdrawal, $referencesByWithdrawalId[$withdrawal->id]);
            }

            $batch->update([
                'status' => 'released',
                'released_by' => $admin->id,
                'released_at' => now(),
            ]);
        });
    }
}
