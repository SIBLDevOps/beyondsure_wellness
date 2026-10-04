<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $value = static::where('key', $key)->value('value');

            return $value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    /**
     * Get the configured percentages for each level as an indexed array [1 => 10.0, 2 => 10.0, ...].
     *
     * @return array<int, float>
     */
    public static function getLevelPercentages(): array
    {
        $raw = static::get('level_payout_pcts');
        if ($raw) {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && ! empty($decoded)) {
                $result = [];
                $i = 1;
                foreach ($decoded as $val) {
                    $result[$i] = round((float) $val, 2);
                    $i++;
                }

                return $result;
            }
        }

        // Fallback: build from cascade_depth and matching_pct
        $depth = max(1, (int) static::get('cascade_depth', 7));
        $matchingPct = (float) static::get('matching_pct', 10);
        $result = [];
        for ($i = 1; $i <= $depth; $i++) {
            $result[$i] = $matchingPct;
        }

        return $result;
    }

    /**
     * Save the configured level percentages and synchronize cascade_depth.
     *
     * @param  array<int, float|numeric-string>  $levels
     */
    public static function setLevelPercentages(array $levels): void
    {
        $indexed = [];
        $i = 1;
        foreach ($levels as $pct) {
            $indexed[$i] = round((float) $pct, 2);
            $i++;
        }

        static::set('level_payout_pcts', json_encode($indexed));
        static::set('cascade_depth', count($indexed));
        if (isset($indexed[1])) {
            static::set('matching_pct', $indexed[1]);
        }
    }

    /**
     * Minimum new BV a layer must generate in a cycle before it's eligible for matching at all
     * (v2 model §4 — Self and Sponsor are never gated). Keyed by layer depth 1..N, plus a
     * 'fallback' value applied to every layer deeper than N. Defaults to all-zero (gate off),
     * matching the model's own loaded preset — nobody is forfeited anything until an admin
     * deliberately sets a threshold above zero.
     *
     * @return array<int|string, float>
     */
    public static function getEligibilityMinVolumes(): array
    {
        $raw = static::get('eligibility_min_volume');
        if (! $raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_map(fn ($v) => round((float) $v, 2), $decoded) : [];
    }

    /**
     * @param  array<int, float|numeric-string>  $layers  Indexed 1..N, in layer order.
     */
    public static function setEligibilityMinVolumes(array $layers, float|string $fallback = 0): void
    {
        $indexed = [];
        $i = 1;
        foreach ($layers as $v) {
            $indexed[(string) $i] = round((float) $v, 2);
            $i++;
        }
        $indexed['fallback'] = round((float) $fallback, 2);

        static::set('eligibility_min_volume', json_encode($indexed));
    }
}
