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
                                    <div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }}">
                                        <label class="ws-label" for="f_{{ $key }}">{{ $label }}@if ($key === 'site_name') <span class="text-danger">*</span>@endif</label>
                                        @if ($type === 'textarea')
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
@endsection
