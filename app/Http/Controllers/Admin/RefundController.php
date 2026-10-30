<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Every refund (credit note) in one place: when, who, how much, how it was sent back and why. Finance access only. */
class RefundController extends Controller
{
    public function index(): View
    {
        if (! Schema::hasTable('credit_notes')) {
            return view('admin.refunds.index', ['setupNeeded' => true]);
        }

        // Only refunds of orders this person may see: Order carries the region limit, the credit note itself does not.
        $query = CreditNote::query()->whereIn('order_id', Order::query()->select('id'));

        return view('admin.refunds.index', [
            'notes' => (clone $query)->with(['order' => fn ($q) => $q->withoutGlobalScopes()->with(['member' => fn ($m) => $m->withoutGlobalScopes()])])->latest('issued_on')->latest('id')->paginate(25),
            'total' => (float) (clone $query)->sum('amount'),
        ]);
    }
}
