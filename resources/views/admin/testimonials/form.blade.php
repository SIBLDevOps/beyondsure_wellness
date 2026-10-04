@extends('layouts.admin')

@section('title', $testimonial->exists ? 'Edit testimonial' : 'New testimonial')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.testimonials.index') }}" class="small text-secondary text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Back to Testimonials
        </a>
    </div>

    <div class="card border" style="max-width: 640px;">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold">
                <i class="bi bi-chat-quote me-2 text-success"></i>{{ $testimonial->exists ? 'Edit Testimonial: ' . $testimonial->name : 'Create New Testimonial' }}
            </h2>
            <div class="text-body-tertiary small">Configure reviewer details, quote, rating, and homepage visibility</div>
        </div>
        <form method="POST" action="{{ $testimonial->exists ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}">
            @csrf
            @if ($testimonial->exists) @method('PUT') @endif

            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Member / Reviewer Name <span class="text-danger">*</span></label>
                        <input name="name" value="{{ old('name', $testimonial->name) }}" required
                            class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Rajesh Sharma">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Role / Designation <span class="text-body-tertiary small fw-normal">(optional)</span></label>
                        <input name="role" value="{{ old('role', $testimonial->role) }}"
                            class="form-control @error('role') is-invalid @enderror" placeholder="e.g. Gold Member, Pune">
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Testimonial Quote <span class="text-danger">*</span></label>
                    <textarea name="message" rows="3" required
                        class="form-control @error('message') is-invalid @enderror" placeholder="Write the member's feedback...">{{ old('message', $testimonial->message) }}</textarea>
                    @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Star Rating (1–5) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-warning"><i class="bi bi-star-fill"></i></span>
                            <input name="rating" type="number" min="1" max="5" value="{{ old('rating', $testimonial->rating ?? 5) }}" required
                                class="form-control @error('rating') is-invalid @enderror">
                            @error('rating') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Sort Order <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary"><i class="bi bi-sort-numeric-down"></i></span>
                            <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $testimonial->sort_order ?? 0) }}" required
                                class="form-control @error('sort_order') is-invalid @enderror">
                            @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text small">Lower numbers appear first on the homepage.</div>
                    </div>
                </div>

                <div class="border rounded-3 p-3 bg-light-subtle">
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $testimonial->exists ? $testimonial->is_active : true))>
                        <label class="form-check-label fw-medium" for="is_active">Show on public homepage</label>
                        <div class="text-body-tertiary small">Uncheck to hide this testimonial without deleting it.</div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light-subtle border-top py-3 d-flex align-items-center justify-content-end gap-2">
                <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Save testimonial</button>
            </div>
        </form>
    </div>
@endsection