@extends('layouts.auth')

@section('title', 'Log in — BeyondSure')

@section('content')
    <h2 class="text-lg font-semibold text-slate-900 mb-1">Member Portal Login</h2>
    <p class="text-xs text-slate-500 mb-4">Sign in with your registered mobile number via OTP.</p>

    <form method="POST" action="{{ route('login.send') }}" class="space-y-4">
        @csrf

        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700">Mobile number <span class="text-rose-500">*</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="e.g. 9876543210" autofocus required
                pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric"
                class="mt-1 w-full rounded-lg border @error('phone') border-red-500 focus:border-red-500 focus:ring-red-500 @else border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 @enderror px-3.5 py-2.5 text-sm transition">
            @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 transition">
            Send OTP
        </button>
    </form>

    <p class="text-sm text-slate-500 text-center mt-4">
        New to BeyondSure?
        <a href="{{ route('register') }}" class="text-emerald-600 font-medium">Create an account</a>
    </p>
@endsection
