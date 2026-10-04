<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'status',
        'phone_verified_at',
        'email_verified_at',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_name',
        'upi_id',
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
