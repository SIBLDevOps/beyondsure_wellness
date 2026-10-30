@extends('layouts.admin')

@section('title', 'Website settings')

@section('content')
    <style>
        .ws-nav .list-group-item { border: 0; border-radius: .6rem; padding: .5rem .8rem; font-size: .85rem; color: var(--bs-secondary-color); display: flex; gap: .6rem; align-items: center; }
        .ws-nav .list-group-item:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
        .ws-card { scroll-margin-top: 80px; border-radius: 1rem; }
        .ws-card .card-header { background: transparent; border-bottom: 0; padding: 1.1rem 1.4rem .2rem; display: flex; gap: .8rem; align-items: center; }
        .ws-chip { width: 2.3rem; height: 2.3rem; border-radius: .65rem; display: grid; place-items: center; flex: none; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
        .ws-card h2 { font-size: 1rem; font-weight: 700; margin: 0; }
        .ws-card .sub { font-size: .8rem; color: var(--bs-secondary-color); margin: 0; }
        .ws-card .card-body { padding: .9rem 1.4rem 1.3rem; }
        .ws-label { font-size: .8rem; font-weight: 600; margin-bottom: .3rem; }
        .ws-preview { border: 1px dashed var(--bs-border-color); border-radius: .75rem; min-height: 84px; display: grid; place-items: center; padding: .75rem; background: repeating-conic-gradient(#f1f3f5 0% 25%, #fff 0% 50%) 50% / 16px 16px; }
        .ws-preview.dark { background: #1e1b4b; }
        .ws-preview img { max-height: 64px; max-width: 100%; object-fit: contain; }
        .ws-savebar { position: sticky; bottom: 0; z-index: 5; background: var(--bs-body-bg); border-top: 1px solid var(--bs-border-color); margin: 0 -1.5rem -1.5rem; padding: .8rem 1.5rem; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
        @media (max-width: 991.98px) { .ws-nav-wrap { display: none; } }
    </style>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-0">Website settings</h2>
            <div class="small text-secondary">Change the brand, logo and every public page without a deploy. Saved changes are live straight away.</div>
        </div>
        <div class="d-flex gap-2">
            @if (auth()->user()->canAccessRoute('admin.testimonials.index'))
                <a href="{{ route('admin.testimonials.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chat-quote me-1"></i>Testimonials</a>
            @endif
            <a href="{{ route('home') }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open website</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.site-content.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-3 ws-nav-wrap">
                <div class="list-group ws-nav sticky-top" style="top: 80px;">
                    <a href="#ws-logo" class="list-group-item list-group-item-action"><i class="bi bi-image"></i>Logo &amp; icon</a>
                    @foreach ($groups as $id => [$title, $icon])
                        <a href="#ws-{{ $id }}" class="list-group-item list-group-item-action"><i class="bi {{ $icon }}"></i>{{ $title }}</a>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-9 d-grid gap-4">

                {{-- Logo, dark logo, favicon --}}
                <section class="card border ws-card" id="ws-logo">
                    <div class="card-header">
                        <div class="ws-chip"><i class="bi bi-image"></i></div>
                        <div><h2>Logo &amp; icon</h2><p class="sub">PNG, JPG, WEBP or GIF. Until you upload a logo, the brand name is shown as text.</p></div>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            @foreach ($images as $key => [$label, $hint])
                                <div class="col-md-4">
                                    <div class="ws-label">{{ $label }}</div>
                                    <div class="ws-preview {{ $key === 'site_logo_dark' ? 'dark' : '' }} mb-2">
                                        @if ($imageUrls[$key])
                                            <img src="{{ $imageUrls[$key] }}" alt="{{ $label }}">
                                        @else
                                            <span class="small {{ $key === 'site_logo_dark' ? 'text-white-50' : 'text-body-tertiary' }}">Not set</span>
                                        @endif
                                    </div>
                                    <input type="file" name="{{ $key }}" accept="image/*" class="form-control form-control-sm @error($key) is-invalid @enderror">
                                    @error($key) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text" style="font-size:.72rem;">{{ $hint }}</div>
                                    @if ($imageUrls[$key])
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="remove_{{ $key }}" value="1" id="rm_{{ $key }}">
                                            <label class="form-check-label small text-danger" for="rm_{{ $key }}">Remove this image</label>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                @foreach ($groups as $id => [$title, $icon, $hint, $fields])
                    <section class="card border ws-card" id="ws-{{ $id }}">
                        <div class="card-header">
                            <div class="ws-chip"><i class="bi {{ $icon }}"></i></div>
                            <div><h2>{{ $title }}</h2><p class="sub">{{ $hint }}</p></div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach ($fields as $key => [$label, $default, $type])
                                    <div class="{{ in_array($type, ['textarea', 'color', 'surface'], true) ? 'col-12' : 'col-md-6' }}">
                                        <label class="ws-label" for="f_{{ $key }}">{{ $label }}@if ($key === 'site_name') <span class="text-danger">*</span>@endif</label>
                                        @if ($type === 'color')
                                            @php
                                                $isAdminColour = $key === 'site_admin_color';
                                                $defaultHex = $isAdminColour ? \App\Support\Theme::ADMIN_DEFAULT : \App\Support\Theme::DEFAULT;
                                                $chosen = \App\Support\Theme::normalize((string) old($key, $values[$key])) ?? $defaultHex;
                                            @endphp
                                            <div class="colour-field" data-default="{{ $defaultHex }}" data-admin="{{ $isAdminColour ? 1 : 0 }}">
                                                <div class="d-flex flex-wrap align-items-center gap-3">
                                                    <input type="color" value="{{ $chosen }}" class="cf-picker form-control form-control-color p-1" style="width: 3.2rem; height: 2.6rem;" title="Pick a colour" aria-label="Pick the colour">
                                                    <input id="f_{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" placeholder="{{ $defaultHex }}" maxlength="7" autocomplete="off"
                                                           class="cf-text form-control font-monospace @error($key) is-invalid @enderror" style="max-width: 9rem;">
                                                    <button type="button" class="cf-reset btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset to default</button>
                                                </div>
                                                <div class="d-flex flex-wrap align-items-center gap-2 mt-3" aria-label="Live preview">
                                                    <span class="small text-secondary me-1">Preview:</span>
                                                    <button type="button" class="cf-btn btn btn-sm" tabindex="-1">Save changes</button>
                                                    <button type="button" class="cf-outline btn btn-sm btn-outline" tabindex="-1">Outline</button>
                                                    <span class="cf-badge badge rounded-pill">Active</span>
                                                    <a href="#" onclick="return false" class="cf-link small">A link</a>
                                                    <span class="cf-menu d-inline-block rounded" style="width: 5.5rem; height: 1.7rem;" title="Side menu"></span>
                                                </div>
                                                <div class="cf-note small mt-2" aria-live="polite"></div>
                                            </div>
                                            <div class="form-text" style="font-size:.72rem;">
                                                @if ($isAdminColour)
                                                    The colour of this admin panel: buttons, links and the dark side menu. Leave empty for the default blue.
                                                @else
                                                    The colour of the website, the sign-in pages and the member area: buttons, links and the dark side menus. Leave empty for the default green.
                                                @endif
                                            </div>
                                        @elseif ($type === 'surface')
                                            @php $softOn = (string) old($key, $values[$key]) === '1'; $baseHex = \App\Support\Theme::primary(); @endphp
                                            <div class="row g-3">
                                                @foreach ([['1', 'Soft (recommended)', 'A gentle, slightly tinted off-white for pages, cards and tables. Easy on the eyes for long reading.', \App\Support\Theme::surface($baseHex), \App\Support\Theme::surfaceRaised($baseHex)], ['0', 'Bright white', 'Pure white backgrounds. Crisp, but it can feel harsh on a bright screen.', '#ffffff', '#ffffff']] as [$val, $title, $desc, $pageCol, $cardCol])
                                                    <div class="col-md-6">
                                                        <label class="d-block border rounded-3 p-3 h-100" style="cursor:pointer;" for="surface_{{ $val }}">
                                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                                <input class="form-check-input mt-0" type="radio" name="{{ $key }}" id="surface_{{ $val }}" value="{{ $val }}" @checked($softOn === ($val === '1'))>
                                                                <span class="fw-semibold">{{ $title }}</span>
                                                            </div>
                                                            <div class="rounded-2 p-2 mb-2" style="background: {{ $pageCol }}; border: 1px solid rgba(0,0,0,.08);">
                                                                <div class="rounded-2 p-2" style="background: {{ $cardCol }}; border: 1px solid rgba(0,0,0,.08);">
                                                                    <div style="height:.5rem; width:60%; background:#1e293b; border-radius:99px; opacity:.85;"></div>
                                                                    <div style="height:.4rem; width:85%; background:#64748b; border-radius:99px; opacity:.5; margin-top:.4rem;"></div>
                                                                </div>
                                                            </div>
                                                            <div class="small text-secondary">{{ $desc }}</div>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @error($key) <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        @elseif ($type === 'taxtype')
                                            @php $taxType = old($key, $values[$key]) === 'igst' ? 'igst' : 'cgst_sgst'; @endphp
                                            <select id="f_{{ $key }}" name="{{ $key }}" class="form-select @error($key) is-invalid @enderror">
                                                <option value="cgst_sgst" @selected($taxType === 'cgst_sgst')>CGST + SGST (sale within your own state)</option>
                                                <option value="igst" @selected($taxType === 'igst')>IGST (sale to another state)</option>
                                            </select>
                                        @elseif ($type === 'textarea')
                                            <textarea id="f_{{ $key }}" name="{{ $key }}" rows="3" class="form-control @error($key) is-invalid @enderror">{{ old($key, $values[$key]) }}</textarea>
                                        @else
                                            <input id="f_{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" @if ($key === 'site_name') required @endif class="form-control @error($key) is-invalid @enderror">
                                        @endif
                                        @error($key) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @if ($key === 'site_footer_text')<div class="form-text" style="font-size:.72rem;">Write <code>{year}</code> to insert the current year automatically.</div>@endif
                                        @if ($key === 'site_name')<div class="form-text" style="font-size:.72rem;">A trailing "+" is drawn in the accent colour.</div>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endforeach

                <div class="ws-savebar">
                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i>Save website settings</button>
                    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline-secondary">Preview</a>
                    <span class="small text-body-tertiary ms-auto"><i class="bi bi-lightning-charge text-warning me-1"></i>Live as soon as you save.</span>
                </div>
            </div>
        </div>
    </form>

    <script>
        // Colour pickers (brand colour and admin colour): the picker and the text box stay in step, a live preview shows
        // buttons, a link and the side menu, and a readability note warns (the server also refuses a colour that is
        // too light for white text).
        document.addEventListener('DOMContentLoaded', function () {
            const norm = v => { v = (v || '').trim(); const m3 = v.match(/^#([0-9a-f]{3})$/i); if (m3) v = '#' + [...m3[1]].map(c => c + c).join(''); return /^#[0-9a-f]{6}$/i.test(v) ? v.toLowerCase() : null; };
            const lum = h => { const c = [1, 3, 5].map(i => parseInt(h.substr(i, 2), 16) / 255).map(v => v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4)); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; };
            const contrastWhite = h => 1.05 / (lum(h) + .05);
            const shade = (h, by) => { const n = [1, 3, 5].map(i => Math.max(0, Math.min(255, Math.round(parseInt(h.substr(i, 2), 16) * by)))); return '#' + n.map(x => x.toString(16).padStart(2, '0')).join(''); };

            document.querySelectorAll('.colour-field').forEach(function (box) {
                const q = s => box.querySelector(s);
                const text = q('.cf-text'), picker = q('.cf-picker'), note = q('.cf-note');
                const btn = q('.cf-btn'), outline = q('.cf-outline'), badge = q('.cf-badge'), link = q('.cf-link'), menu = q('.cf-menu');
                const DEFAULT = box.dataset.default;

                function paint() {
                    const empty = text.value.trim() === '';
                    const hex = empty ? DEFAULT : norm(text.value);
                    if (!hex) { note.className = 'cf-note small mt-2 text-danger'; note.textContent = 'Not a valid colour yet. Use a # followed by 3 or 6 letters or digits, like ' + DEFAULT + '.'; return; }
                    const ok = contrastWhite(hex) >= 3;
                    const dark = shade(hex, .7), deep = shade(hex, .28);
                    btn.style.cssText = 'background:' + hex + ';border-color:' + hex + ';color:#fff';
                    outline.style.cssText = 'background:transparent;border:1px solid ' + hex + ';color:' + dark;
                    badge.style.cssText = 'background:' + hex + ';color:#fff';
                    link.style.color = dark;
                    menu.style.background = 'linear-gradient(180deg,' + shade(hex, .45) + ',' + deep + ')';
                    if (!empty) picker.value = hex;
                    note.className = 'cf-note small mt-2 ' + (ok ? 'text-success' : 'text-danger');
                    note.innerHTML = ok ? '<i class="bi bi-check-circle me-1"></i>Easy to read with white text.' : '<i class="bi bi-exclamation-triangle me-1"></i>Too light: white text on this colour would be hard to read. Pick a darker one.';
                }

                picker.addEventListener('input', () => { text.value = picker.value; paint(); });
                text.addEventListener('input', paint);
                q('.cf-reset').addEventListener('click', () => { text.value = ''; picker.value = DEFAULT; paint(); });
                paint();
            });
        });
    </script>
@endsection
