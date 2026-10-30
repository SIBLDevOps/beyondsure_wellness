<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[\Illuminate\Database\Eloquent\Attributes\ScopedBy([\App\Models\Scopes\AdminRegionScope::class])]
class Member extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'member_code',
        'name',
        'email',
        'phone',
        'password',
        'sponsor_id',
        'placement_id',
        'position',
        'path',
        'rank',
        'region_id',
        'interested_plan_id',
        'status',
        'phone_verified_at',
        'email_verified_at',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_name',
        'upi_id',
        'email_opt_out',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** A member always lives in exactly one region: their sponsor's, else their parent's, else the starting one. */
    protected static function booted(): void
    {
        static::creating(function (Member $m) {
            $m->region_id ??= Member::query()->whereKey($m->sponsor_id ?? $m->placement_id)->value('region_id')
                ?? Region::fallbackId();
        });
    }

    /** The plan this person said they wanted when they registered (may be empty for people added another way). */
    public function interestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'interested_plan_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_id');
    }

    public function sponsoredMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'sponsor_id');
    }

    public function placementParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'placement_id');
    }

    public function placementChildren(): HasMany
    {
        return $this->hasMany(Member::class, 'placement_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function legTotal(): HasOne
    {
        return $this->hasOne(MemberLegTotal::class);
    }

    public function walletBalance(): float
    {
        return (float) $this->ledgerEntries()->sum('amount');
    }

    /**
     * Balance actually free to request right now: the ledger balance minus every withdrawal
     * that already claims part of it but hasn't been paid or rejected yet. A withdrawal only
     * debits the ledger at payout time (WithdrawalService::markPaid), so without this, the same
     * balance could be requested multiple times over before any of them are paid.
     */
    public function availableBalance(): float
    {
        $reserved = (float) $this->withdrawals()->whereIn('status', ['pending', 'approved'])->sum('amount');

        return $this->walletBalance() - $reserved;
    }

    /**
     * Where this person is on the way to becoming an active member:
     *   registered         signed up, no open order
     *   order_placed      an order is waiting for payment
     *   payment_submitted they sent a payment and it is waiting for approval
     *   active            a payment was approved: they now count as downline
     */
    /**
     * The same three steps as activationStage(), but as a database filter (for lists too long to load whole).
     * Only meaningful for people who are not active yet.
     */
    public function scopeAtStage(Builder $query, string $stage): Builder
    {
        $awaitingPayment = fn (Builder $o) => $o->where('status', 'pending_payment');
        $paymentSent = fn (Builder $o) => $o->where('status', 'pending_payment')
            ->whereHas('payment', fn (Builder $p) => $p->where('status', 'pending'));

        return match ($stage) {
            'registered' => $query->whereDoesntHave('orders', $awaitingPayment),
            'payment_submitted' => $query->whereHas('orders', $paymentSent),
            'order_placed' => $query->whereHas('orders', $awaitingPayment)->whereDoesntHave('orders', $paymentSent),
            default => $query,
        };
    }

    /** True once a payment of theirs was approved. Until then the portal only shows the plans to buy. */
    public function hasBought(): bool
    {
        return $this->status === 'active';
    }

    public function activationStage(): string
    {
        if ($this->status === 'active') {
            return 'active';
        }

        $orders = $this->relationLoaded('orders') ? $this->orders : $this->orders()->get();
        $open = $orders->where('status', 'pending_payment');

        if ($open->isEmpty()) {
            return 'registered';
        }

        foreach ($open as $order) {
            $payment = $order->relationLoaded('payment') ? $order->payment : $order->payment()->first();
            if ($payment?->status === 'pending') {
                return 'payment_submitted';
            }
        }

        return 'order_placed';
    }

    /** Active this month = at least one own order placed in the current calendar month. */
    public function isActiveThisMonth(): bool
    {
        return $this->orders()
            ->whereIn('status', ['paid', 'settled'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->exists();
    }

    public function activeDirectSponsoredCount(): int
    {
        return $this->sponsoredMembers()
            ->where('status', 'active')
            ->get()
            ->filter(fn (Member $m) => $m->isActiveThisMonth())
            ->count();
    }

    public function hasBankDetails(): bool
    {
        return filled($this->bank_account_number) && filled($this->bank_ifsc) && filled($this->bank_account_name);
    }
}
