<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

class PaymentService
{
    public function submit(Order $order, string $method, ?string $reference): Payment
    {
        return $order->payment()->updateOrCreate([], [
            'method' => $method,
            'reference' => $reference,
            'status' => 'pending',
        ]);
    }

    public function verify(Payment $payment, User $admin): void
    {
        $payment->update([
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $payment->order->update(['status' => 'paid']);

        // A verified purchase activates the member's account (distinct from "active this
        // month", which is checked separately for matching/rank eligibility via order recency).
        $payment->order->member->update(['status' => 'active']);
    }

    public function reject(Payment $payment, User $admin): void
    {
        $payment->update([
            'status' => 'rejected',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * A payment confirmed by the gateway itself (Razorpay signature verified) rather than
     * by an admin reviewing a manual bank transfer — verified_by stays null to reflect that.
     */
    public function confirmGatewayPayment(Order $order, string $gatewayPaymentId): void
    {
        $order->payment()->updateOrCreate([], [
            'method' => 'razorpay',
            'reference' => $gatewayPaymentId,
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $order->update(['status' => 'paid']);
        $order->member->update(['status' => 'active']);
    }
}
