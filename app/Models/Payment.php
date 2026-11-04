<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[\Illuminate\Database\Eloquent\Attributes\ScopedBy([\App\Models\Scopes\AdminRegionScope::class])]
class Payment extends Model
{
    use \App\Models\Concerns\Audited;

    /** The fields whose changes by staff are written to the audit log. */
    public function auditFields(): array
    {
        return ['status', 'method'];
    }

    protected $fillable = ['order_id', 'method', 'reference', 'status', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
