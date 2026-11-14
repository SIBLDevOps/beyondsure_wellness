<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private const LENGTH = 6;

    private const TTL_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_PER_HOUR = 5;

    /**
     * Issue a fresh OTP for the given identifier (phone or email) and purpose.
     * Returns the plaintext code only in local/debug so a developer can read it
     * without a configured SMS/email gateway; never returned otherwise.
     */
    public function issue(string $identifier, string $purpose, ?string $name = null): array
    {
        $recent = OtpVerification::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($recent && $recent->created_at->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            $wait = self::RESEND_COOLDOWN_SECONDS - $recent->created_at->diffInSeconds(now());

            return ['throttled' => true, 'retry_after' => $wait];
        }

        // Hourly cap per number: stops someone from flooding a victim with codes (and from buying themselves
        // unlimited guesses), whatever address they come from.
        $window = OtpVerification::where('identifier', $identifier)->where('purpose', $purpose)->where('created_at', '>=', now()->subHour());
        if ($window->count() >= self::MAX_PER_HOUR) {
            $oldest = (clone $window)->oldest('id')->first();

            return ['throttled' => true, 'retry_after' => max(1, (int) now()->diffInSeconds($oldest->created_at->copy()->addHour()))];
        }

        $code = (string) random_int(10 ** (self::LENGTH - 1), (10 ** self::LENGTH) - 1);

        $otp = OtpVerification::create([
            'identifier' => $identifier,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        // The code goes to the member's mobile when SMS is switched on (a failing API never blocks the sign-in).
        $this->sendSms($identifier, $code, $name);

        // On a developer machine the code is also logged and shown on screen, so on a developer machine the code is logged and shown on screen to
        // make the flow testable. Never anywhere else: a live site that leaks the code lets anyone sign in as anyone.
        $developerMode = $this->developerMode();
        if ($developerMode) {
            Log::info("OTP for {$identifier} ({$purpose}): {$code}");
        }

        return [
            'throttled' => false,
            'otp_id' => $otp->id,
            'expires_at' => $otp->expires_at,
            'debug_code' => $developerMode ? $code : null,
        ];
    }

    /**
     * Texts the code to a 10-digit mobile through the SMS API (template "bima_new_otp": name, code, brand). Silent when
     * SMS is off, when the identifier is not a mobile number, or when the settings are incomplete. An API failure is
     * logged (masked number only) and never stops the member from asking for the code again.
     */
    private function sendSms(string $identifier, string $code, ?string $name = null): void
    {
        $sms = (array) config('otp.sms', []);

        if (empty($sms['enabled'])) {
            return;
        }

        $mobile = preg_replace('/\s+/', '', $identifier);
        // The SMS API accepts only a 10-digit mobile that starts with 6, 7, 8 or 9.
        if (! preg_match('/^[6-9]\d{9}$/', (string) $mobile)) {
            return;
        }

        // The SMS API answers 401 SMS_API_KEY_REQUIRED to every call without the key issued for it (OTP_SMS_TOKEN).
        foreach (['url' => 'OTP_SMS_URL', 'template_slug' => 'OTP_SMS_TEMPLATE_SLUG', 'token' => 'OTP_SMS_TOKEN'] as $required => $envName) {
            if (empty($sms[$required])) {
                Log::warning('OTP SMS not sent: the SMS settings are incomplete (set '.$envName.' in .env).');

                return;
            }
        }

        // The first template value is a name in a short text message: plain, one line, and short.
        $person = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $name)));
        $person = $person !== '' ? mb_substr($person, 0, 30) : 'Member';
        $masked = substr($mobile, 0, 2).'******'.substr($mobile, -2);

        try {
            $response = Http::timeout((int) ($sms['timeout'] ?? 8))->acceptJson()->asJson()->withToken((string) $sms['token'])->post((string) $sms['url'], [
                'template_slug' => $sms['template_slug'],
                'mobile'        => $mobile,
                'values'        => [$person, $code, $person ?: \App\Support\Site::name()],
            ]);
            //$sms['brand']
            Log::info($response);
            if ($response->successful()) {
                Log::info("OTP SMS handed to the SMS API for {$masked}.");
            } else {
                $why = $response->json('errors.developer.error_code') ?? $response->json('message') ?? '';
                Log::warning("OTP SMS was refused by the SMS API for {$masked} (HTTP {$response->status()}".($why !== '' ? ': '.mb_substr((string) $why, 0, 80) : '').').');
            }
        } catch (\Throwable $e) {
            Log::warning("OTP SMS could not reach the SMS API for {$masked}: ".get_class($e));
        }
    }
    /**
     * Whether the code is shown on screen and logged instead of (or until) being sent by SMS. True on a local, test or
     * UAT / staging server (APP_ENV local, testing, uat, staging), or when the explicit switch OTP_SHOW_ON_SCREEN is on
     * (for a UAT server that runs with APP_ENV=production). A live site is APP_ENV=production with the switch off, so
     * it never exposes or logs a code.
     */
    private function developerMode(): bool
    {
        return app()->environment('local', 'testing', 'uat', 'staging') || (bool) config('otp.show_on_screen');
    }

    public function verify(string $identifier, string $purpose, string $code): array
    {
        $otp = OtpVerification::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return ['ok' => false, 'error' => 'No pending verification for this identifier. Request a new OTP.'];
        }

        if ($otp->expires_at->isPast()) {
            return ['ok' => false, 'error' => 'OTP has expired. Request a new one.'];
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Request a new OTP.'];
        }

        if (! Hash::check($code, $otp->otp_hash)) {
            $otp->increment('attempts');

            return ['ok' => false, 'error' => 'Incorrect OTP.'];
        }

        $otp->update(['verified_at' => now()]);

        return ['ok' => true];
    }
}
