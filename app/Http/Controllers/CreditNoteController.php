<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Issuing a credit note for a paid order (staff with Finance access) and opening it (the buyer, or staff). */
class CreditNoteController extends Controller
{
    public function store(Request $request, Order $order, CreditNoteService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\CreditNote::METHODS))],
            // Cash has no bank reference; every other way of sending money back does.
            'refund_reference' => [\Illuminate\Validation\Rule::requiredIf(fn () => $request->input('refund_method') !== 'cash'), 'nullable', 'string', 'max:255'],
        ], [
            'reason.required' => 'Write the reason for the refund.',
            'refund_method.required' => 'Choose how the money is being sent back.',
            'refund_method.in' => 'Choose one of the listed ways of refunding.',
            'refund_reference.required' => 'Enter the reference of the refund (UTR, UPI or gateway refund ID).',
        ]);

        try {
            $note = $service->issue($order, trim($data['reason']), $request->user(), $data['refund_method'], isset($data['refund_reference']) ? trim($data['refund_reference']) : null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Refund recorded: order cancelled and credit note '.$note->number.' issued.');
    }

    public function member(Order $order, InvoiceService $invoices): View
    {
        abort_unless($order->member_id === Auth::guard('member')->id(), 403);

        return $this->show($order, $invoices);
    }

    public function admin(Order $order, InvoiceService $invoices): View
    {
        return $this->show($order, $invoices);
    }

    private function show(Order $order, InvoiceService $invoices): View
    {
        $note = $order->creditNote;
        abort_unless($note, 404);

        return view('invoices.show', $invoices->build($order, $note) + ['shared' => false]);
    }
}
