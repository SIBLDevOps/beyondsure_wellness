<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    use \App\Models\Concerns\Audited;

    /** The fields whose changes by staff are written to the audit log. */
    public function auditFields(): array
    {
        return ['name', 'sort_order', 'matched_bv_threshold', 'min_active_directs', 'share'];
    }

    protected $fillable = ['name', 'sort_order', 'matched_bv_threshold', 'min_active_directs', 'share'];

    protected function casts(): array
    {
        return [
            'matched_bv_threshold' => 'decimal:2',
        ];
    }
}
