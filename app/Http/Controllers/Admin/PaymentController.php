<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
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

    public function verify(Payment $payment): RedirectResponse
    {
        try {
            $this->payments->verify($payment, Auth::user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment verified — order marked paid.');
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
