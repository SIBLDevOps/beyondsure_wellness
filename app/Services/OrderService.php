<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Support\Gst;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * The plan's price is the taxable base (v2 model: "Package Tiers, MRP excl. GST") — GST is
     * added on top here, once, at the moment of sale, and snapshotted onto the order. A later
     * change to the plan's or a product's GST rate must never retroactively change what this
     * order already charged.
     */
    public function place(Member $member, Plan $plan): Order
    {
        $split = Gst::splitForPlan($plan, (float) $plan->price);

        // v2 §2B: New vs. Repurchase is a classification only — Self/Sponsor/Matching pay the
        // same rate either way. "Repurchase" = this member has at least one prior completed
        // (paid/settled) order; a never-completed pending or cancelled attempt doesn't count as
        // having transacted before.
        $isRepurchase = Order::where('member_id', $member->id)->whereIn('status', ['paid', 'settled'])->exists();

        return Order::create([
            'order_code' => 'ORD-'.strtoupper(Str::random(8)),
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'amount' => $split['total'],
            'taxable_amount' => $split['taxable'],
            'gst_amount' => $split['gst'],
            'bv' => $plan->bv,
            'status' => 'pending_payment',
            'is_repurchase' => $isRepurchase,
        ]);
    }

    /** Refuses to touch a settled order — reversing settled income is out of scope (see spec §6). */
    public function cancel(Order $order): void
    {
        if ($order->status === 'settled') {
            throw new \RuntimeException('Cannot cancel an order that has already been settled into a cycle.');
        }

        $order->update(['status' => 'cancelled']);
    }
}
