<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Enter your code — {{ Site::name() }}</title>
    @include('partials.favicon')
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f172a; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #1f2937; padding: 16px; }
        .card { background: #fff; width: 100%; max-width: 400px; border-radius: 14px; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,.35); }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p { color: #6b7280; margin: 0 0 16px; font-size: 14px; line-height: 1.5; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        input { width: 100%; box-sizing: border-box; font: inherit; font-size: 20px; letter-spacing: 4px; text-align: center; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; }
        button { width: 100%; margin-top: 14px; padding: 11px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button.link { background: none; color: #4f46e5; margin-top: 8px; padding: 6px; font-weight: 500; }
        .err { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; font-size: 14px; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; font-size: 14px; }
        .dev { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; font-size: 13px; }
        a { color: #6b7280; font-size: 13px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Enter the code we texted you</h1>
        <p>We sent a 6-digit code to <strong>{{ $masked }}</strong>. It is valid for 5 minutes.</p>

        @if (session('debug_code'))
            <div class="dev" role="status">Dev mode, no SMS needed: your code is <strong>{{ session('debug_code') }}</strong>.</div>
        @endif
        @if (session('status'))<div class="ok" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('admin.otp.verify') }}" autocomplete="off">
            @csrf
            <label for="otp">6-digit code</label>
            <input id="otp" name="otp" inputmode="numeric" maxlength="6" autofocus autocomplete="one-time-code" placeholder="000000" required>
            <button type="submit">Confirm and sign in</button>
        </form>

        <form method="POST" action="{{ route('admin.otp.resend') }}">
            @csrf
            <button type="submit" class="link">Did not get it? Send a new code</button>
        </form>

        <p style="margin:18px 0 0"><a href="{{ route('admin.login') }}">&larr; Back to sign in</a></p>
    </main>
</body>
</html>
