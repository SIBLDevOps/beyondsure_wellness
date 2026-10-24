<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Rank;
use App\Models\Setting;
use Illuminate\View\View;

/**
 * The in-app Business Guide: explains every feature and the money flow using the live settings and
 * real plans, so the worked examples always match what the system will actually do today.
 */
class GuideController extends Controller
{
    public function index(): View
    {
        $levelPcts = Setting::getLevelPercentages();
        $selfPct = (float) Setting::get('self_pct', 10);
        $sponsorPct = (float) Setting::get('sponsor_pct', 20);
        $rankPoolPct = (float) Setting::get('rank_pool_pct', 3);
        $breakerPct = (float) Setting::get('circuit_breaker_pct', 60);
        $levelsTotal = round(array_sum($levelPcts), 2);

        $plans = Plan::where('is_active', true)->with('products')->orderBy('price')->get();

        $planData = $plans->map(function (Plan $p) use ($selfPct, $sponsorPct, $levelsTotal) {
            $bv = (float) $p->bv;
            $cost = (float) $p->costForQty();
            $price = (float) $p->price;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $price,
                'gst' => (float) $p->gstAmount(),
                'total' => (float) $p->totalPayable(),
                'bv' => $bv,
                'cost' => $cost,
                'self' => round($bv * $selfPct / 100, 2),
                'sponsor' => round($bv * $sponsorPct / 100, 2),
                'levels' => round($bv * $levelsTotal / 100, 2),
                'rest' => round($price - $cost - $bv, 2),
            ];
        })->values();

        // One concrete plan drives every worked example (prefers Essential, otherwise the cheapest).
        $sample = $planData->firstWhere('name', 'Essential') ?? $planData->first() ?? [
            'id' => 0, 'name' => 'Sample plan', 'price' => 2500.0, 'gst' => 300.0, 'total' => 2800.0, 'bv' => 1000.0,
            'cost' => 800.0, 'self' => round(1000 * $selfPct / 100, 2), 'sponsor' => round(1000 * $sponsorPct / 100, 2),
            'levels' => round(1000 * $levelsTotal / 100, 2), 'rest' => 700.0,
        ];

        $bv = (float) $sample['bv'];

        // Matching example: one new purchase matches against volume that was already waiting (carry-forward),
        // so the breaker's base is just this cycle's new BV.
        $gross = round($bv * $levelsTotal / 100, 2);
        $cap = round($bv * $breakerPct / 100, 2);
        $scale = $gross > 0 ? min(1, $cap / $gross) : 1;
        $cascade = collect($levelPcts)->map(fn ($pct, $lvl) => [
            'level' => $lvl,
            'pct' => (float) $pct,
            'gross' => round($bv * $pct / 100, 2),
            'paid' => round($bv * $pct / 100 * $scale, 2),
        ])->values();

        $ranks = Rank::orderBy('sort_order')->get();

        return view('admin.guide', [
            'settings' => [
                'self' => $selfPct,
                'sponsor' => $sponsorPct,
                'rankPool' => $rankPoolPct,
                'breaker' => $breakerPct,
                'depth' => count($levelPcts),
                'levelsTotal' => $levelsTotal,
                'weeklyCap' => (float) Setting::get('match_cap_per_week', 150000),
                'monthlyCap' => (float) Setting::get('match_cap_per_cycle', 600000),
                'costPct' => (float) Setting::get('split_cost_pct', 40),
                'poolPct' => (float) Setting::get('split_pool_pct', 40),
                'companyPct' => (float) Setting::get('split_company_pct', 20),
                'requireSponsor' => (bool) Setting::get('require_sponsor', 0),
            ],
            'levelPcts' => $levelPcts,
            'planData' => $planData,
            'sample' => $sample,
            'cascade' => $cascade,
            'gross' => $gross,
            'cap' => $cap,
            'cascadePaid' => round($gross * $scale, 2),
            'breakerBinds' => $gross > $cap,
            'ranks' => $ranks,
        ]);
    }
}
