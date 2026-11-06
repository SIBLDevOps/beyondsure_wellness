<?php

namespace App\Support;

use App\Models\Region;
use Illuminate\Support\Str;

/**
 * The class and name of every report download, so a file can be told apart without opening it:
 * company-class-report[-region]-date.csv, for example acme-sales-top-buyers-gujarat-20261101.csv.
 * Classes: sales, payouts, compliance, tax. A new report download must be added here (a test checks it).
 */
class ReportNames
{
    /** route name => [class, report] */
    public const MAP = [
        'admin.reports.overview.trend.csv' => ['sales', 'overview-trend'],
        'admin.reports.sales.csv' => ['sales', 'by-plan'],
        'admin.reports.sales.top-buyers.csv' => ['sales', 'top-buyers'],
        'admin.reports.sales.purchases.csv' => ['sales', 'purchases'],
        'admin.reports.repurchase.csv' => ['sales', 'repurchase'],
        'admin.reports.repurchase.orders.csv' => ['sales', 'repurchase-orders'],
        'admin.reports.repurchase.buyers.csv' => ['sales', 'repurchase-buyers'],
        'admin.reports.payouts.csv' => ['payouts', 'by-cycle'],
        'admin.reports.payouts.by-type.csv' => ['payouts', 'by-type'],
        'admin.reports.payouts.top-earners.csv' => ['payouts', 'top-earners'],
        'admin.reports.levels.csv' => ['payouts', 'level-income'],
        'admin.reports.levels.earners.csv' => ['payouts', 'level-earners'],
        'admin.reports.distribution.csv' => ['payouts', 'money-distribution'],
        'admin.reports.cascade.csv' => ['payouts', 'cascade-7th-level'],
        'admin.reports.pending.csv' => ['compliance', 'pending-cash'],
        'admin.reports.pending.withdrawals.csv' => ['compliance', 'pending-withdrawals'],
        'admin.reports.pending.unsettled.csv' => ['compliance', 'unsettled-orders'],
        'admin.reports.compliance.csv' => ['compliance', 'distribution'],
        'admin.reports.compliance.overpaid.csv' => ['compliance', 'overpaid-cycles'],
        'admin.reports.compliance.cost-cap.csv' => ['compliance', 'cost-cap'],
        'admin.reports.gst.csv' => ['tax', 'gst-sales'],
    ];

    /** The class names shown to people, in the order the report tabs use them. */
    public const CLASSES = ['sales' => 'Sales', 'payouts' => 'Payouts', 'compliance' => 'Compliance', 'tax' => 'Tax'];

    /** @return array{0: string, 1: string}|null */
    public static function for(?string $route): ?array
    {
        return self::MAP[(string) $route] ?? null;
    }

    /** The file name for a download. $period replaces the date (for monthly reports, e.g. "2026-10"). */
    public static function fileName(?string $route, ?int $regionId = null, ?string $period = null): string
    {
        $parts = [Str::slug(Site::name()) ?: 'report'];

        if ($named = self::for($route)) {
            array_push($parts, ...$named);
        } else {
            $parts[] = 'report';
        }

        if ($regionId && ($region = Region::withoutGlobalScopes()->find($regionId))) {
            $parts[] = Str::slug($region->name) ?: 'region-'.$regionId;
        }

        $parts[] = $period ?: now()->format('Ymd');

        return implode('-', $parts).'.csv';
    }
}
