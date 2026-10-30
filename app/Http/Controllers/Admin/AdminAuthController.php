<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Please enter your admin email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ]);

        // Check the password without signing in yet: with two-factor on, the code comes first.
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials(['email' => $credentials['email']]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => $credentials['password']])) {
            AuditLog::record('admin.login.failed', $credentials['email'], 'POST', '/admin/login', [], null, $request->ip());

            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        if (! $user->isAdminStaff()) {
            AuditLog::record('admin.login.failed', $credentials['email'].' (no admin access)', 'POST', '/admin/login', [], null, $request->ip());

            return back()->withErrors(['email' => 'This account does not have admin access.']);
        }

        if ($user->hasTwoFactor()) {
            $request->session()->regenerate();
            $request->session()->put('two_factor', ['user_id' => $user->id, 'at' => now()->timestamp]);

            return redirect()->route('admin.two-factor.challenge');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        AuditLog::record('admin.login', $user->email, 'POST', '/admin/login', [], $user, $request->ip());

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
