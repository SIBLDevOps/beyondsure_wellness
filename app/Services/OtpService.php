<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;
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
    public function issue(string $identifier, string $purpose): array
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

        // No SMS/email gateway is wired up yet, so on a developer machine the code is logged and shown on screen to
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
     * Whether the code is shown on screen and logged instead of (or until) being sent by SMS. True when the explicit
     * switch OTP_SHOW_ON_SCREEN is on (set it on UAT), or on a local / test machine with debug on. A live site has
     * neither, so it never exposes or logs a code.
     */
    private function developerMode(): bool
    {
        return (bool) config('otp.show_on_screen')
            || ((bool) config('app.debug') && app()->environment('local', 'testing'));
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
