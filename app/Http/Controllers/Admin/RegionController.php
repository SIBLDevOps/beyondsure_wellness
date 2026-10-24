<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(): View
    {
        $regions = Region::query()
            ->withCount(['members', 'plans'])
            ->withCount(['members as active_members_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->each(function (Region $r) {
                $r->setRelation('rootMember', $r->root());
                $r->orders_count = Order::whereIn('status', ['paid', 'settled'])
                    ->whereHas('member', fn ($q) => $q->where('region_id', $r->id))->count();
            });

        return view('admin.regions.index', ['regions' => $regions]);
    }

    public function create(): View
    {
        return view('admin.regions.form', ['region' => new Region(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['sort_order'] = (int) Region::max('sort_order') + 1;
        Region::create($data);

        return redirect()->route('admin.regions.index')->with('status', 'Region created. Add plans for it, then share its invite link.');
    }

    public function edit(Region $region): View
    {
        return view('admin.regions.form', ['region' => $region->loadCount(['members', 'plans'])]);
    }

    public function update(Request $request, Region $region): RedirectResponse
    {
        $data = $this->validated($request, $region);

        // The code is printed in invite links that members already hold; it can't change once people joined.
        if ($region->members()->exists()) {
            unset($data['code']);
        }

        $region->update($data);

        return redirect()->route('admin.regions.index')->with('status', 'Region updated.');
    }

    private function validated(Request $request, ?Region $region): array
    {
        $request->merge(['code' => strtoupper(Str::slug((string) $request->input('code', $request->input('name')), ''))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('regions', 'name')->ignore($region?->id)],
            'code' => ['required', 'string', 'max:20', Rule::unique('regions', 'code')->ignore($region?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'A region with this name already exists.',
            'code.unique' => 'This region code is already used.',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
