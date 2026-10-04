<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RedirectsAfterMemberAuth;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    use RedirectsAfterMemberAuth;

    public function __construct(private readonly OtpService $otp) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('phone')) {
            $request->merge(['phone' => trim((string) $request->input('phone'))]);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
        ], [
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
        ]);

        if (! Member::where('phone', $data['phone'])->exists()) {
            return redirect()
                ->route('register', ['phone' => $data['phone']])
                ->with('status', "We couldn't find an account for {$data['phone']} — let's get you registered.");
        }

        $result = $this->otp->issue($data['phone'], 'login');

        if ($result['throttled']) {
            return back()->withErrors(['phone' => "Please wait {$result['retry_after']}s before requesting another OTP."]);
        }

        $request->session()->put('pending_login_phone', $data['phone']);

        return redirect()
            ->route('login.verify')
            ->with('debug_code', $result['debug_code']);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pending_login_phone')) {
            return redirect()->route('login');
        }

        return view('auth.login-verify', [
            'phone' => $request->session()->get('pending_login_phone'),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $phone = $request->session()->get('pending_login_phone');

        if (! $phone) {
            return redirect()->route('login');
        }

        $result = $this->otp->issue($phone, 'login');

        if ($result['throttled']) {
            return back()->withErrors(['otp' => "Please wait {$result['retry_after']}s before requesting another OTP."]);
        }

        return redirect()->route('login.verify')->with('debug_code', $result['debug_code']);
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = $request->session()->get('pending_login_phone');

        if (! $phone) {
            return redirect()->route('login');
        }

        $request->validate(['otp' => ['required', 'digits:6']], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'Verification code must be exactly 6 digits.',
        ]);

        $result = $this->otp->verify($phone, 'login', $request->input('otp'));

        if (! $result['ok']) {
            return back()->withErrors(['otp' => $result['error']]);
        }

        $member = Member::where('phone', $phone)->firstOrFail();

        $request->session()->forget('pending_login_phone');

        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $member);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
