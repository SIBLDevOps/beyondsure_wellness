@extends('layouts.admin')

@section('title', 'Sub-admins')

@section('content')
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <p class="text-secondary mb-0" style="max-width: 700px;">
            Give a team member their own login to the same admin console, and choose exactly what they can open.
            Anything you don't tick stays hidden from them — including the <strong>profit reports</strong>, which show
            where the money goes and what the company keeps.
        </p>
        <a href="{{ route('admin.team.create') }}" class="btn btn-success flex-shrink-0"><i class="bi bi-person-plus me-1"></i>Add sub-admin</a>
    </div>

    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-primary"></i>Sub-admins</h2>
            <div class="text-body-tertiary small">{{ $subAdmins->count() }} {{ Str::plural('account', $subAdmins->count()) }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>Can access</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subAdmins as $sub)
                        @php $granted = $sub->permissions ?? []; @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $sub->name }}</div>
                                <div class="text-body-tertiary small">{{ $sub->email }}</div>
                            </td>
                            <td style="max-width: 420px;">
                                @if (empty($granted))
                                    <span class="text-body-tertiary small">Dashboard only</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($groups as $items)
                                            @foreach ($items as $key => [$label])
                                                @if (in_array($key, $granted, true))
                                                    <span class="badge {{ in_array($key, \App\Support\AdminPermissions::SENSITIVE, true) ? 'bg-warning-subtle text-warning-emphasis' : 'bg-primary-subtle text-primary' }} fw-normal">{{ $label }}</span>
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $sub->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $sub->is_active ? 'Active' : 'Switched off' }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.team.edit', $sub) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit access</a>
                                    <form method="POST" action="{{ route('admin.team.destroy', $sub) }}" onsubmit="return confirm('Remove this sub-admin? They will no longer be able to sign in.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-5">No sub-admins yet. Add one to share the work without sharing everything.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border">
        <div class="card-body">
            <h2 class="h6 fw-semibold mb-1"><i class="bi bi-shield-lock me-2 text-success"></i>Super Admin</h2>
            <p class="text-body-tertiary small mb-2">Full access to everything, including this page. Sub-admins can never create or promote anyone.</p>
            @foreach ($superAdmins as $sa)
                <span class="badge bg-light text-dark border fw-normal me-1">{{ $sa->name }} · {{ $sa->email }}</span>
            @endforeach
        </div>
    </div>
@endsection
