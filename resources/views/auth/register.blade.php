@extends('layouts.auth')

@section('title', 'Register')

@section('content')
    <h2 class="text-lg font-semibold text-slate-900 mb-1">Create your account</h2>
    <p class="text-xs text-slate-500 mb-4">Join our wellness &amp; partner community.</p>

    @if ($regionClosed ?? false)
        {{-- A region only opens when the admin has created its root, so there is nothing to sign up to yet. --}}
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <div class="font-semibold mb-1">This region has not been opened yet</div>
            Sign-ups open once the company has started the region. Please contact the company, or ask the person who invited you for their invite link.
        </div>
        @error('region') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
    @else
    <form method="POST" action="{{ route('register.send') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Full name <span class="text-rose-500">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Enter your full name" required
                class="mt-1 w-full rounded-lg border @error('name') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-sm transition">
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700">Mobile number <span class="text-rose-500">*</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $phone ?? '') }}" placeholder="10-digit mobile number" required
                pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric"
                class="mt-1 w-full rounded-lg border @error('phone') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-sm transition">
            @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email <span class="text-slate-400">(optional)</span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com"
                class="mt-1 w-full rounded-lg border @error('email') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-sm transition">
            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Region: fixed by the invite link / plan, or chosen here when more than one region is open --}}
        @if (($regionFixed ?? false) && ($region ?? null))
            <input type="hidden" name="region" value="{{ $region->code }}">
            <p class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 border border-sky-200 px-3 py-1 text-xs font-medium text-sky-700">
                You are joining the <strong>{{ $region->name }}</strong> region
            </p>
        @elseif (($regions ?? collect())->count() > 1)
            <div>
                <label for="region" class="block text-sm font-medium text-slate-700">Region <span class="text-rose-500">*</span></label>
                <select id="region" name="region" required
                    class="mt-1 w-full rounded-lg border @error('region') border-red-500 @else border-slate-300 @enderror px-3.5 py-2.5 text-sm bg-white">
                    <option value="">Choose your region</option>
                    @foreach ($regions as $r)
                        <option value="{{ $r->code }}" @selected(old('region', $region?->code) === $r->code)>{{ $r->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">Each region has its own network and plans. A mobile number can belong to one region only.</p>
            </div>
        @elseif ($region ?? null)
            <input type="hidden" name="region" value="{{ $region->code }}">
        @endif
        @error('region') <p class="text-xs text-red-600 -mt-2">{{ $message }}</p> @enderror

        <div>
            <label for="sponsor_code" class="block text-sm font-medium text-slate-700">Sponsor code
                @if ($sponsorRequired ?? false)
                    <span class="text-rose-500">*</span>
                @else
                    <span class="text-slate-400">(optional)</span>
                @endif
            </label>
            @php $joinSide = old('side', $side ?? null); @endphp
            @if ($joinSide)
                <input type="hidden" name="side" value="{{ $joinSide }}">
                <p class="mt-1 mb-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-medium text-emerald-700">
                    You are joining your sponsor's <strong class="uppercase">{{ $joinSide }}</strong> leg
                </p>
            @endif
            @if ($sponsorLocked ?? null)
                {{-- Came through an invite link: the sponsor is fixed by the link and cannot be changed here. --}}
                <input id="sponsor_code" name="sponsor_code" type="text" value="{{ $sponsorLocked->member_code }}" readonly aria-readonly="true"
                    class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-100 text-slate-600 cursor-not-allowed px-3.5 py-2.5 text-sm uppercase font-mono">
                <p class="text-xs text-slate-500 mt-1"><i class="bi bi-lock-fill"></i> Invited by <strong>{{ $sponsorLocked->name }}</strong> ({{ $sponsorLocked->member_code }}). This comes from the link you opened and cannot be changed.</p>
            @else
            <input id="sponsor_code" name="sponsor_code" type="text" value="{{ old('sponsor_code', $sponsor ?? '') }}" placeholder="e.g. BSW-1001" @required($sponsorRequired ?? false)
                class="mt-1 w-full rounded-lg border @error('sponsor_code') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-sm uppercase font-mono transition">
            @if ($sponsorRequired ?? false)
                <p class="text-xs text-slate-500 mt-1">A sponsor code is required. Use the invite link your sponsor shared with you.</p>
            @else
                <p class="text-xs text-slate-500 mt-1">Leave blank to register directly under the company in your region.</p>
            @endif
            @endif
            @error('sponsor_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Which plan? The person pays for it right after the OTP; they count in a team only once the payment is approved. --}}
        @if ($planLocked ?? null)
            <div>
                <span class="block text-sm font-medium text-slate-700">Your plan</span>
                <input type="hidden" name="plan_id" value="{{ $planLocked->id }}">
                <div class="mt-1 flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2.5 text-sm text-slate-700">
                    <span class="font-medium">{{ $planLocked->name }}</span>
                    <span>₹{{ number_format($planLocked->totalPayable()) }} <span class="text-xs text-slate-500">incl. GST</span></span>
                </div>
                <p class="text-xs text-slate-500 mt-1"><i class="bi bi-lock-fill"></i> Chosen by your sponsor. You will pay for it right after verifying your mobile number.</p>
                @error('plan_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @elseif (($plans ?? collect())->isNotEmpty())
            <div>
                <label for="plan_id" class="block text-sm font-medium text-slate-700">Which plan do you want to buy? <span class="text-slate-400">(optional)</span></label>
                <select id="plan_id" name="plan_id"
                    class="mt-1 w-full rounded-lg border @error('plan_id') border-red-500 @else border-slate-300 @enderror px-3.5 py-2.5 text-sm bg-white">
                    <option value="">Decide later — I will choose after I register</option>
                    @foreach ($plans as $p)
                        <option value="{{ $p->id }}" @selected((int) old('plan_id', $selectedPlan ?? 0) === $p->id)>{{ $p->name }} — ₹{{ number_format($p->totalPayable()) }} (incl. GST){{ ($regions ?? collect())->count() > 1 && ! ($region ?? null) ? ' · '.$p->region?->name : '' }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">Pick a plan to go straight to payment after verifying your mobile number, or leave it and choose from the plans once you are in. You become an active member when your first payment is approved.</p>
                @error('plan_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <button type="submit"
            class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 transition">
            Send OTP
        </button>
    </form>
    @endif

    <p class="text-sm text-slate-500 text-center mt-4">
        Already have an account?
        <a href="{{ route('login') }}" class="text-emerald-600 font-medium">Log in</a>
    </p>
@endsection
