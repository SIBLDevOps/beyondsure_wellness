<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/** Income a member missed because they had not bought a plan. Shown to them, never paid. */
class LockedIncome extends Model
{
    protected $fillable = ['member_id', 'source_member_id', 'cycle_id', 'order_id', 'type', 'level', 'amount', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sourceMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'source_member_id');
    }

    /** False until the migration has been run, so nothing breaks before then. */
    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('locked_incomes');
    }
}
