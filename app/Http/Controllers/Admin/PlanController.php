<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $regions = Region::orderBy('sort_order')->get();
        $regionId = $request->filled('region') ? (int) $request->query('region') : null;
        $plans = Plan::with(['products', 'region'])
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderBy('region_id')->orderBy('price')->get();

        // Costs, BV, margins and the payout matrix are money-flow figures: only people with Finance
        // access get the full page. Everyone else gets a plain read-only list.
        if (! auth()->user()->canSeeFinance()) {
            return view('admin.plans.basic', compact('plans', 'regions', 'regionId'));
        }

        return view('admin.plans.index', [
            'plans' => $plans,
            'regions' => $regions,
            'regionId' => $regionId,
            'costPct' => (float) Setting::get('split_cost_pct', 40),
            'poolPct' => (float) Setting::get('split_pool_pct', 40),
            'companyPct' => (float) Setting::get('split_company_pct', 20),
            'selfPct' => (float) Setting::get('self_pct', 10),
            'sponsorPct' => (float) Setting::get('sponsor_pct', 20),
            'matchingPct' => (float) Setting::get('matching_pct', 10),
            'cascadeDepth' => (int) Setting::get('cascade_depth', 7),
            'levelPcts' => Setting::getLevelPercentages(),
            'rankPoolPct' => (float) Setting::get('rank_pool_pct', 3),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', [
            'plan' => new Plan,
            'regions' => Region::orderBy('sort_order')->get(),
            'products' => Product::orderBy('name')->get(),
            'settings' => $this->compensationSettings(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        $plan = Plan::create($data);
        $this->syncProducts($request, $plan);

        return redirect()->route('admin.plans.index')->with('status', 'Plan created.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', [
            'plan' => $plan->load('products'),
            'regions' => Region::orderBy('sort_order')->get(),
            'products' => Product::orderBy('name')->get(),
            'settings' => $this->compensationSettings(),
        ]);
    }

    private function compensationSettings(): array
    {
        return [
            'costPct' => (float) Setting::get('split_cost_pct', 40),
            'poolPct' => (float) Setting::get('split_pool_pct', 40),
            'companyPct' => (float) Setting::get('split_company_pct', 20),
            'selfPct' => (float) Setting::get('self_pct', 10),
            'sponsorPct' => (float) Setting::get('sponsor_pct', 20),
            'matchingPct' => (float) Setting::get('matching_pct', 10),
            'rankPoolPct' => (float) Setting::get('rank_pool_pct', 3),
        ];
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request);

        // Orders already placed on this plan belong to its region; moving it would strand them.
        if ((int) $data['region_id'] !== (int) $plan->region_id && $plan->orders()->exists()) {
            return back()->withInput()->withErrors(['region_id' => 'This plan already has orders, so its region cannot be changed.']);
        }

        $plan->update($data);
        $this->syncProducts($request, $plan);

        return redirect()->route('admin.plans.index')->with('status', 'Plan updated.');
    }

    private function syncProducts(Request $request, Plan $plan): void
    {
        $qtys = $request->input('qty', []);
        $sync = [];

        foreach ($qtys as $productId => $qty) {
            if ((int) $qty > 0) {
                $sync[$productId] = ['qty' => (int) $qty];
            }
        }

        $plan->products()->sync($sync);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'region_id' => ['required', 'exists:regions,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'bv' => ['required', 'numeric', 'min:0'],
        ], [
            'region_id.required' => 'Choose the region this plan is for.',
            'name.required' => 'Plan bundle name is required.',
            'price.required' => 'Plan price is required.',
            'price.numeric' => 'Plan price must be a valid number.',
            'price.min' => 'Plan price cannot be negative.',
            'bv.required' => 'Business Volume (BV) is required.',
            'bv.numeric' => 'Business Volume (BV) must be a valid number.',
            'bv.min' => 'Business Volume (BV) cannot be negative.',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // A region-restricted sub-admin can only work inside the regions they were given.
        abort_unless(\App\Models\Region::whereKey($data['region_id'])->exists(), 403, 'You do not have access to that region.');

        return $data;
    }
}
