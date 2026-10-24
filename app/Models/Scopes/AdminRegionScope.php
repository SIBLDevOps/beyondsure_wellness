<?php

namespace App\Models\Scopes;

use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Region;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits what a region-restricted sub-admin can see inside the admin console. It is attached to the
 * models that belong to a region (directly or through their member), so every list, count, report
 * and route-model binding is narrowed in one place and nothing can be forgotten page by page. It does
 * nothing for the Super Admin, for unrestricted sub-admins, or outside admin pages (member portal,
 * registration, CLI, queued jobs).
 */
class AdminRegionScope implements Scope
{
    /** @return list<int>|null null = no restriction */
    public static function ids(): ?array
    {
        $request = app()->bound('request') ? request() : null;

        if (! $request || ! $request->routeIs('admin.*')) {
            return null;
        }

        return auth('web')->user()?->regionScope();
    }

    public function apply(Builder $builder, Model $model): void
    {
        $ids = self::ids();

        if ($ids === null) {
            return;
        }

        match (true) {
            $model instanceof Region => $builder->whereIn($model->qualifyColumn('id'), $ids),
            $model instanceof Member, $model instanceof Plan => $builder->whereIn($model->qualifyColumn('region_id'), $ids),
            $model instanceof Order, $model instanceof LedgerEntry, $model instanceof Withdrawal => $builder->whereHas('member'),
            $model instanceof Payment => $builder->whereHas('order'),
            default => null,
        };
    }
}
