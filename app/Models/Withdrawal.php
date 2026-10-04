<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Withdrawal extends Model
{
    protected $fillable = [
        'member_id', 'amount', 'status', 'payout_reference',
        'approved_by', 'approved_at', 'paid_at',
        'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name', 'upi_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(DispatchBatch::class, 'dispatch_batch_items');
    }

    public function inOpenBatch(): bool
    {
        return $this->batches()->where('status', 'draft')->exists();
    }
}
