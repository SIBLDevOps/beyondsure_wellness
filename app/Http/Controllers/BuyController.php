<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuyController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Entry point for every "Buy" / "Choose plan" button on the public site.
     * Logged in already → straight to checkout. Not logged in → remember the intended
     * plan in session and send them to login; LoginController/RegisterController pick
     * this back up after a successful OTP verify and land them on checkout instead of
     * the dashboard.
     */
    public function start(Request $request, Plan $plan): RedirectResponse
    {
        if (! $plan->is_active) {
            abort(404);
        }

        if (Auth::guard('member')->check()) {
            $order = $this->orders->place(Auth::guard('member')->user(), $plan);

            return redirect()->route('member.orders.pay', $order);
        }

        $request->session()->put('intended_plan_id', $plan->id);

        return redirect()->route('login')->with('status', "Log in to continue with the {$plan->name} plan.");
    }
}
