<?php

namespace App\Support;

/** An amount in Indian rupees written in words (lakh and crore), as printed at the bottom of an invoice. */
class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function rupees(float|int|string $amount): string
    {
        // Work in whole paise so 0.1 + 0.2 and the like cannot leave a stray fraction.
        $paise = (int) round(abs((float) $amount) * 100);
        $rupees = intdiv($paise, 100);
        $rest = $paise % 100;

        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::number($rupees));

        if ($rest > 0) {
            $words .= ' and '.self::number($rest).($rest === 1 ? ' Paisa' : ' Paise');
        }

        return $words.' Only';
    }

    private static function number(int $n): string
    {
        $parts = [];

        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$size, $name]) {
            if ($n >= $size) {
                $parts[] = self::number(intdiv($n, $size)).' '.$name;
                $n %= $size;
            }
        }

        if ($n > 0) {
            $parts[] = $n < 20 ? self::ONES[$n] : self::TENS[intdiv($n, 10)].($n % 10 ? ' '.self::ONES[$n % 10] : '');
        }

        return implode(' ', $parts);
    }
}
