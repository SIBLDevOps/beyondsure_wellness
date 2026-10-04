<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'BeyondSure')</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-ink-50 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <a href="{{ route('home') }}" class="text-2xl font-bold text-ink-900">BeyondSure<span class="text-brand-600">+</span></a>
            <p class="text-sm text-ink-500 mt-1">Wellness &amp; Protection Plans</p>
        </div>

        <div class="bg-white shadow-sm rounded-2xl border border-ink-100 p-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-brand-50 border border-brand-200 text-brand-700 text-sm px-3 py-2">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('debug_code'))
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-3 py-2">
                    Dev mode — no SMS gateway configured. Your OTP is
                    <span class="font-mono font-semibold">{{ session('debug_code') }}</span>.
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </div>

        <p class="text-center text-xs text-ink-400 mt-6">
            <a href="{{ route('home') }}" class="hover:text-brand-600">&larr; Back to home</a>
        </p>
    </div>
</body>
</html>
