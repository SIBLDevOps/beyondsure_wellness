<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\RedirectsAfterMemberAuth;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Region;
use App\Models\Setting;
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
        $sponsor = $request->filled('sponsor')
            ? Member::where('member_code', strtoupper(trim((string) $request->query('sponsor'))))->first()
            : null;
        $requested = $request->filled('region') ? Region::where('code', strtoupper((string) $request->query('region')))->first() : null;
        $planRegion = Plan::whereKey($request->session()->get('intended_plan_id'))->first()?->region;

        $regions = Region::where('is_active', true)->orderBy('sort_order')->get();
        // Who decides the region: the sponsor's, else the link's, else the plan being bought, else the only one open.
        $region = $sponsor?->region ?? $requested ?? $planRegion ?? ($regions->count() === 1 ? $regions->first() : null);

        return view('auth.register', [
            'phone' => $request->query('phone'),
            'sponsor' => $request->query('sponsor'),
            'side' => in_array($request->query('side'), ['left', 'right'], true) ? $request->query('side') : null,
            'sponsorRequired' => $this->sponsorRequired($region),
            'region' => $region,
            'regions' => $regions,
            'regionFixed' => (bool) ($sponsor || $requested || $planRegion),
        ]);
    }

    /**
     * Admin setting (Settings -> registration). The very first member of a region has nobody to sponsor
     * them, so registration stays open while that region is empty — otherwise nobody could ever join.
     */
    private function sponsorRequired(?Region $region = null): bool
    {
        $hasMembers = $region ? $region->members()->exists() : Member::exists();

        return (bool) Setting::get('require_sponsor', 0) && $hasMembers;
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
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
            'sponsor_code' => ['nullable', 'string', 'exists:members,member_code'],
            'side' => ['nullable', 'in:left,right'],
            'region' => ['nullable', 'string', 'exists:regions,code'],
        ], [
            'name.required' => 'Please enter your full name.',
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'email.email' => 'Please enter a valid email address.',
            'sponsor_code.exists' => 'The sponsor code entered does not exist.',
            'region.exists' => 'The selected region does not exist.',
        ]);

        // Which region is this person joining? The sponsor's region wins; a region chosen on the form
        // or link must agree with it.
        $sponsor = ! empty($data['sponsor_code']) ? Member::with('region')->where('member_code', $data['sponsor_code'])->first() : null;
        $chosen = ! empty($data['region']) ? Region::where('code', $data['region'])->first() : null;
        $planRegion = Plan::whereKey($request->session()->get('intended_plan_id'))->first()?->region;

        if ($sponsor && $chosen && $sponsor->region_id !== $chosen->id) {
            return back()->withInput()->withErrors(['sponsor_code' => "This sponsor belongs to the {$sponsor->region->name} region, not {$chosen->name}. Use a sponsor from the same region."]);
        }

        $region = $sponsor?->region ?? $chosen ?? $planRegion
            ?? (Region::where('is_active', true)->count() === 1 ? Region::where('is_active', true)->first() : null);

        if (! $region) {
            return back()->withInput()->withErrors(['region' => 'Please choose your region.']);
        }
        if (! $region->is_active) {
            return back()->withInput()->withErrors(['region' => "The {$region->name} region is not open for new members right now."]);
        }

        // One mobile number, one region — ever. Say exactly where it already lives.
        if ($existing = Member::with('region')->where('phone', $data['phone'])->first()) {
            $where = $existing->region?->name ?? 'another region';
            $message = $existing->region_id === $region->id
                ? 'This mobile number is already registered. Please sign in instead.'
                : "This mobile number is already registered in the {$where} region. A mobile number can belong to only one region — sign in to that account instead.";

            return back()->withInput()->withErrors(['phone' => $message]);
        }

        if ($this->sponsorRequired($region) && ! $sponsor) {
            return back()->withInput()->withErrors(['sponsor_code' => "A sponsor code is required to join the {$region->name} region. Ask the person who invited you for their link."]);
        }

        $data['region_id'] = $region->id;
        unset($data['region']);

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

        $region = Region::findOrFail($pending['region_id'] ?? $sponsor?->region_id ?? Region::fallbackId());

        // Nobody can be added to a sponsor from another region (this also guards a stale session).
        if ($sponsor && $sponsor->region_id !== $region->id) {
            $request->session()->forget('pending_registration');

            return redirect()->route('register')->withErrors(['sponsor_code' => 'That sponsor is in a different region. Please start again.']);
        }

        // Registering without a sponsor places the member under their own region's root. The very
        // first member of a region becomes that region's root (no parent).
        if (! $sponsor) {
            $sponsor = $region->root();
        }

        $placement = $this->members->placeUnderSponsor($sponsor, $sponsor ? ($pending['side'] ?? null) : null);

        $member = Member::create([
            'region_id' => $region->id,
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
