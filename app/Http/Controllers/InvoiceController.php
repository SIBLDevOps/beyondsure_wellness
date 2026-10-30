<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\MemberMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The printable tax invoice for a paid order. Members open their own (route "member.orders.invoice"); staff with Orders
 * access open any order's within their regions (route "admin.orders.invoice"); anyone with a signed, time-limited link
 * from an email can open it too (route "invoice.shared"). Orders that were never paid have none.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function member(Order $order): View
    {
        abort_unless($order->member_id === Auth::guard('member')->id(), 403);

        return $this->show($order);
    }

    public function admin(Order $order): View
    {
        return $this->show($order);
    }

    /** Opened from an emailed link. The "signed" middleware has already checked the signature and the expiry. */
    public function shared(int $order): View
    {
        // Looked up here, after the signature was checked, so a forged link is always refused the same way
        // whether or not that order number exists.
        return $this->show(Order::withoutGlobalScopes()->findOrFail($order), shared: true);
    }

    /** A member emails their own invoice (as a signed link) to any address, for example their accountant. */
    public function email(Request $request, Order $order): RedirectResponse
    {
        $member = Auth::guard('member')->user();
        abort_unless($order->member_id === $member->id, 403);
        abort_unless(in_array($order->status, ['paid', 'settled'], true), 404);

        $data = $request->validate(['email' => ['required', 'email', 'max:255']], ['email.required' => 'Enter the email address to send it to.', 'email.email' => 'Enter a valid email address.']);

        $this->invoices->issue($order);
        app(MemberMailer::class)->invoiceTo($order->fresh(), $data['email'], $member->name);

        return back()->with('status', 'Invoice sent to '.$data['email'].'.');
    }

    private function show(Order $order, bool $shared = false): View
    {
        // An order cancelled by a credit note still has its invoice: it was issued, and the credit note refers to it.
        abort_unless(in_array($order->status, ['paid', 'settled'], true) || ($order->invoice_number && $order->creditNote()->exists()), 404);

        // Orders paid before invoices existed get their number the first time one is opened.
        $this->invoices->issue($order);

        return view('invoices.show', $this->invoices->build($order->fresh()) + ['shared' => $shared]);
    }
}
