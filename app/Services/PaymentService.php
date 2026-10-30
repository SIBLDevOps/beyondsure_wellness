<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * A manual payment can only be (re)submitted while the order is still waiting for payment and the payment has not
     * already been verified. Otherwise a settled order's verified payment record could be replaced by a fake pending
     * one, or a cancelled order brought back to life.
     */
    public function submit(Order $order, string $method, ?string $reference): Payment
    {
        if ($order->status !== 'pending_payment') {
            throw new \RuntimeException('This order is no longer waiting for payment.');
        }

        if ($order->payment?->status === 'verified') {
            throw new \RuntimeException('This order already has a verified payment.');
        }

        $payment = $order->payment()->updateOrCreate([], [
            'method' => $method,
            'reference' => $reference,
            'status' => 'pending',
        ]);

        app(MemberMailer::class)->paymentReceived($order->fresh(['payment']));

        return $payment;
    }

    /** The emails that follow an approved purchase: the member's confirmation with their invoice, and their sponsor's news. */
    private function announcePurchase(int $orderId, bool $firstPurchase): void
    {
        $order = Order::withoutGlobalScopes()->with('member.sponsor', 'plan')->find($orderId);
        if (! $order) {
            return;
        }

        $mailer = app(MemberMailer::class);
        $mailer->purchaseConfirmed($order);

        if ($firstPurchase) {
            $mailer->sponsorMemberActive($order->member);
        }
    }

    /** Only a pending payment on an order that is still awaiting payment can be verified, and only once. */
    public function verify(Payment $payment, User $admin): void
    {
        $firstPurchase = false;
        $orderId = null;

        DB::transaction(function () use ($payment, $admin, &$firstPurchase, &$orderId) {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($lockedPayment->order_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending') {
                throw new \RuntimeException('This payment has already been '.$lockedPayment->status.'.');
            }

            if ($order->status !== 'pending_payment') {
                throw new \RuntimeException('This order is '.str_replace('_', ' ', $order->status).', so its payment cannot be verified.');
            }

            $lockedPayment->update([
                'status' => 'verified',
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $order->update(['status' => 'paid']);
            app(InvoiceService::class)->issue($order);

            // A verified purchase activates the member's account (distinct from "active this month", which is
            // checked separately for matching/rank eligibility via order recency).
            $firstPurchase = $order->member->status !== 'active';
            $orderId = $order->id;
            $order->member->update(['status' => 'active']);
        });

        $payment->refresh();
        $this->announcePurchase($orderId, $firstPurchase);
    }

    public function reject(Payment $payment, User $admin): void
    {
        DB::transaction(function () use ($payment, $admin) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new \RuntimeException('This payment has already been '.$locked->status.'.');
            }

            $locked->update([
                'status' => 'rejected',
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);
        });

        $payment->refresh();
        app(MemberMailer::class)->paymentRejected($payment->order()->withoutGlobalScopes()->first());
    }

    /**
     * A payment confirmed by the gateway itself (Razorpay signature verified) rather than
     * by an admin reviewing a manual bank transfer — verified_by stays null to reflect that.
     * Replaying the same confirmation later (or on a settled or cancelled order) changes nothing.
     */
    public function confirmGatewayPayment(Order $order, string $gatewayPaymentId): bool
    {
        $firstPurchase = false;

        $confirmed = DB::transaction(function () use ($order, $gatewayPaymentId, &$firstPurchase) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending_payment') {
                return false;
            }

            $locked->payment()->updateOrCreate([], [
                'method' => 'razorpay',
                'reference' => $gatewayPaymentId,
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            $locked->update(['status' => 'paid']);
            app(InvoiceService::class)->issue($locked);
            $firstPurchase = $locked->member->status !== 'active';
            $locked->member->update(['status' => 'active']);
            $order->refresh();

            return true;
        });

        if ($confirmed) {
            $this->announcePurchase($order->id, $firstPurchase);
        }

        return $confirmed;
    }
}
