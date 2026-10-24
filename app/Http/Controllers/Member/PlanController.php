<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(): View
    {
        $member = Auth::guard('member')->user();

        return view('member.plans', [
            'plans' => Plan::where('is_active', true)->where('region_id', $member->region_id)->with('products')->orderBy('price')->get(),
            'region' => $member->region,
        ]);
    }

    public function buy(Plan $plan): RedirectResponse
    {
        try {
            $order = $this->orders->place(Auth::guard('member')->user(), $plan);
        } catch (\DomainException $e) {
            return redirect()->route('member.plans.index')->withErrors(['plan' => $e->getMessage()]);
        }

        return redirect()->route('member.orders.pay', $order)->with('status', 'Order placed — submit your payment to continue.');
    }
}
