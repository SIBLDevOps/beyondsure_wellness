@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
    @if ($setupNeeded ?? false)
        @include('partials.setup-needed', ['what' => 'The audit log'])
    @else
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
        <div style="max-width: 640px;">
            <h2 class="h5 fw-bold mb-1">Audit log</h2>
            <p class="text-secondary small mb-0">Every change staff made, and every sign-in, newest first. Only the names of the fields that were sent are kept, never what was typed. Lines can never be edited or deleted.</p>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Person, action, item or address" style="min-width: 240px;">
            <button class="btn btn-sm btn-primary">Search</button>
            @if ($q !== '')<a href="{{ route('admin.audit-log.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>@endif
        </form>
    </div>

    <div class="card border">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr><th class="ps-3">When</th><th>Who</th><th>Action</th><th>Item</th><th>Fields sent</th><th class="pe-3">From</th></tr>
                </thead>
                <tbody>
                    @forelse ($entries as $e)
                        <tr>
                            <td class="ps-3 small text-nowrap">{{ $e->created_at->format('d M Y, h:i A') }}</td>
                            <td class="small">{{ $e->user_name ?? '—' }}</td>
                            <td class="small font-monospace">{{ $e->action }}@if ($e->method)<span class="text-secondary"> · {{ $e->method }}</span>@endif</td>
                            <td class="small">{{ $e->subject ?? '—' }}</td>
                            <td class="small text-secondary" style="max-width: 260px;">{{ $e->details ?? '—' }}</td>
                            <td class="pe-3 small text-secondary">{{ $e->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-tertiary py-5">Nothing recorded{{ $q !== '' ? ' for that search' : ' yet' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())<div class="card-footer bg-white">{{ $entries->links('pagination::bootstrap-5') }}</div>@endif
    </div>
    @endif
@endsection
