<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $fillable = ['name', 'sort_order', 'matched_bv_threshold', 'min_active_directs', 'share'];

    protected function casts(): array
    {
        return [
            'matched_bv_threshold' => 'decimal:2',
        ];
    }
}
