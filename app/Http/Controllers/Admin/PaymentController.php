<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\BusinessDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(): View
    {
        return view('admin.payments.index', [
            'payments' => Payment::with('order.member', 'order.plan')->latest()->paginate(25),
        ]);
    }

    /** The date field is the time the member really paid (India time). Empty means the time they submitted it. */
    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ], [
            'paid_at.date_format' => 'Enter the payment date and time like 06 Nov 2026 23:40.',
        ]);

        $paidAt = ! empty($data['paid_at']) ? BusinessDay::parse($data['paid_at']) : null;
        $submitted = $payment->created_at;

        try {
            $this->payments->verify($payment, Auth::user(), $paidAt);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        $this->logPaidAt($payment->fresh('order'), 'Payment date set on approval', $submitted, $payment->fresh()->paid_at);

        return back()->with('status', 'Payment verified — order marked paid.');
    }

    /** Super Admin only: corrects the date after approval. Money already settled in an approved cycle is not recalculated. */
    public function changePaidAt(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $data = $request->validate([
            'paid_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ], [
            'paid_at.required' => 'Enter the payment date and time.',
            'paid_at.date_format' => 'Enter the payment date and time like 06 Nov 2026 23:40.',
        ]);
        $before = $payment->paid_at;

        try {
            $this->payments->changePaidAt($payment, BusinessDay::parse($data['paid_at']));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        $this->logPaidAt($payment->fresh('order'), 'Payment date changed', $before, $payment->fresh()->paid_at);

        return back()->with('status', 'Payment date updated. Income already settled in an approved cycle is not changed.');
    }

    private function logPaidAt(Payment $payment, string $what, ?\Carbon\CarbonInterface $from, ?\Carbon\CarbonInterface $to): void
    {
        AuditLog::record('payment.paid_at', 'Payment for order '.($payment->order->order_code ?? $payment->order_id), details: $what.': '.BusinessDay::label($from).' → '.BusinessDay::label($to).' (India time)');
    }

    public function reject(Payment $payment): RedirectResponse
    {
        try {
            $this->payments->reject($payment, Auth::user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment rejected.');
    }
}
