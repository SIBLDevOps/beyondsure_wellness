<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * "The same day, until 23:59" is a day in the business's own time (India), not in the server's UTC. Times are stored in
 * UTC; this converts them for comparing days and for the admin's date field.
 */
class BusinessDay
{
    public static function timezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Kolkata');
    }

    /** False until the migration that adds payments.paid_at and members.activated_at has been run, so nothing breaks before then. */
    public static function columnsReady(): bool
    {
        static $ready = null;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('payments', 'paid_at') && \Illuminate\Support\Facades\Schema::hasColumn('members', 'activated_at');
    }

    /** False until the migration that adds member_leg_totals.own_matched_bv has been run: the old rule applies until then. */
    public static function ownMatchReady(): bool
    {
        static $ready = null;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('member_leg_totals', 'own_matched_bv');
    }

    /** The calendar day (Y-m-d) a moment falls on in business time. */
    public static function date(?CarbonInterface $moment): ?string
    {
        return $moment?->copy()->setTimezone(self::timezone())->toDateString();
    }

    /** The admin's "2026-11-06T23:40" (business time) as a moment. */
    public static function parse(string $input): Carbon
    {
        return Carbon::createFromFormat('Y-m-d\TH:i', $input, self::timezone())->setTimezone(config('app.timezone'));
    }

    /** A moment in the format the date field wants, in business time. */
    public static function forInput(?CarbonInterface $moment): string
    {
        return ($moment ?? now())->copy()->setTimezone(self::timezone())->format('Y-m-d\TH:i');
    }

    public static function label(?CarbonInterface $moment): string
    {
        return $moment ? $moment->copy()->setTimezone(self::timezone())->format('d M Y H:i') : '—';
    }
}
