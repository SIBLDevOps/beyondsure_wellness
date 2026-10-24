<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin only (enforced by EnsureAdmin through the 'admin.team.' route prefix, which can never be
 * granted). Only sub-admins are managed here — a Super Admin account can't be edited or removed from
 * this screen, and a sub-admin can never create or promote anyone.
 */
class TeamController extends Controller
{
    public function index(): View
    {
        return view('admin.team.index', [
            'subAdmins' => User::where('role', 'sub_admin')->orderBy('name')->get(),
            'superAdmins' => User::where('role', 'admin')->orderBy('name')->get(),
            'groups' => AdminPermissions::GROUPS,
        ]);
    }

    public function create(): View
    {
        return view('admin.team.form', ['subAdmin' => new User(['is_active' => true, 'permissions' => []]), 'groups' => AdminPermissions::GROUPS, 'regions' => Region::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        User::create($data + ['role' => 'sub_admin']);

        return redirect()->route('admin.team.index')->with('status', "Sub-admin {$data['name']} created. They sign in at the normal admin login.");
    }

    public function edit(User $user): View
    {
        abort_unless($user->role === 'sub_admin', 404);

        return view('admin.team.form', ['subAdmin' => $user, 'groups' => AdminPermissions::GROUPS, 'regions' => Region::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === 'sub_admin', 404);

        $data = $this->validated($request, $user->id);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.team.index')->with('status', "Access for {$user->name} saved. It applies on their next page load.");
    }

    /**
     * Passwords are stored as one-way hashes, so nobody — including the Super Admin — can read an
     * existing one. What the Super Admin can do is set a fresh one and see it once to hand over.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        abort_unless($user->role === 'sub_admin', 404);

        $password = Str::password(12, symbols: false);
        $user->update(['password' => $password]);

        return redirect()->route('admin.team.edit', $user)
            ->with('new_password', ['name' => $user->name, 'email' => $user->email, 'password' => $password]);
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->role === 'sub_admin', 404);

        $user->delete();

        return redirect()->route('admin.team.index')->with('status', 'Sub-admin removed.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'password' => [$ignoreId ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::keys())],
            'region_access' => ['nullable', 'in:all,selected'],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer', 'exists:regions,id'],
        ], [
            'region_ids.*.exists' => 'Unknown region.',
            'password.required' => 'Set a password for the new sub-admin (at least 8 characters).',
            'permissions.*.in' => 'Unknown permission.',
        ]);

        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $data['is_active'] = $request->boolean('is_active');

        // "All regions" = no restriction (null); "selected" needs at least one region ticked.
        $selected = array_values(array_unique(array_map('intval', $data['region_ids'] ?? [])));
        if (($data['region_access'] ?? 'all') === 'selected') {
            if (! $selected) {
                throw \Illuminate\Validation\ValidationException::withMessages(['region_ids' => 'Tick at least one region, or choose "All regions".']);
            }
            $data['region_ids'] = $selected;
        } else {
            $data['region_ids'] = null;
        }
        unset($data['region_access']);

        return $data;
    }
}
