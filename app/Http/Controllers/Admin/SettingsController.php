<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const KEYS = [
        'gst_pct',
        'split_cost_pct', 'split_pool_pct', 'split_company_pct',
        'self_pct', 'sponsor_pct', 'matching_pct', 'cascade_depth', 'rank_pool_pct',
        'match_cap_per_cycle', 'match_cap_per_week', 'circuit_breaker_pct',
    ];

    public function edit(): View
    {
        $defaults = ['circuit_breaker_pct' => 60, 'gst_pct' => 12];
        $values = collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k, $defaults[$k] ?? 0)]);
        $levelPcts = Setting::getLevelPercentages();

        return view('admin.settings.edit', [
            'values' => $values,
            'levelPcts' => $levelPcts,
            'ranks' => Rank::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'gst_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'split_cost_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'split_pool_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'split_company_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'self_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'sponsor_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'matching_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cascade_depth' => ['nullable', 'integer', 'min:1', 'max:25'],
            'rank_pool_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'match_cap_per_cycle' => ['required', 'numeric', 'min:0'],
            'match_cap_per_week' => ['required', 'numeric', 'min:0'],
            'circuit_breaker_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'levels' => ['nullable', 'array', 'min:1', 'max:25'],
            'levels.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'eligibility' => ['nullable', 'array'],
            'eligibility.*' => ['nullable', 'numeric', 'min:0'],
            'eligibility_fallback' => ['nullable', 'numeric', 'min:0'],
        ];

        $data = $request->validate($rules, [
            'levels.required' => 'Please configure at least 1 level for income distribution.',
            'levels.min' => 'You must have at least 1 level configured.',
            'levels.max' => 'Maximum allowed depth is 25 levels.',
            'levels.*.required' => 'Every level must have a valid percentage value.',
            'levels.*.numeric' => 'Level percentage must be a number.',
            'levels.*.min' => 'Level percentage cannot be negative (minimum 0%).',
            'levels.*.max' => 'A single level percentage cannot exceed 100%.',
        ]);

        $splitSum = round((float) $data['split_cost_pct'] + (float) $data['split_pool_pct'] + (float) $data['split_company_pct'], 2);
        if (abs($splitSum - 100.0) > 0.01) {
            return back()->withInput()->withErrors([
                'split_pool_pct' => "Revenue split calculation error: Cost ({$data['split_cost_pct']}%) + Pool ({$data['split_pool_pct']}%) + Company ({$data['split_company_pct']}%) equals {$splitSum}%. The three must add up to exactly 100%.",
            ]);
        }

        if (! empty($data['levels']) && is_array($data['levels'])) {
            $levels = array_values(array_map(fn ($v) => round((float) $v, 2), $data['levels']));
        } else {
            $depth = max(1, (int) ($data['cascade_depth'] ?? Setting::get('cascade_depth', 7)));
            $flatPct = round((float) ($data['matching_pct'] ?? Setting::get('matching_pct', 10)), 2);
            $levels = array_fill(0, $depth, $flatPct);
        }

        Setting::setLevelPercentages($levels);
        Setting::setEligibilityMinVolumes($data['eligibility'] ?? [], $data['eligibility_fallback'] ?? 0);

        $data['cascade_depth'] = count($levels);
        $data['matching_pct'] = $levels[0] ?? 10;

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                Setting::set($key, $data[$key]);
            }
        }

        $totalLevelPct = round(array_sum($levels), 2);
        $totalPayoutPct = round((float) $data['self_pct'] + (float) $data['sponsor_pct'] + $totalLevelPct + (float) $data['rank_pool_pct'], 2);

        $statusMsg = "Settings saved — configured {$data['cascade_depth']} levels (Total Level Payout: {$totalLevelPct}%). Applies on the next cycle approval.";

        if ($totalPayoutPct > 100.0) {
            $over = round($totalPayoutPct - 100.0, 2);
            $recommendedMaxLevel = max(0, round(100.0 - (float) $data['self_pct'] - (float) $data['sponsor_pct'] - (float) $data['rank_pool_pct'], 2));

            return back()
                ->with('status', $statusMsg)
                ->with('calculation_warning', "Calculation Guidance: Total potential compensation is {$totalPayoutPct}% of BV (Self {$data['self_pct']}% + Sponsor {$data['sponsor_pct']}% + Levels {$totalLevelPct}% + Rank {$data['rank_pool_pct']}%), which exceeds 100% of BV by {$over}%. For 100% self-funded sustainability, keep total level percentages at or below {$recommendedMaxLevel}%.");
        }

        $buffer = round(100.0 - $totalPayoutPct, 2);

        return back()->with('status', "{$statusMsg} Total compensation is {$totalPayoutPct}% of BV (leaves {$buffer}% unallocated reserve buffer).");
    }
}
