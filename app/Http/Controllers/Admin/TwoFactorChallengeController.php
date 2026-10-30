<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** The second step of signing in: the 6-digit code from the authenticator app, or one of the saved recovery codes. */
class TwoFactorChallengeController extends Controller
{
    private const WINDOW_SECONDS = 600;

    /** The person who passed the password step, or null if there is none (or it took too long). */
    private function pending(Request $request): ?User
    {
        $state = $request->session()->get('two_factor');

        if (! is_array($state) || now()->timestamp - ($state['at'] ?? 0) > self::WINDOW_SECONDS) {
            $request->session()->forget('two_factor');

            return null;
        }

        return User::find($state['user_id']);
    }

    public function create(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('admin.two-factor-challenge') : redirect()->route('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:30']]);

        $ok = false;
        $field = 'code';

        if ($user->isAdminStaff() && $user->hasTwoFactor()) {
            if ($request->filled('recovery_code')) {
                $field = 'recovery_code';
                $ok = $this->useRecoveryCode($user, (string) $request->input('recovery_code'));
            } else {
                $ok = Totp::verify($user->two_factor_secret, (string) $request->input('code', ''));
            }
        }

        if (! $ok) {
            return back()->withErrors([$field => $field === 'code' ? 'That code is not right. Check your authenticator app and try again.' : 'That recovery code is not valid, or was already used.']);
        }

        $request->session()->forget('two_factor');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        AuditLog::record('admin.login', $user->email.($field === 'recovery_code' ? ' (recovery code)' : ' (two-factor)'), 'POST', '/admin/two-factor-challenge', [], $user, $request->ip());

        return redirect()->route('admin.dashboard');
    }

    /** Each recovery code works once: the matching hash is removed. */
    private function useRecoveryCode(User $user, string $code): bool
    {
        $code = strtolower(trim($code));
        $hashes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
