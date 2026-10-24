@extends('layouts.admin')

@section('title', $region->exists ? 'Edit region' : 'New region')

@section('content')
    @php $locked = $region->exists && ($region->members_count ?? 0) > 0; @endphp
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h5 fw-bold mb-0">{{ $region->exists ? 'Edit region · '.$region->name : 'New region' }}</h2>
        <a href="{{ route('admin.regions.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to regions</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ $region->exists ? route('admin.regions.update', $region) : route('admin.regions.store') }}" class="card border">
                @csrf
                @if ($region->exists) @method('PUT') @endif
                <div class="card-body p-4 d-grid gap-3">
                    <div>
                        <label class="form-label small fw-semibold" for="name">Region name <span class="text-danger">*</span></label>
                        <input id="name" name="name" value="{{ old('name', $region->name) }}" required placeholder="e.g. Gujarat, Maharashtra, UAE" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label small fw-semibold" for="code">Code <span class="text-danger">*</span></label>
                        <input id="code" name="code" value="{{ old('code', $region->code) }}" required placeholder="e.g. GJ" @readonly($locked) class="form-control font-monospace text-uppercase @error('code') is-invalid @enderror">
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Used in the region invite link. @if ($locked) It can't change now because members have already joined. @endif</div>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold" for="description">Description <span class="text-secondary fw-normal">(optional)</span></label>
                        <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $region->description) }}</textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $region->is_active))>
                        <label class="form-check-label fw-semibold" for="is_active">Open for new members</label>
                        <div class="form-text mt-0">Closing a region stops new sign-ups and new orders there. Existing members and their network are not affected.</div>
                    </div>
                    <div class="d-flex gap-2 pt-2 border-top">
                        <button class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i>{{ $region->exists ? 'Save changes' : 'Create region' }}</button>
                        <a href="{{ route('admin.regions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="card border"><div class="card-body small">
                <div class="fw-semibold mb-2"><i class="bi bi-info-circle text-primary me-1"></i>What a region controls</div>
                <ul class="ps-3 mb-0 d-grid gap-1">
                    <li><strong>One tree.</strong> Sponsors, left/right placement and matching never cross into another region.</li>
                    <li><strong>Its own plans.</strong> Members only see and can only buy their region's plans.</li>
                    <li><strong>One region per mobile number.</strong> A number already registered elsewhere is refused with a clear message.</li>
                    <li><strong>Shared everything else.</strong> Products, GST, income rates, cycles and withdrawals work the same in every region.</li>
                </ul>
            </div></div>
        </div>
    </div>
@endsection
