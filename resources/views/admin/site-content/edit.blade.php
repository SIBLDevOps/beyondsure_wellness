@extends('layouts.admin')

@section('title', 'Homepage content')

@section('content')
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <p class="text-secondary mb-0">
            Everything here is shown live on the public homepage — no deploy needed.
        </p>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.testimonials.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chat-quote me-1"></i>Manage testimonials
            </a>
            <a href="{{ route('home') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-box-arrow-up-right me-1"></i>Preview homepage
            </a>
        </div>
    </div>

    <div class="card border mb-3" style="max-width: 860px;">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-window-desktop me-2 text-success"></i>Public Website Copy &amp; Contact Info</h2>
            <div class="text-body-tertiary small">Update hero headlines, about text, and public contact details</div>
        </div>
        <form method="POST" action="{{ route('admin.site-content.update') }}">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($fields as $key => [$label, $default])
                        @php $isLong = str_contains($key, 'text') || str_contains($key, 'subtitle') || str_contains($key, 'address'); @endphp
                        <div class="{{ $isLong ? 'col-12' : 'col-md-6' }}">
                            <label class="form-label fw-medium small">{{ $label }} <span class="font-monospace text-body-tertiary fw-normal">({{ $key }})</span></label>
                            @if ($isLong)
                                <textarea name="{{ $key }}" rows="3" class="form-control">{{ old($key, $values[$key]) }}</textarea>
                            @else
                                <input name="{{ $key }}" value="{{ old($key, $values[$key]) }}" class="form-control">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer bg-light-subtle border-top py-3 d-flex align-items-center justify-content-between">
                <span class="text-body-tertiary small"><i class="bi bi-lightning-charge text-warning me-1"></i>Changes take effect immediately on the public homepage.</span>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save homepage content</button>
            </div>
        </form>
    </div>
@endsection
