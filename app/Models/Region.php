<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A region is a self-contained market. It owns one binary tree (every member's sponsor and
 * placement stay inside it) and its own plan catalogue. A mobile number can belong to one region only.
 */
#[\Illuminate\Database\Eloquent\Attributes\ScopedBy([\App\Models\Scopes\AdminRegionScope::class])]
class Region extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /** The top of this region's tree: its first member, who has no placement parent. */
    public function root(): ?Member
    {
        return $this->members()->whereNull('placement_id')->orderBy('id')->first();
    }

    public function registerUrl(): string
    {
        return route('register', ['region' => $this->code]);
    }

    /** Id of the starting region, created on the fly if none exists yet (fresh installs, tests). */
    public static function fallbackId(): int
    {
        return (self::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->value('id'))
            ?? self::create(['name' => 'Main Region', 'code' => 'MAIN', 'sort_order' => 1])->id;
    }
}
