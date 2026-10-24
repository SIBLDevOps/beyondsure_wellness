<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Region;
use Illuminate\Http\Request;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): View
    {
        $regionId = $request->integer('region') ?: null;

        return view('admin.orders.index', [
            'orders' => Order::with('member.region', 'plan', 'payment')
                ->when($regionId, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('region_id', $regionId)))
                ->latest()->paginate(25)->withQueryString(),
            'regions' => Region::orderBy('sort_order')->get(),
            'regionId' => $regionId,
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->orders->cancel($order);

        return back()->with('status', 'Order cancelled.');
    }
}
