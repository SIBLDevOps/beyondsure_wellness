<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberWeeklyTotal extends Model
{
    protected $fillable = ['member_id', 'iso_year', 'iso_week', 'matched_total'];

    protected function casts(): array
    {
        return ['matched_total' => 'decimal:2'];
    }
}
