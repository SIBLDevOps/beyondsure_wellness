@extends('layouts.admin')

@section('title', 'Testimonials')

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <p class="text-secondary mb-0" style="max-width: 560px;">
            Shown on the public homepage's "What members say" section, ordered by Sort order (lowest first).
            Only <strong>active</strong> testimonials appear live —
            <a href="{{ route('home') }}" target="_blank" class="link-primary fw-medium text-decoration-none">preview the homepage</a>.
        </p>
        <a href="{{ route('admin.testimonials.create') }}" class="btn btn-success flex-shrink-0"><i class="bi bi-plus-lg me-1"></i>New testimonial</a>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-chat-quote me-2 text-success"></i>Member Testimonials</h2>
                <div class="text-body-tertiary small">Customer reviews displayed on the public homepage</div>
            </div>
            <span class="badge bg-light text-secondary border">{{ $testimonials->count() }} total</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Sort</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Message</th>
                        <th>Rating</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($testimonials as $t)
                        <tr>
                            <td><span class="badge bg-light text-secondary border font-monospace">#{{ $t->sort_order }}</span></td>
                            <td class="fw-semibold">{{ $t->name }}</td>
                            <td class="text-secondary small">{{ $t->role ?: '—' }}</td>
                            <td class="text-secondary small" style="max-width: 320px;">{{ $t->message }}</td>
                            <td class="text-nowrap">
                                <span class="text-warning">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi {{ $i <= $t->rating ? 'bi-star-fill' : 'bi-star text-body-tertiary' }}"></i>
                                    @endfor
                                </span>
                                <span class="small text-secondary ms-1">({{ $t->rating }}/5)</span>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $t->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $t->is_active ? 'Live' : 'Hidden' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('admin.testimonials.edit', $t) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
                                    <form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" onsubmit="return confirm('Delete this testimonial?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-tertiary py-4">No testimonials yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
