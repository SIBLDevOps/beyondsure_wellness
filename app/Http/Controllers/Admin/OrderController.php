<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(): View
    {
        return view('admin.orders.index', [
            'orders' => Order::with('member', 'plan', 'payment')->latest()->paginate(25),
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->orders->cancel($order);

        return back()->with('status', 'Order cancelled.');
    }
}
