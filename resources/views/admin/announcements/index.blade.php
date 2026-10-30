@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')
    @if (session('error'))
        <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>
    @endif

    @if ($setupNeeded ?? false)
        <div class="card border text-center py-5"><div class="card-body">
            <i class="bi bi-database-gear fs-1 text-warning"></i>
            <h2 class="h5 fw-bold mt-2">One database update is needed first</h2>
            <p class="text-secondary mb-3" style="max-width: 520px; margin: 0 auto;">
                Announcements need a new table that has not been created yet. Run this once in the project folder, then reload this page.
            </p>
            <code class="d-inline-block bg-light border rounded px-3 py-2">php artisan migrate --force</code>
        </div></div>
    @else

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border">
                <div class="card-header bg-light-subtle border-bottom py-3">
                    <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>New announcement</h2>
                    <div class="text-body-tertiary small">Members see it on their dashboard. Tick the box to email it as well.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.announcements.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-medium" for="title">Title <span class="text-danger">*</span></label>
                            <input id="title" name="title" value="{{ old('title') }}" maxlength="150" required class="form-control @error('title') is-invalid @enderror" placeholder="Festive offer on the Essential plan">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium" for="body">Message <span class="text-danger">*</span></label>
                            @include('admin.partials.rich-editor', ['name' => 'body', 'value' => old('body'), 'placeholder' => 'Write the announcement. Use the toolbar for bold, lists and links.', 'height' => 200])
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium" for="audience">Who</label>
                                <select id="audience" name="audience" class="form-select @error('audience') is-invalid @enderror">
                                    <option value="all" @selected(old('audience', 'all') === 'all')>Everyone</option>
                                    <option value="active" @selected(old('audience') === 'active')>Active members only</option>
                                </select>
                                @error('audience') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-medium" for="region_id">Region</label>
                                <select id="region_id" name="region_id" class="form-select @error('region_id') is-invalid @enderror">
                                    @unless (auth()->user()->isRegionRestricted() && $regions->count() === 1)
                                        <option value="">{{ auth()->user()->isRegionRestricted() ? 'Choose a region' : 'All regions' }}</option>
                                    @endunless
                                    @foreach ($regions as $r)
                                        <option value="{{ $r->id }}" @selected((string) old('region_id') === (string) $r->id)>{{ $r->name }}</option>
                                    @endforeach
                                </select>
                                @error('region_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" class="form-check-input" id="send_email" name="send_email" value="1" @checked(old('send_email'))>
                            <label class="form-check-label small" for="send_email">Also email it to members (those who have not unsubscribed)</label>
                        </div>
                        <button class="btn btn-success"><i class="bi bi-send me-1"></i>Post announcement</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border">
                <div class="card-header bg-light-subtle border-bottom py-3"><h2 class="h6 mb-0 fw-semibold">Posted announcements</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary"><tr><th class="ps-3">Announcement</th><th>For</th><th class="text-end">Emailed</th><th class="pe-3">Posted</th></tr></thead>
                        <tbody>
                            @forelse ($announcements as $a)
                                <tr>
                                    <td class="ps-3"><div class="fw-semibold">{{ $a->title }}</div><div class="small text-secondary">{{ \Illuminate\Support\Str::limit($a->body, 90) }}</div></td>
                                    <td class="small">{{ $a->region?->name ?? 'All regions' }}<div class="text-secondary">{{ $a->audience === 'active' ? 'Active members' : 'Everyone' }}</div></td>
                                    <td class="text-end">{{ $a->send_email ? number_format($a->emailed_count) : '—' }}</td>
                                    <td class="pe-3 small text-secondary">{{ $a->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-tertiary py-5">No announcements yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($announcements->hasPages())<div class="card-footer bg-white">{{ $announcements->links('pagination::bootstrap-5') }}</div>@endif
            </div>
        </div>
    </div>
    @endif
@endsection
