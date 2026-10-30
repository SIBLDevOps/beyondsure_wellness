<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Site;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Each staff member switches two-factor sign-in on or off for their own account. */
class TwoFactorController extends Controller
{
    /** The two-factor columns come from a database update that may not have been run yet. */
    private function ready(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('users', 'two_factor_secret');
    }

    public function show(Request $request): View
    {
        if (! $this->ready()) {
            return view('admin.account.two-factor', ['setupNeeded' => true]);
        }

        $user = $request->user();
        $pending = ! $user->hasTwoFactor() && $user->two_factor_secret;

        return view('admin.account.two-factor', [
            'on' => $user->hasTwoFactor(),
            'secret' => $pending ? $user->two_factor_secret : null,
            'uri' => $pending ? Totp::uri($user->two_factor_secret, $user->email, Site::name()) : null,
            'recoveryCodes' => session('recovery_codes'),
            'left' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    /** Step 1: make a secret. Nothing changes about signing in until a code from the app is confirmed. */
    public function enable(Request $request): RedirectResponse
    {
        if (! $this->ready()) {
            return back()->with('error', 'Two-factor sign-in needs a database update that has not been run yet. Run: php artisan migrate --force');
        }

        $user = $request->user();

        if ($user->hasTwoFactor()) {
            return back()->with('error', 'Two-factor sign-in is already on.');
        }

        $user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();

        return redirect()->route('admin.account.two-factor');
    }

    /** Step 2: a correct code proves the app is set up. Switches it on and shows the recovery codes once. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Enter the 6-digit code from your app.']);

        if ($user->hasTwoFactor() || ! $user->two_factor_secret || ! Totp::verify($user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is not right. Check your authenticator app and try again.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();

        return redirect()->route('admin.account.two-factor')->with('recovery_codes', $codes)->with('status', 'Two-factor sign-in is on.');
    }

    /** Switching it off needs both the password and a current code, so a borrowed session cannot remove it. */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'string', 'max:20']]);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'That is not your current password.']);
        }
        if (! $user->hasTwoFactor() || ! Totp::verify($user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is not right.']);
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return back()->with('status', 'Two-factor sign-in is off.');
    }
}
