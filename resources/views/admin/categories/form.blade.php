@extends('layouts.admin')

@section('title', $category->exists ? 'Edit category' : 'New category')

@section('content')
    <div class="card border" style="max-width: 560px;">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0 fw-semibold">
                <i class="bi bi-tag me-2 text-primary"></i>
                {{ $category->exists ? 'Edit Category — ' . $category->name : 'Create New Category' }}
            </h2>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
                @csrf
                @if ($category->exists) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label fw-medium small">Category Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-tag text-muted"></i></span>
                        <input name="name" value="{{ old('name', $category->name) }}" placeholder="e.g. Wellness Supplements or Telehealth Benefits" required
                            class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="p-3 border rounded-3 bg-light mb-4">
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" name="is_free" value="1" class="form-check-input" id="is_free" @checked(old('is_free', $category->is_free))>
                        <label class="form-check-label fw-medium small" for="is_free">
                            Free / Complimentary Benefit Category
                        </label>
                        <div class="text-body-tertiary mt-1" style="font-size: .78rem;">
                            Enable this when the category holds complimentary services (teleconsultation, discount vouchers, etc.) that have zero cost/compensation impact.
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i>Save Category
                    </button>
                    <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection