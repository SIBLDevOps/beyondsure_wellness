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
            // A cycle covers every region at once, so its pool and payout totals are company-wide. Someone limited
            // to some regions must not see figures that include regions they cannot open.
            'hideTotals' => auth()->user()->isRegionRestricted(),
            // Nothing can be created until the compensation settings have been saved and make sense.
            'settingsProblems' => \App\Support\CompensationSettings::problems(),
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

        try {
            $this->cycles->create($data['period_start'], $data['period_end']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['cycle' => $e->getMessage()]);
        }

        return back()->with('status', 'Cycle created as draft.');
    }

    public function approve(Cycle $cycle): RedirectResponse
    {
        try {
            $this->cycles->approve($cycle, Auth::user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['cycle' => $e->getMessage()]);
        }

        if (! auth()->user()->canSeeFinance()) {
            return back()->with('status', 'Cycle approved — income has been posted to members.');
        }

        return back()->with('status', "Cycle approved — pool ₹{$cycle->fresh()->pool_bv}, paid ₹{$cycle->fresh()->total_paid}.");
    }
}
