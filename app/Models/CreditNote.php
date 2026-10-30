<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A numbered document that reverses the invoice of a paid order that was cancelled or refunded. */
class CreditNote extends Model
{
    /** How the customer's money was sent back. */
    public const METHODS = ['upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'original' => 'Original payment method (gateway)'];

    protected $fillable = ['order_id', 'number', 'issued_on', 'reason', 'taxable_amount', 'gst_amount', 'amount', 'refund_method', 'refund_reference', 'refunded_on', 'created_by'];

    public function methodLabel(): string
    {
        return self::METHODS[$this->refund_method] ?? '—';
    }

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'refunded_on' => 'date', 'taxable_amount' => 'decimal:2', 'gst_amount' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
