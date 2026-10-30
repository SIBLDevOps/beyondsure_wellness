<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            'zero' => [0, 'Rupees Zero Only'],
            'one' => [1, 'Rupees One Only'],
            'teen' => [13, 'Rupees Thirteen Only'],
            'tens' => [40, 'Rupees Forty Only'],
            'two digits' => [99, 'Rupees Ninety Nine Only'],
            'hundred' => [100, 'Rupees One Hundred Only'],
            'hundreds' => [345, 'Rupees Three Hundred Forty Five Only'],
            'thousand' => [1000, 'Rupees One Thousand Only'],
            'order total' => [2240, 'Rupees Two Thousand Two Hundred Forty Only'],
            'ten thousand' => [10000, 'Rupees Ten Thousand Only'],
            'lakh' => [100000, 'Rupees One Lakh Only'],
            'lakhs' => [1234567, 'Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven Only'],
            'crore' => [10000000, 'Rupees One Crore Only'],
            'crores' => [123456789, 'Rupees Twelve Crore Thirty Four Lakh Fifty Six Thousand Seven Hundred Eighty Nine Only'],
            'paise' => [2800.50, 'Rupees Two Thousand Eight Hundred and Fifty Paise Only'],
            'one paisa' => [0.01, 'Rupees Zero and One Paisa Only'],
            'paise only' => [0.99, 'Rupees Zero and Ninety Nine Paise Only'],
            'string input' => ['2240.00', 'Rupees Two Thousand Two Hundred Forty Only'],
            'float noise' => [0.1 + 0.2, 'Rupees Zero and Thirty Paise Only'],
            'rounds up' => [1.995, 'Rupees Two Only'],
            'negative is treated as its size' => [-5, 'Rupees Five Only'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_it_writes_indian_rupee_amounts_in_words(float|int|string $amount, string $words): void
    {
        $this->assertSame($words, AmountInWords::rupees($amount));
    }
}
