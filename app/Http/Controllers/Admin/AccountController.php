<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()->route('admin.account.password')->with('status', 'Your password has been changed.');
    }
}
