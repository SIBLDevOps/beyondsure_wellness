<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use \App\Models\Concerns\Audited;

    /** The fields whose changes by staff are written to the audit log. */
    public function auditFields(): array
    {
        return ['name', 'slug', 'is_free'];
    }

    protected $fillable = ['name', 'slug', 'is_free'];

    protected function casts(): array
    {
        return ['is_free' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
