<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Every signed-in staff member (Super Admin or sub-admin) can change their own password here.
 * It never needs a permission, and it only ever touches the signed-in person's own account.
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.password');
    }

    /** Saves the signed-in person's own mobile number, the one their sign-in code is texted to. Needs their password. */
    public function mobile(Request $request): RedirectResponse
    {
        $request->merge(['phone' => preg_replace('/\s+/', '', (string) $request->input('phone'))]);

        $data = $request->validate([
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($request->user()->id)],
            'current_password' => ['required', 'current_password:web'],
        ], [
            'phone.required' => 'Enter your mobile number.',
            'phone.regex' => 'Enter a valid 10-digit mobile number starting with 6, 7, 8 or 9.',
            'phone.unique' => 'That mobile number already belongs to another staff member.',
            'current_password.required' => 'Enter your current password to change your mobile number.',
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $request->user()->update(['phone' => $data['phone']]);

        return redirect()->route('admin.account.password')->with('status', 'Your mobile number is saved. Sign-in codes are texted to it.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'That is not your current password.',
            'password.required' => 'Enter a new password.',
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Choose a password different from your current one.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.letters' => 'The new password must include at least one letter.',
            'password.numbers' => 'The new password must include at least one number.',
        ]);

        $request->user()->update(['password' => $data['password']]);
        \App\Support\Sessions::endOthersFor($request->user(), $request->session()->getId());

        return redirect()->route('admin.account.password')->with('status', 'Your password has been changed.');
    }
}
