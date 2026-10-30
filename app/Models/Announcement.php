<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something the company tells its members: shown in the member area and, if asked, emailed. */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'region_id', 'audience', 'send_email', 'emailed_count', 'created_by'];

    protected function casts(): array
    {
        return ['send_email' => 'boolean'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** Announcements this member should see: for everyone or their own region, and not an active-only one while unpaid. */
    public function scopeVisibleTo($query, Member $member)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('region_id')->orWhere('region_id', $member->region_id))
            ->when(! $member->hasBought(), fn ($q) => $q->where('audience', 'all'));
    }
}
