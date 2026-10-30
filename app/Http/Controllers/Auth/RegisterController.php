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

        // The plans people can choose from: those of their region, or every open region when the region is not decided yet.
        $plans = Plan::where('is_active', true)
            ->when($region, fn ($q) => $q->where('region_id', $region->id), fn ($q) => $q->whereIn('region_id', $regions->pluck('id')))
            ->with(['region', 'products'])->orderBy('region_id')->orderBy('price')->get();

        // Arriving through someone's invite link locks that sponsor (and leg) in for this visit; arriving without
        // one clears any earlier link so the field is free to type in.
        // The sponsor may also have chosen the plan for them. Only an active plan of the person's own region counts.
        $linkedPlan = $request->filled('plan') && ctype_digit((string) $request->query('plan'))
            ? $plans->firstWhere('id', (int) $request->query('plan'))
            : null;
        $linkedPlan && $region
            ? $request->session()->put('invited_plan', $linkedPlan->id)
            : $request->session()->forget('invited_plan');

        if ($sponsor) {
            $request->session()->put('invited_sponsor', $sponsor->member_code);
            in_array($request->query('side'), ['left', 'right'], true)
                ? $request->session()->put('invited_side', $request->query('side'))
                : $request->session()->forget('invited_side');
        } else {
            $request->session()->forget(['invited_sponsor', 'invited_side']);
        }

        return view('auth.register', [
            'sponsorLocked' => $sponsor,
            'planLocked' => $linkedPlan && $region ? $linkedPlan : null,
            'plans' => $plans,
            'selectedPlan' => (int) ($request->query('plan') ?: $request->session()->get('intended_plan_id')),
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
        // Came through an invite link: the sponsor and leg are fixed by the link, whatever the form says.
        if ($invited = $request->session()->get('invited_sponsor')) {
            $request->merge(['sponsor_code' => $invited]);
            if ($invitedSide = $request->session()->get('invited_side')) {
                $request->merge(['side' => $invitedSide]);
            }
        }
        // Same for the plan the sponsor chose in the link.
        if ($invitedPlan = $request->session()->get('invited_plan')) {
            $request->merge(['plan_id' => $invitedPlan]);
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
            'plan_id' => ['nullable', 'integer'],
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

        // Ask which plan they want. It must be an active plan of their region; a region with no plans does not ask.
        $available = Plan::where('region_id', $region->id)->where('is_active', true)->pluck('id');
        if ($available->isNotEmpty()) {
            if (empty($data['plan_id'])) {
                return back()->withInput()->withErrors(['plan_id' => 'Please choose the plan you want to buy.']);
            }
            if (! $available->contains((int) $data['plan_id'])) {
                return back()->withInput()->withErrors(['plan_id' => 'That plan is not available in the '.$region->name.' region. Please choose one from the list.']);
            }
        } else {
            $data['plan_id'] = null;
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
            'interested_plan_id' => $pending['plan_id'] ?? null,
            'phone_verified_at' => now(),
        ]);

        // Welcome the new member and tell their sponsor (each only if there is an email address to send to).
        $member->load('sponsor', 'interestedPlan');
        app(\App\Services\MemberMailer::class)->welcome($member);
        app(\App\Services\MemberMailer::class)->sponsorNewMember($member);

        // Straight to payment for the plan they chose (an unpaid order is created by the redirect below).
        if (! empty($pending['plan_id'])) {
            $request->session()->put('intended_plan_id', $pending['plan_id']);
        }

        $request->session()->forget(['pending_registration', 'invited_sponsor', 'invited_side', 'invited_plan']);

        Auth::guard('member')->login($member);
        $request->session()->regenerate();

        // The payment page greets a brand-new member once, then it is gone.
        $request->session()->flash('welcome_new_member', true);

        return $this->redirectAfterAuth($request, $member);
    }
}
