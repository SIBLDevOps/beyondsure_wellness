@extends('layouts.auth')

@section('title', 'Verify OTP — BeyondSure')

@section('content')
    <h2 class="text-lg font-semibold text-slate-900 mb-1">Enter OTP</h2>
    <p class="text-sm text-slate-500 mb-4">Enter the 6-digit code sent to {{ $phone }}.</p>

    <form method="POST" action="{{ route('login.verify.submit') }}" class="space-y-4">
        @csrf

        <div>
            <label for="otp" class="block text-sm font-medium text-slate-700">6-Digit Verification Code <span class="text-rose-500">*</span></label>
            <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" autofocus required
                class="mt-1 w-full rounded-lg border @error('otp') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-base text-center tracking-[0.5em] font-mono transition">
            @error('otp') <p class="text-xs text-red-600 mt-1 text-center">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium py-2.5">
            Verify &amp; log in
        </button>
    </form>

    <form method="POST" action="{{ route('login.resend') }}" class="mt-3">
        @csrf
        <button type="submit" class="text-sm text-emerald-600 font-medium w-full text-center">Resend OTP</button>
    </form>
@endsection
