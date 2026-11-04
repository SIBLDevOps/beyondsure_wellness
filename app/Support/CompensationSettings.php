<?php

namespace App\Support;

use App\Models\Setting;

/**
 * What must be saved on the admin Settings page before any money is calculated, and the choices about members who have
 * not paid yet. A cycle posts real income, so it never runs on built-in defaults: every value below has to have been
 * saved on purpose, and they have to make sense together.
 */
class CompensationSettings
{
    /** key => [label shown to the admin, a sensible starting value, kind]. kind: pct (0-100), amount (0 or more), levels (a list of percentages) */
    public const REQUIRED = [
        'split_cost_pct' => ['Product cost share', 40, 'pct'],
        'split_pool_pct' => ['Pool share', 40, 'pct'],
        'split_company_pct' => ['Company share', 20, 'pct'],
        'self_pct' => ['Self income', 10, 'pct'],
        'sponsor_pct' => ['Sponsor bonus', 20, 'pct'],
        'rank_pool_pct' => ['Rank pool', 3, 'pct'],
        'match_cap_per_cycle' => ['Monthly matching cap', 600000, 'amount'],
        'match_cap_per_week' => ['Weekly matching cap', 150000, 'amount'],
        'circuit_breaker_pct' => ['Circuit breaker', 60, 'pct'],
        'level_payout_pcts' => ['Level income percentages', [10, 10, 10, 10, 10, 10, 10], 'levels'],
    ];

    /** @return array<string, mixed> every value with its starting figure */
    public static function defaults(): array
    {
        return array_map(fn (array $row) => $row[1], self::REQUIRED);
    }

    /**
     * Saves a complete set, as pressing Save on the Settings page does. Used by tests and by anyone setting up quickly.
     *
     * @param  array<string, mixed>  $overrides  setting key => value; "levels" is the list of level percentages
     */
    public static function saveDefaults(array $overrides = []): void
    {
        $values = $overrides + self::defaults();
        $levels = $overrides['levels'] ?? $values['level_payout_pcts'];
        unset($values['levels'], $values['level_payout_pcts']);

        foreach ($values as $key => $value) {
            Setting::set($key, $value);
        }
        Setting::setLevelPercentages($levels);
    }

    /** @return array<string, string> key => stored value, read straight from the database (never from a cache or a default) */
    private static function stored(): array
    {
        return Setting::whereIn('key', array_keys(self::REQUIRED))->pluck('value', 'key')->map(fn ($v) => trim((string) $v))->all();
    }

    /** @return list<string> the labels of settings that were never saved (or saved empty) */
    public static function missing(): array
    {
        $stored = self::stored();

        return collect(self::REQUIRED)
            ->filter(fn ($row, $key) => ($stored[$key] ?? '') === '')
            ->map(fn ($row) => $row[0])->values()->all();
    }

    /** @return list<string> everything wrong: what is missing, and values that cannot be right */
    public static function problems(): array
    {
        $stored = self::stored();
        $problems = [];

        foreach (self::REQUIRED as $key => [$label, , $kind]) {
            $value = $stored[$key] ?? '';

            if ($value === '') {
                $problems[] = "{$label} has not been saved";

                continue;
            }

            if ($kind === 'levels') {
                $list = json_decode($value, true);
                $valid = is_array($list) && $list !== [] && collect($list)->every(fn ($p) => is_numeric($p) && $p >= 0 && $p <= 100);
                if (! $valid) {
                    $problems[] = "{$label} must be a list of percentages between 0 and 100";
                }
            } elseif (! is_numeric($value) || (float) $value < 0 || ($kind === 'pct' && (float) $value > 100)) {
                $problems[] = $label.' must be a number '.($kind === 'pct' ? 'between 0 and 100' : 'of 0 or more');
            }
        }

        $split = ['split_cost_pct', 'split_pool_pct', 'split_company_pct'];
        if (collect($split)->every(fn ($k) => is_numeric($stored[$k] ?? null))) {
            $sum = array_sum(array_map(fn ($k) => (float) $stored[$k], $split));
            if (abs($sum - 100.0) > 0.01) {
                $problems[] = 'Product cost share + Pool share + Company share must add up to 100 (they add up to '.round($sum, 2).')';
            }
        }

        return $problems;
    }

    /** @throws \RuntimeException when anything is missing or wrong */
    public static function assertReady(): void
    {
        $problems = self::problems();

        if ($problems !== []) {
            throw new \RuntimeException('Settings are not complete: '.implode('; ', $problems).'. Save them on the Settings page first.');
        }
    }

    // ------------------------------------------------------------------------------------------ members who have not paid yet

    /** Do people who registered but have not paid show in their sponsor's team, tree and levels? On unless switched off. */
    public static function showInactiveInDownline(): bool
    {
        return self::flag('show_inactive_in_downline', true);
    }

    /** Does a sponsor who has not paid yet still earn sponsor income? Off unless switched on. */
    public static function inactiveEarnsSponsor(): bool
    {
        return self::flag('inactive_earns_sponsor', false);
    }

    /** Does a member who has not paid yet still earn matching income? Off unless switched on. */
    public static function inactiveEarnsMatching(): bool
    {
        return self::flag('inactive_earns_matching', false);
    }

    // ------------------------------------------------------------------------------------------ the root of the tree

    /**
     * The root of a region's tree (the member the admin created to start it) is always inside the level window: when a
     * matching event is deeper than the window reaches, the root takes the window's last slot and is paid that level's
     * rate. The number of people paid does not change. On unless switched off.
     */
    public static function rootAlwaysPaid(): bool
    {
        return self::flag('root_matching_enabled', true);
    }

    private static function flag(string $key, bool $default): bool
    {
        $value = Setting::get($key);

        return ($value === null || $value === '') ? $default : in_array((string) $value, ['1', 'true', 'on'], true);
    }
}
