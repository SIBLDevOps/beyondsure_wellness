<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The step after the password: the 6-digit code texted to the staff member's mobile. */
class AdminOtpChallengeController extends Controller
{
    private const WINDOW_SECONDS = 600;

    public function __construct(private readonly OtpService $otp) {}

    /** The person who passed the password step, or null (none, or it took too long, or their account changed). */
    private function pending(Request $request): ?User
    {
        $state = $request->session()->get('admin_otp');

        if (! is_array($state) || now()->timestamp - ($state['at'] ?? 0) > self::WINDOW_SECONDS) {
            $request->session()->forget('admin_otp');

            return null;
        }

        $user = User::find($state['user_id']);

        return ($user && $user->phone && $user->isAdminStaff()) ? $user : null;
    }

    private function masked(User $user): string
    {
        return substr($user->phone, 0, 2).'******'.substr($user->phone, -2);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->pending($request);

        return $user ? view('admin.otp-challenge', ['masked' => $this->masked($user)]) : redirect()->route('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        $data = $request->validate(['otp' => ['required', 'string', 'max:10']], ['otp.required' => 'Enter the 6-digit code.']);
        $check = $this->otp->verify($user->phone, 'admin_login', trim($data['otp']));

        if (! $check['ok']) {
            AuditLog::record('admin.login.failed', $user->email.' (wrong sign-in code)', 'POST', '/admin/otp-challenge', [], $user, $request->ip(), null, 'failed');

            return back()->withErrors(['otp' => $check['error']]);
        }

        $request->session()->forget('admin_otp');

        // The authenticator app code, if they use one, still comes after the SMS code.
        if ($user->hasTwoFactor()) {
            $request->session()->put('two_factor', ['user_id' => $user->id, 'at' => now()->timestamp]);

            return redirect()->route('admin.two-factor.challenge');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        AuditLog::record('admin.login', $user->email.' (sms code)', 'POST', '/admin/otp-challenge', [], $user, $request->ip());

        return redirect()->route('admin.dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        $result = $this->otp->issue($user->phone, 'admin_login', $user->name);

        if ($result['throttled']) {
            return back()->withErrors(['otp' => "Please wait {$result['retry_after']}s before asking for another code."]);
        }

        return redirect()->route('admin.otp.challenge')->with('debug_code', $result['debug_code'])->with('status', 'A new code was sent.');
    }
}
