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
            // If the email belongs to a real account, the line says whose (a wrong password on a known account matters more).
            AuditLog::record('admin.login.failed', $credentials['email'], 'POST', '/admin/login', [], $user instanceof \App\Models\User ? $user : null, $request->ip(), null, 'failed');

            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        if (! $user->isAdminStaff()) {
            AuditLog::record('admin.login.failed', $credentials['email'].' (no admin access)', 'POST', '/admin/login', [], null, $request->ip(), null, 'failed');

            return back()->withErrors(['email' => 'This account does not have admin access.']);
        }

        // Signing in with a code texted to the staff member's mobile (when switched on and they have a mobile saved).
        if (config('otp.admin.enabled') && $user->phone) {
            $result = app(\App\Services\OtpService::class)->issue($user->phone, 'admin_login', $user->name);
            $request->session()->regenerate();
            $request->session()->put('admin_otp', ['user_id' => $user->id, 'at' => now()->timestamp]);

            $redirect = redirect()->route('admin.otp.challenge');

            return $result['throttled']
                ? $redirect->withErrors(['otp' => "A code was sent a moment ago. Use it, or ask for a new one in {$result['retry_after']}s."])
                : $redirect->with('debug_code', $result['debug_code']);
        }

        if ($user->hasTwoFactor()) {
            $request->session()->regenerate();
            $request->session()->put('two_factor', ['user_id' => $user->id, 'at' => now()->timestamp]);

            return redirect()->route('admin.two-factor.challenge');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        AuditLog::record('admin.login', $user->email, 'POST', '/admin/login', [], $user, $request->ip());

        // The code step is on but there is no mobile to text: sign in, and ask them to add one.
        if (config('otp.admin.enabled') && ! $user->phone) {
            return redirect()->route('admin.dashboard')->with('status', 'Add your mobile number (Account, Change password page) to receive a sign-in code by SMS.');
        }

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Written before signing out, while we still know who it is.
        if ($user = $request->user('web')) {
            AuditLog::record('admin.logout', $user->email, 'POST', '/admin/logout', [], $user, $request->ip());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
