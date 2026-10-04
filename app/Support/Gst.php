<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Setting;

/**
 * Per the v2 model (Settings §0 — Tax Assumption, and the "Package Tiers, MRP excl. GST" table):
 * a plan's price is the TAXABLE value, and GST is added ON TOP of it to get what the member
 * actually pays. This is the opposite of treating price as tax-inclusive — the business's own
 * spec labels package prices "excl. GST", so that's the convention the whole app follows.
 *
 * A plan is a bundle of several products, and each product can carry its own GST rate (falling
 * back to the company-wide default below when it doesn't). splitForPlan() allocates a bundle's
 * base price across its products by relative retail value, applies each product's own rate, and
 * sums the result — so a bundle of a 5%-rated and an 18%-rated item is taxed as what it actually
 * is, not as one blended guess.
 */
class Gst
{
    /** The company-wide default rate, used by any product that doesn't set its own. */
    public static function pct(): float
    {
        return (float) Setting::get('gst_pct', 12);
    }

    /** Flat fallback split — used only when there's no plan/product context to allocate against. */
    public static function gstOnTop(float $baseAmount): float
    {
        return round($baseAmount * self::pct() / 100, 2);
    }

    /**
     * Split a plan's taxable base price (or an order's snapshotted taxable amount) into GST and
     * total payable, allocated per product by retail value (qty × sell_price) within the bundle.
     * Proportional, so it works the same for one order's base amount or the summed base revenue
     * of many orders for the same plan.
     *
     * @return array{taxable: float, gst: float, total: float}
     */
    public static function splitForPlan(?Plan $plan, float $baseAmount): array
    {
        $products = $plan?->relationLoaded('products') ? $plan->products : $plan?->products()->get();

        if (! $plan || ! $products || $products->isEmpty()) {
            $gst = self::gstOnTop($baseAmount);

            return ['taxable' => $baseAmount, 'gst' => $gst, 'total' => round($baseAmount + $gst, 2)];
        }

        $weights = $products->map(fn ($p) => max(0.01, (float) $p->sell_price) * max(1, (int) $p->pivot->qty));
        $totalWeight = $weights->sum();

        $gstTotal = 0.0;
        foreach ($products as $i => $product) {
            $share = $totalWeight > 0 ? $weights[$i] / $totalWeight : 1 / $products->count();
            $allocatedBase = $baseAmount * $share;
            $gstTotal += $allocatedBase * $product->gstPct() / 100;
        }

        $gstTotal = round($gstTotal, 2);

        return ['taxable' => $baseAmount, 'gst' => $gstTotal, 'total' => round($baseAmount + $gstTotal, 2)];
    }
}
