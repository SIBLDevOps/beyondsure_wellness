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

        $code = (string) random_int(10 ** (self::LENGTH - 1), (10 ** self::LENGTH) - 1);

        $otp = OtpVerification::create([
            'identifier' => $identifier,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        // No SMS/email gateway wired up yet — log it so the flow is testable end to end.
        Log::info("OTP for {$identifier} ({$purpose}): {$code}");

        return [
            'throttled' => false,
            'otp_id' => $otp->id,
            'expires_at' => $otp->expires_at,
            'debug_code' => config('app.debug') ? $code : null,
        ];
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
