@extends('layouts.auth')

@section('title', 'Register — BeyondSure')

@section('content')
    <h2 class="text-lg font-semibold text-slate-900 mb-1">Create your account</h2>
    <p class="text-xs text-slate-500 mb-4">Join the BeyondSure+ wellness &amp; partner community.</p>

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

        <div>
            <label for="sponsor_code" class="block text-sm font-medium text-slate-700">Sponsor code <span class="text-slate-400">(optional)</span></label>
            <input id="sponsor_code" name="sponsor_code" type="text" value="{{ old('sponsor_code', $sponsor ?? '') }}" placeholder="e.g. BSW-1001"
                class="mt-1 w-full rounded-lg border @error('sponsor_code') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-sm uppercase font-mono transition">
            <p class="text-xs text-slate-500 mt-1">Leave blank to register directly under company admin downline.</p>
            @error('sponsor_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 transition">
            Send OTP
        </button>
    </form>

    <p class="text-sm text-slate-500 text-center mt-4">
        Already have an account?
        <a href="{{ route('login') }}" class="text-emerald-600 font-medium">Log in</a>
    </p>
@endsection
