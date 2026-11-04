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

    /**
     * Starts the region: creates the member at the top of its tree. This is the only way a root comes to exist, so a
     * stranger can never take the seat that earns on every match in the region.
     */
    public function root(Request $request, Region $region, \App\Services\MemberService $members): RedirectResponse
    {
        if ($request->filled('phone')) {
            $request->merge(['phone' => trim((string) $request->input('phone'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
        ], [
            'name.required' => 'Enter the root member\'s full name.',
            'phone.required' => 'Enter their mobile number.',
            'phone.regex' => 'Enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'That email address is already used by another member.',
        ]);

        try {
            $root = $members->createRoot($region, $data['name'], $data['phone'], $data['email'] ?? null);
        } catch (\DomainException $e) {
            $field = str_contains($e->getMessage(), 'mobile') ? 'phone' : 'root';

            return back()->withInput()->withErrors([$field => $e->getMessage()]);
        }

        // They are told by email if the admin gave an address; otherwise the admin tells them.
        app(\App\Services\MemberMailer::class)->welcome($root);

        return back()->with('status', "{$root->name} is now the root of the {$region->name} region. They sign in with their mobile number (OTP), choose a plan and activate. Everyone else joins through member invite links.");
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
