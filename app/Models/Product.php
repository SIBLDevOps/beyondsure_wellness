<?php

namespace App\Models;

use App\Support\Gst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'sku', 'description', 'cost_price', 'sell_price', 'gst_pct',
        'benefit_group', 'usage_limit', 'usage_period', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'gst_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** This product's own GST rate, or the company-wide default when it doesn't have one set. */
    public function gstPct(): float
    {
        return $this->gst_pct !== null ? (float) $this->gst_pct : Gst::pct();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_products')->withPivot('qty');
    }

    public function usageLimitLabel(): ?string
    {
        if (! $this->usage_limit) {
            return null;
        }

        return "{$this->usage_limit} / {$this->usage_period}";
    }
}
