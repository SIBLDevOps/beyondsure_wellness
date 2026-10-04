<?php

namespace App\Models;

use App\Support\Gst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_code', 'member_id', 'plan_id', 'amount', 'taxable_amount', 'gst_amount', 'bv', 'status', 'is_repurchase', 'cycle_id',
        'billing_name', 'billing_email', 'billing_phone', 'billing_address', 'razorpay_order_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'bv' => 'decimal:2',
            'is_repurchase' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Taxable value and GST — snapshotted at order creation (OrderService::place()), never
     * recomputed. A product's GST rate can change after the sale; this order's own tax breakdown
     * must not. Rows created before this column existed have no snapshot — treated as GST-free
     * rather than guessed at, since there's no way to know what rate applied back then.
     */
    public function netOfGst(): float
    {
        return $this->taxable_amount !== null ? (float) $this->taxable_amount : (float) $this->amount;
    }

    public function gstAmount(): float
    {
        return $this->gst_amount !== null ? (float) $this->gst_amount : 0.0;
    }

    /** Blended effective rate implied by this order's actual snapshotted split — for display only. */
    public function gstPct(): float
    {
        $taxable = $this->netOfGst();

        return $taxable > 0 ? round($this->gstAmount() / $taxable * 100, 2) : Gst::pct();
    }
}
