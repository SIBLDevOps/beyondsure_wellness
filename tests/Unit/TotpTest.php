<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** The secret and times from the RFC 6238 appendix (the 6-digit ends of its 8-digit values). */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';   // "12345678901234567890"

    public function test_it_matches_the_standard_test_vectors(): void
    {
        $this->assertSame('287082', Totp::at(self::SECRET, 59));
        $this->assertSame('081804', Totp::at(self::SECRET, 1111111109));
        $this->assertSame('050471', Totp::at(self::SECRET, 1111111111));
        $this->assertSame('005924', Totp::at(self::SECRET, 1234567890));
        $this->assertSame('279037', Totp::at(self::SECRET, 2000000000));
    }

    public function test_base32_round_trips_and_ignores_spaces_and_case(): void
    {
        $raw = random_bytes(20);
        $this->assertSame($raw, Totp::base32Decode(Totp::base32Encode($raw)));
        $this->assertSame('12345678901234567890', Totp::base32Decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'));
    }

    public function test_verify_accepts_the_current_and_neighbouring_codes_but_nothing_else(): void
    {
        $now = 1234567890;

        $this->assertTrue(Totp::verify(self::SECRET, Totp::at(self::SECRET, $now), $now));
        $this->assertTrue(Totp::verify(self::SECRET, Totp::at(self::SECRET, $now - 30), $now));
        $this->assertTrue(Totp::verify(self::SECRET, Totp::at(self::SECRET, $now + 30), $now));
        $this->assertFalse(Totp::verify(self::SECRET, Totp::at(self::SECRET, $now - 90), $now), 'Too old');
        $this->assertFalse(Totp::verify(self::SECRET, '000000', $now));
        foreach (['', 'abcdef', '12345', '1234567', '12 34'] as $bad) {
            $this->assertFalse(Totp::verify(self::SECRET, $bad, $now), "'{$bad}'");
        }
        $this->assertTrue(Totp::verify(self::SECRET, '005 924', $now), 'Spaces typed in the middle are ignored');
    }

    public function test_a_generated_secret_is_long_random_and_valid_base32(): void
    {
        $a = Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $a);
        $this->assertNotSame($a, Totp::generateSecret());
    }

    public function test_the_setup_address_names_the_account_and_company(): void
    {
        $uri = Totp::uri('ABCDEFGH', 'admin@x.com', 'My Co');
        $this->assertStringStartsWith('otpauth://totp/My%20Co%3Aadmin%40x.com?secret=ABCDEFGH', $uri);
        $this->assertStringContainsString('issuer=My%20Co', $uri);
    }
}
