<?php

namespace App\Models;

use App\Support\Gst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'bv', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'bv' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'plan_products')->withPivot('qty');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Total cost of goods for this bundle: sum(product.cost_price * qty).
     * Used against the settings-driven cost cap — a plan's cost share must not exceed target.
     */
    public function costForQty(): float
    {
        return $this->products->sum(fn (Product $p) => (float) $p->cost_price * (int) $p->pivot->qty);
    }

    private ?array $gstSplitCache = null;

    /** @return array{taxable: float, gst: float, total: float} */
    private function gstSplit(): array
    {
        return $this->gstSplitCache ??= Gst::splitForPlan($this, (float) $this->price);
    }

    /** Blended effective rate implied by this bundle's own product mix — for display only. */
    public function gstPct(): float
    {
        return $this->price > 0 ? round($this->gstAmount() / $this->price * 100, 2) : Gst::pct();
    }

    /** This plan's price IS the taxable base (the v2 model's "Package Tiers, MRP excl. GST"). */
    public function netOfGst(): float
    {
        return (float) $this->price;
    }

    public function gstAmount(): float
    {
        return $this->gstSplit()['gst'];
    }

    /** What a member actually pays: base price + GST. */
    public function totalPayable(): float
    {
        return $this->gstSplit()['total'];
    }
}
