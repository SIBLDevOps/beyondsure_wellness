@extends('layouts.admin')

@section('title', 'Change password')

@section('content')
    <div class="row g-4">
        <div class="col-lg-6 col-xl-5">
            <form method="POST" action="{{ route('admin.account.password.update') }}" class="card border">
                @csrf
                @method('PUT')
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4">
                    <h2 class="h5 fw-bold mb-1"><i class="bi bi-key me-2 text-primary"></i>Change your password</h2>
                    <div class="text-secondary small">Signed in as <strong>{{ auth()->user()->email }}</strong></div>
                </div>
                <div class="card-body px-4 pb-4 d-grid gap-3">
                    <div>
                        <label class="form-label small fw-semibold" for="current_password">Current password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                            class="form-control @error('current_password') is-invalid @enderror">
                        @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label small fw-semibold" for="password">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
                            class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">At least 8 characters, with a letter and a number.</div>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold" for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="form-control">
                    </div>
                    <div class="d-flex gap-2 pt-2 border-top">
                        <button class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i>Save new password</button>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-6 col-xl-5">
            <div class="card border"><div class="card-body small">
                <div class="fw-semibold mb-2"><i class="bi bi-info-circle text-primary me-1"></i>Good to know</div>
                <ul class="ps-3 mb-0 d-grid gap-1">
                    <li>Nobody, including the Super Admin, can read your password. It is stored scrambled.</li>
                    <li>Forgot it? The Super Admin can set you a new one from <em>Sub-admins</em>.</li>
                    <li>Members don't have passwords. They sign in with a one-time code sent to their mobile number.</li>
                </ul>
            </div></div>
        </div>
    </div>
@endsection
