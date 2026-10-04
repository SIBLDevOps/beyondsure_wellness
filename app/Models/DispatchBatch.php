<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DispatchBatch extends Model
{
    protected $fillable = [
        'scheduled_for', 'status', 'total_amount', 'withdrawal_count',
        'created_by', 'released_by', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'total_amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function withdrawals(): BelongsToMany
    {
        return $this->belongsToMany(Withdrawal::class, 'dispatch_batch_items');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
