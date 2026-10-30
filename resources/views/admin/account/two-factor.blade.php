@extends('layouts.admin')

@section('title', 'Two-factor sign-in')

@section('content')
    @if ($setupNeeded ?? false)
        @include('partials.setup-needed', ['what' => 'Two-factor sign-in'])
    @else
    <div class="row g-4">
        <div class="col-lg-7 col-xl-6">
            <div class="card border">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Two-factor sign-in</h2>
                    <p class="text-secondary small">A stolen password is then not enough to get in: signing in also needs a 6-digit code from an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, Authy and others).</p>

                    @if ($recoveryCodes)
                        <div class="alert alert-warning" role="alert">
                            <strong>Save these recovery codes now.</strong> Each works once if you lose your phone. They are shown only this one time.
                            <div class="row g-1 mt-2 font-monospace">
                                @foreach ($recoveryCodes as $code)<div class="col-6">{{ $code }}</div>@endforeach
                            </div>
                        </div>
                    @endif

                    @if ($on)
                        <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Two-factor sign-in is on for your account. You have {{ $left }} unused recovery {{ \Illuminate\Support\Str::plural('code', $left) }}.</div>
                        <form method="POST" action="{{ route('admin.account.two-factor.disable') }}" class="border rounded-3 p-3">
                            @csrf @method('DELETE')
                            <div class="fw-semibold small mb-2">Switch it off</div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <input type="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" placeholder="Your password" autocomplete="current-password" required>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-6">
                                    <input name="code" class="form-control form-control-sm @error('code') is-invalid @enderror" placeholder="6-digit code" inputmode="numeric" autocomplete="one-time-code" required>
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger mt-2">Switch off</button>
                        </form>
                    @elseif ($secret)
                        <ol class="small ps-3">
                            <li class="mb-2">In your authenticator app choose <em>Add account</em>, then <em>Enter a setup key</em>.</li>
                            <li class="mb-2">Type this key (spaces do not matter):
                                <div class="font-monospace fs-5 fw-bold bg-light border rounded px-3 py-2 mt-1 d-inline-block">{{ trim(chunk_split($secret, 4, ' ')) }}</div>
                            </li>
                            <li>Type the 6-digit code the app now shows, to confirm it works.</li>
                        </ol>
                        <details class="small text-secondary mb-3"><summary>Setup link for apps that can open it</summary><code class="text-break">{{ $uri }}</code></details>
                        <form method="POST" action="{{ route('admin.account.two-factor.confirm') }}" class="d-flex gap-2 align-items-start">
                            @csrf
                            <div>
                                <input name="code" class="form-control @error('code') is-invalid @enderror" placeholder="6-digit code" inputmode="numeric" autocomplete="one-time-code" required style="max-width: 170px;">
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-success">Confirm and switch on</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.account.two-factor.enable') }}">
                            @csrf
                            <p class="small mb-3">It is <strong>off</strong> for your account.</p>
                            <button class="btn btn-primary">Switch on</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
