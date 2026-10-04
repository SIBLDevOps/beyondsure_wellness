<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\RazorpayNotConfigured;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly RazorpayService $razorpay,
    ) {}

    public function index(): View
    {
        $member = Auth::guard('member')->user();

        return view('member.orders', ['orders' => $member->orders()->with('plan', 'payment')->latest()->get()]);
    }

    public function pay(Order $order): View
    {
        $this->authorizeOwnership($order);

        $member = Auth::guard('member')->user();

        return view('member.pay', [
            'order' => $order->load('payment', 'plan.products'),
            'razorpayConfigured' => $this->razorpay->isConfigured(),
            'razorpayKey' => config('services.razorpay.key'),
            'billingName' => $order->billing_name ?? $member->name,
            'billingEmail' => $order->billing_email ?? $member->email,
            'billingPhone' => $order->billing_phone ?? $member->phone,
        ]);
    }

    public function submitPayment(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwnership($order);

        if ($request->filled('billing_phone')) {
            $request->merge(['billing_phone' => trim((string) $request->input('billing_phone'))]);
        }
        if ($request->filled('reference')) {
            $request->merge(['reference' => trim((string) $request->input('reference'))]);
        }

        $data = $request->validate([
            'method' => ['required', 'in:bank_transfer,upi,cash'],
            'reference' => ['required_unless:method,cash', 'nullable', 'string', 'max:255'],
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'billing_address' => ['nullable', 'string', 'max:500'],
        ], [
            'method.required' => 'Please select a payment method.',
            'method.in' => 'Please select a valid payment method.',
            'reference.required_unless' => 'A reference / UTR transaction ID is required for bank transfer or UPI payments.',
            'billing_name.required' => 'Billing name is required.',
            'billing_phone.required' => 'Billing phone number is required.',
            'billing_phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'billing_email.email' => 'Please enter a valid email address.',
        ]);

        $order->update($this->billingFields($data));
        $this->payments->submit($order, $data['method'], $data['reference'] ?? null);

        return redirect()->route('member.orders.index')->with('status', 'Payment submitted — awaiting admin verification.');
    }

    /** Saves billing details and creates the Razorpay order; returns what the frontend widget needs. */
    public function razorpayOrder(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwnership($order);

        if ($order->status !== 'pending_payment') {
            return response()->json(['message' => 'This order is no longer awaiting payment.'], 422);
        }

        if ($request->filled('billing_phone')) {
            $request->merge(['billing_phone' => trim((string) $request->input('billing_phone'))]);
        }

        $data = $request->validate([
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'billing_address' => ['nullable', 'string', 'max:500'],
        ], [
            'billing_name.required' => 'Billing name is required.',
            'billing_phone.required' => 'Billing phone number is required.',
            'billing_phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'billing_email.email' => 'Please enter a valid email address.',
        ]);

        $order->update($this->billingFields($data));

        try {
            $razorpayOrder = $this->razorpay->createOrder((float) $order->amount, $order->order_code);
        } catch (RazorpayNotConfigured $e) {
            return response()->json(['message' => 'Online payment is not configured yet. Please use the manual payment option below.'], 422);
        }

        $order->update(['razorpay_order_id' => $razorpayOrder['id']]);

        return response()->json([
            'key' => config('services.razorpay.key'),
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
            'name' => 'BeyondSure+',
            'description' => $order->plan->name.' plan',
            'prefill' => [
                'name' => $data['billing_name'],
                'email' => $data['billing_email'] ?? '',
                'contact' => $data['billing_phone'],
            ],
        ]);
    }

    public function razorpayCallback(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwnership($order);

        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if ($data['razorpay_order_id'] !== $order->razorpay_order_id) {
            return redirect()->route('member.orders.pay', $order)->withErrors(['payment' => 'Payment order mismatch — please try again.']);
        }

        $valid = $this->razorpay->verifySignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        if (! $valid) {
            return redirect()->route('member.orders.pay', $order)->withErrors(['payment' => 'Payment verification failed — please contact support.']);
        }

        $this->payments->confirmGatewayPayment($order, $data['razorpay_payment_id']);

        return redirect()->route('member.orders.index')->with('status', 'Payment successful — your order is confirmed!');
    }

    private function billingFields(array $data): array
    {
        return [
            'billing_name' => $data['billing_name'],
            'billing_email' => $data['billing_email'] ?? null,
            'billing_phone' => $data['billing_phone'],
            'billing_address' => $data['billing_address'] ?? null,
        ];
    }

    private function authorizeOwnership(Order $order): void
    {
        if ($order->member_id !== Auth::guard('member')->id()) {
            abort(403);
        }
    }
}
