<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\Order;
use App\Services\CycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CycleController extends Controller
{
    public function __construct(private readonly CycleService $cycles) {}

    public function index(): View
    {
        return view('admin.cycles.index', [
            'cycles' => Cycle::orderByDesc('period_end')->paginate(15),
            'unsettledCount' => Order::where('status', 'paid')->whereNull('cycle_id')->count(),
            'unsettledBv' => Order::where('status', 'paid')->whereNull('cycle_id')->sum('bv'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ], [
            'period_start.required' => 'Period start date is required.',
            'period_start.date' => 'Period start must be a valid date.',
            'period_end.required' => 'Period end date is required.',
            'period_end.date' => 'Period end must be a valid date.',
            'period_end.after_or_equal' => 'Period end date must be on or after the period start date.',
        ]);

        $this->cycles->create($data['period_start'], $data['period_end']);

        return back()->with('status', 'Cycle created as draft.');
    }

    public function approve(Cycle $cycle): RedirectResponse
    {
        $this->cycles->approve($cycle, Auth::user());

        return back()->with('status', "Cycle approved — pool ₹{$cycle->fresh()->pool_bv}, paid ₹{$cycle->fresh()->total_paid}.");
    }
}
