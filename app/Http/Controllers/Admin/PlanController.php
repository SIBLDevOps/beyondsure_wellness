<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::with('products')->orderBy('price')->get(),
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
        $plan->update($this->validated($request));
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'bv' => ['required', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Plan bundle name is required.',
            'price.required' => 'Plan price is required.',
            'price.numeric' => 'Plan price must be a valid number.',
            'price.min' => 'Plan price cannot be negative.',
            'bv.required' => 'Business Volume (BV) is required.',
            'bv.numeric' => 'Business Volume (BV) must be a valid number.',
            'bv.min' => 'Business Volume (BV) cannot be negative.',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
