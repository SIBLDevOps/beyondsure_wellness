@extends('layouts.admin')

@section('title', $subAdmin->exists ? 'Edit sub-admin' : 'New sub-admin')

@section('content')
    @php $granted = old('permissions', $subAdmin->permissions ?? []); @endphp

    @if (session('new_password'))
        @php $np = session('new_password'); @endphp
        <div class="alert alert-warning border d-flex gap-3 align-items-start" style="max-width: 860px;" role="alert">
            <i class="bi bi-key-fill fs-4 text-warning-emphasis"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">New password for {{ $np['name'] }}</div>
                <div class="small mb-2">Shown <strong>only this once</strong> — copy it now and share it with them yourself. After you leave this page it can't be shown again.</div>
                <div class="input-group input-group-sm" style="max-width: 420px;">
                    <input type="text" readonly class="form-control font-monospace fs-6" id="newPasswordValue" value="{{ $np['password'] }}" onclick="this.select()">
                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('newPasswordValue').value).then(() => { this.textContent = 'Copied'; }).catch(() => document.getElementById('newPasswordValue').select())">Copy</button>
                </div>
                <div class="small text-body-secondary mt-1">Login: {{ $np['email'] }}</div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $subAdmin->exists ? route('admin.team.update', $subAdmin) : route('admin.team.store') }}" style="max-width: 860px;">
        @csrf
        @if ($subAdmin->exists) @method('PUT') @endif

        <div class="card border mb-4">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-primary"></i>{{ $subAdmin->exists ? 'Edit ' . $subAdmin->name : 'New sub-admin' }}</h2>
                <a href="{{ route('admin.team.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" for="name">Name <span class="text-danger">*</span></label>
                        <input id="name" name="name" value="{{ old('name', $subAdmin->name) }}" required class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" for="email">Email (their login) <span class="text-danger">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email', $subAdmin->email) }}" required class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" for="password">Password @unless ($subAdmin->exists)<span class="text-danger">*</span>@endunless</label>
                        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(! $subAdmin->exists) class="form-control">
                        <div class="form-text">{{ $subAdmin->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters. Share it with them yourself.' }}</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch fs-5 mb-1">
                            <input type="checkbox" class="form-check-input" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $subAdmin->is_active))>
                            <label class="form-check-label fs-6 fw-medium" for="is_active">Account is active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $chosenRegions = old('region_ids', $subAdmin->region_ids ?? []);
            $regionMode = old('region_access', empty($subAdmin->region_ids) ? 'all' : 'selected');
        @endphp
        <div class="card border mb-4" id="regionAccessCard">
            <div class="card-header bg-light-subtle border-bottom py-3">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-geo-alt me-2 text-primary"></i>Which regions they can see</h2>
                <div class="text-body-tertiary small">Applies on top of the sections below. Members, orders, payments, plans and reports then show only these regions.</div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <div class="form-check"><input class="form-check-input" type="radio" name="region_access" id="ra_all" value="all" @checked($regionMode === 'all')><label class="form-check-label fw-medium" for="ra_all">All regions</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="region_access" id="ra_sel" value="selected" @checked($regionMode === 'selected')><label class="form-check-label fw-medium" for="ra_sel">Only the regions I tick</label></div>
                </div>
                <div class="d-flex flex-wrap gap-2" id="regionBoxes">
                    @foreach ($regions as $r)
                        <label class="border rounded-3 px-3 py-2 d-flex align-items-center gap-2" style="cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-0" name="region_ids[]" value="{{ $r->id }}" @checked(in_array($r->id, array_map('intval', (array) $chosenRegions), true))>
                            <span class="small fw-semibold">{{ $r->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('region_ids') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                <div class="form-text mt-2">A person limited to some regions can't approve cycles, release payout batches or create regions, because those affect every region.</div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var boxes = document.getElementById('regionBoxes');
                function sync() { var sel = document.getElementById('ra_sel').checked; boxes.style.opacity = sel ? 1 : .45; boxes.querySelectorAll('input').forEach(function (i) { i.disabled = !sel; }); }
                document.querySelectorAll('input[name=region_access]').forEach(function (r) { r.addEventListener('change', sync); });
                sync();
            });
        </script>

        <div class="card border mb-4">
            <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-key me-2 text-primary"></i>What they can see</h2>
                    <div class="text-body-tertiary small">The Dashboard is always available. Everything else is off until you tick it.</div>
                </div>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="none">None</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="safe">All except Finance &amp; editing</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="all">Everything</button>
                </div>
            </div>
            <div class="card-body">
                @foreach ($groups as $groupLabel => $items)
                    <div class="mb-4">
                        <div class="text-uppercase fw-semibold text-secondary mb-2" style="font-size: .7rem; letter-spacing: .08em;">{{ $groupLabel }}</div>
                        <div class="row g-2">
                            @foreach ($items as $key => [$label, $desc])
                                @php $sensitive = in_array($key, \App\Support\AdminPermissions::SENSITIVE, true); @endphp
                                <div class="col-md-6">
                                    <label class="d-flex gap-2 p-3 border rounded-3 h-100 {{ $sensitive ? 'border-warning-subtle bg-warning-subtle bg-opacity-25' : '' }}" style="cursor: pointer;" for="perm_{{ $key }}">
                                        <input type="checkbox" class="form-check-input mt-1 flex-shrink-0 perm-box" data-sensitive="{{ $sensitive ? 1 : 0 }}" id="perm_{{ $key }}" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $granted, true))>
                                        <span>
                                            <span class="fw-semibold small d-block">{{ $label }} @if ($sensitive)<i class="bi bi-eye-slash text-warning-emphasis" title="Sensitive"></i>@endif</span>
                                            <span class="text-body-tertiary" style="font-size: .78rem;">{{ $desc }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="small text-body-tertiary border-top pt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Sub-admins sign in at the normal admin login and see the same console, just without the sections you leave unticked.
                    They can never open this team page or create other admins.
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i>{{ $subAdmin->exists ? 'Save access' : 'Create sub-admin' }}</button>
        <a href="{{ route('admin.team.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>

    @if ($subAdmin->exists)
        <div class="card border mt-4" style="max-width: 860px;">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="fw-semibold"><i class="bi bi-key me-1 text-primary"></i>Forgot or need their password?</div>
                    <div class="text-body-tertiary small" style="max-width: 560px;">
                        Passwords are stored scrambled, so no one — not even you — can read an existing one.
                        Generate a new password instead; it's shown once so you can pass it on. Their old password stops working immediately.
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.team.password', $subAdmin) }}" onsubmit="return confirm('Generate a new password? Their current password will stop working.')">
                    @csrf @method('PUT')
                    <button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Generate new password</button>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const boxes = document.querySelectorAll('.perm-box');
            // Allowing edits implies allowing to view the catalog.
            const manage = document.getElementById('perm_catalog.manage');
            const view = document.getElementById('perm_catalog');
            if (manage && view) manage.addEventListener('change', () => { if (manage.checked) view.checked = true; });
            document.querySelectorAll('[data-preset]').forEach(btn => btn.addEventListener('click', () => {
                const preset = btn.dataset.preset;
                boxes.forEach(b => { b.checked = preset === 'all' || (preset === 'safe' && b.dataset.sensitive === '0'); });
            }));
        });
    </script>
@endsection
