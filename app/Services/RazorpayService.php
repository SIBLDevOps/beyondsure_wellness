<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RazorpayService
{
    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret'));
    }

    /**
     * Create a Razorpay Order via the REST API (no SDK dependency — just the HTTP client).
     * Amount must be in the smallest currency unit (paise for INR).
     */
    public function createOrder(float $amountRupees, string $receipt): array
    {
        if (! $this->isConfigured()) {
            throw new RazorpayNotConfigured('Razorpay keys are not configured yet.');
        }

        $response = Http::withBasicAuth(config('services.razorpay.key'), config('services.razorpay.secret'))
            ->asJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($amountRupees * 100),
                'currency' => 'INR',
                'receipt' => $receipt,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Razorpay order creation failed: '.$response->body());
        }

        return $response->json();
    }

    public function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', "{$razorpayOrderId}|{$razorpayPaymentId}", (string) config('services.razorpay.secret'));

        return hash_equals($expected, $signature);
    }
}
