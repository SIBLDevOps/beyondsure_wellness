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
            'orders' => Order::with(array_filter(['member.region', 'plan', 'payment', \Illuminate\Support\Facades\Schema::hasTable('credit_notes') ? 'creditNote' : null]))
                ->when($regionId, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('region_id', $regionId)))
                ->latest()->paginate(25)->withQueryString(),
            'regions' => Region::orderBy('sort_order')->get(),
            'regionId' => $regionId,
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        try {
            $this->orders->cancel($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Order cancelled.');
    }
}
