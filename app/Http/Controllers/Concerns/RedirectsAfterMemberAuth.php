<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Member;
use App\Models\Plan;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsAfterMemberAuth
{
    /**
     * If the member arrived here via the "Buy" flow (BuyController stashed a plan id in
     * session before sending them to login/register), pick that back up: place the order
     * and land them straight on the checkout/billing page instead of the dashboard.
     */
    protected function redirectAfterAuth(Request $request, Member $member): RedirectResponse
    {
        $planId = $request->session()->pull('intended_plan_id');
        $plan = $planId ? Plan::where('id', $planId)->where('is_active', true)->first() : null;

        if ($plan) {
            try {
                $order = app(OrderService::class)->place($member, $plan);

                return redirect()->route('member.orders.pay', $order);
            } catch (\DomainException $e) {
                // The plan they picked belongs to another region: take them to their own region's plans.
                return redirect()->route('member.plans.index')->withErrors(['plan' => $e->getMessage()]);
            }
        }

        return redirect()->route('member.dashboard');
    }
}
