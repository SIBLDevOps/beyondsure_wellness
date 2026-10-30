<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Confirm it is you — {{ Site::name() }}</title>
    @include('partials.favicon')
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f172a; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #1f2937; padding: 16px; }
        .card { background: #fff; width: 100%; max-width: 400px; border-radius: 14px; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,.35); }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p { color: #6b7280; margin: 0 0 16px; font-size: 14px; line-height: 1.5; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        input { width: 100%; box-sizing: border-box; font: inherit; font-size: 20px; letter-spacing: 4px; text-align: center; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; }
        input.small { font-size: 15px; letter-spacing: 1px; }
        button { width: 100%; margin-top: 14px; padding: 11px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .err { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; font-size: 14px; }
        details { margin-top: 18px; font-size: 14px; }
        summary { cursor: pointer; color: #4f46e5; }
        a { color: #6b7280; font-size: 13px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Confirm it is you</h1>
        <p>Open your authenticator app and type the 6-digit code it shows for {{ Site::name() }}.</p>

        @if ($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('admin.two-factor.verify') }}" autocomplete="off">
            @csrf
            <label for="code">6-digit code</label>
            <input id="code" name="code" inputmode="numeric" maxlength="7" autofocus autocomplete="one-time-code" placeholder="000000">
            <button type="submit">Confirm and sign in</button>
        </form>

        <details {{ $errors->has('recovery_code') ? 'open' : '' }}>
            <summary>Lost your phone? Use a recovery code</summary>
            <form method="POST" action="{{ route('admin.two-factor.verify') }}" autocomplete="off" style="margin-top:10px">
                @csrf
                <label for="recovery_code">Recovery code</label>
                <input id="recovery_code" name="recovery_code" class="small" maxlength="30" placeholder="xxxxx-xxxxx">
                <button type="submit">Use recovery code</button>
            </form>
        </details>

        <p style="margin:18px 0 0"><a href="{{ route('admin.login') }}">&larr; Back to sign in</a></p>
    </main>
</body>
</html>
