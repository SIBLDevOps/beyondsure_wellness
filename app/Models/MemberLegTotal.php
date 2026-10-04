<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLegTotal extends Model
{
    protected $primaryKey = 'member_id';

    public $incrementing = false;

    protected $fillable = ['member_id', 'left_bv', 'right_bv', 'matched_bv'];

    protected function casts(): array
    {
        return [
            'left_bv' => 'decimal:2',
            'right_bv' => 'decimal:2',
            'matched_bv' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function unmatchedLeft(): float
    {
        return max(0, (float) $this->left_bv - (float) $this->matched_bv);
    }

    public function unmatchedRight(): float
    {
        return max(0, (float) $this->right_bv - (float) $this->matched_bv);
    }
}
