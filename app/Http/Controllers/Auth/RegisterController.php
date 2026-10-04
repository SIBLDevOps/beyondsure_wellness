<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RedirectsAfterMemberAuth;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    use RedirectsAfterMemberAuth;

    public function __construct(
        private readonly OtpService $otp,
        private readonly MemberService $members,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.register', [
            'phone' => $request->query('phone'),
            'sponsor' => $request->query('sponsor'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('phone')) {
            $request->merge(['phone' => trim((string) $request->input('phone'))]);
        }
        if ($request->filled('sponsor_code')) {
            $request->merge(['sponsor_code' => strtoupper(trim((string) $request->input('sponsor_code')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
            'sponsor_code' => ['nullable', 'string', 'exists:members,member_code'],
        ], [
            'name.required' => 'Please enter your full name.',
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'phone.unique' => 'This mobile number is already registered. Please sign in instead.',
            'email.email' => 'Please enter a valid email address.',
            'sponsor_code.exists' => 'The sponsor code entered does not exist.',
        ]);

        $result = $this->otp->issue($data['phone'], 'register');

        if ($result['throttled']) {
            return back()->withErrors(['phone' => "Please wait {$result['retry_after']}s before requesting another OTP."]);
        }

        $request->session()->put('pending_registration', $data);

        return redirect()
            ->route('register.verify')
            ->with('debug_code', $result['debug_code']);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pending_registration')) {
            return redirect()->route('register');
        }

        return view('auth.register-verify', [
            'phone' => $request->session()->get('pending_registration')['phone'],
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('pending_registration');

        if (! $pending) {
            return redirect()->route('register');
        }

        $result = $this->otp->issue($pending['phone'], 'register');

        if ($result['throttled']) {
            return back()->withErrors(['otp' => "Please wait {$result['retry_after']}s before requesting another OTP."]);
        }

        return redirect()->route('register.verify')->with('debug_code', $result['debug_code']);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('pending_registration');

        if (! $pending) {
            return redirect()->route('register');
        }

        $request->validate(['otp' => ['required', 'digits:6']], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.digits' => 'Verification code must be exactly 6 digits.',
        ]);

        $result = $this->otp->verify($pending['phone'], 'register', $request->input('otp'));

        if (! $result['ok']) {
            return back()->withErrors(['otp' => $result['error']]);
        }

        $sponsor = ! empty($pending['sponsor_code'])
            ? Member::where('member_code', $pending['sponsor_code'])->first()
            : null;

        // When registering directly without a sponsor, place under the company admin/root account
        if (! $sponsor) {
            $sponsor = Member::whereNull('placement_id')->orderBy('id')->first()
                ?? Member::orderBy('id')->first();
        }

        $placement = $this->members->placeUnderSponsor($sponsor);

        $member = Member::create([
            'member_code' => $this->members->generateMemberCode(),
            'name' => $pending['name'],
            'phone' => $pending['phone'],
            'email' => $pending['email'] ?? null,
            'sponsor_id' => $sponsor?->id,
            'placement_id' => $placement['placement_id'],
            'position' => $placement['position'],
            'path' => $placement['path'],
            'status' => 'inactive',
            'phone_verified_at' => now(),
        ]);

        $request->session()->forget('pending_registration');

        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $member);
    }
}
