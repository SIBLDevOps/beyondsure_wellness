@extends('layouts.admin')

@section('title', 'Regions')

@section('content')
    @php $can = fn ($r) => auth()->user()->canAccessRoute($r); @endphp

    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <div style="max-width: 720px;">
            <h2 class="h5 fw-bold mb-1">Regions</h2>
            <p class="text-secondary mb-0">
                A region is a separate market. Each one has <strong>its own binary tree</strong> and <strong>its own plans</strong>.
                A member, their sponsor and everyone placed under them always stay in the same region, and a mobile number can belong to <strong>only one region</strong>.
            </p>
        </div>
        <a href="{{ route('admin.regions.create') }}" class="btn btn-success flex-shrink-0"><i class="bi bi-plus-lg me-1"></i>New region</a>
    </div>

    <div class="card border mb-4">
        <div class="card-body p-3">
            <div class="small fw-semibold text-secondary mb-2"><i class="bi bi-signpost-split text-primary me-1"></i>Setting up a region</div>
            <div class="row g-2">
                @foreach ([['1', 'Create the region', 'Name and code'], ['2', 'Design its plans', 'Plans, New plan, choose the region'], ['3', 'Create the root member', 'Only you can start a region'], ['4', 'Root signs in, then members invite', 'Everyone after joins through member links']] as [$n, $t, $d])
                    <div class="col-md-3"><div class="border rounded-3 px-3 py-2 h-100"><span class="badge bg-primary rounded-circle me-1">{{ $n }}</span><span class="fw-semibold small">{{ $t }}</span><div class="text-secondary" style="font-size:.72rem">{{ $d }}</div></div></div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4">
        @foreach ($regions as $region)
            <div class="col-lg-6">
                <div class="card border h-100 {{ $region->is_active ? '' : 'opacity-75' }}">
                    <div class="card-header bg-transparent border-bottom-0 pt-3 d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <h3 class="h6 fw-bold mb-0"><i class="bi bi-geo-alt-fill text-primary me-1"></i>{{ $region->name }}
                                <span class="badge bg-light text-secondary border font-monospace ms-1">{{ $region->code }}</span></h3>
                            @if ($region->description)<div class="text-secondary small mt-1">{{ $region->description }}</div>@endif
                        </div>
                        <span class="badge {{ $region->is_active ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary border' }}">{{ $region->is_active ? 'Open' : 'Closed' }}</span>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-2 mb-3 text-center">
                            <div class="col-3"><div class="border rounded-3 py-2"><div class="fs-5 fw-bold">{{ $region->members_count }}</div><div class="text-secondary" style="font-size:.7rem">Members</div></div></div>
                            <div class="col-3"><div class="border rounded-3 py-2"><div class="fs-5 fw-bold text-success">{{ $region->active_members_count }}</div><div class="text-secondary" style="font-size:.7rem">Active</div></div></div>
                            <div class="col-3"><div class="border rounded-3 py-2"><div class="fs-5 fw-bold">{{ $region->plans_count }}</div><div class="text-secondary" style="font-size:.7rem">Plans</div></div></div>
                            <div class="col-3"><div class="border rounded-3 py-2"><div class="fs-5 fw-bold">{{ $region->orders_count }}</div><div class="text-secondary" style="font-size:.7rem">Paid orders</div></div></div>
                        </div>

                        <div class="small mb-2">
                            <span class="text-secondary">Tree root:</span>
                            @if ($region->rootMember)
                                <span class="fw-semibold">{{ $region->rootMember->name }}</span>
                                <span class="font-monospace text-secondary">({{ $region->rootMember->member_code }})</span>
                                @if ($can('admin.members.network'))<a href="{{ route('admin.members.network', $region->rootMember) }}" class="ms-1 text-decoration-none">View tree &rarr;</a>@endif
                            @else
                                <span class="text-warning-emphasis">Not started yet. Create the root member below to open this region.</span>
                            @endif
                        </div>

                        @if ($region->rootMember)
                            {{-- The tree already has its root, so everyone else joins through a member's own invite link. --}}
                            <div class="rounded-3 border bg-body-tertiary px-3 py-2 small mb-3">
                                <i class="bi bi-check-circle-fill text-success me-1"></i>
                                <strong>Root created.</strong> New members now join through a member's own Left or Right invite link, so there is no region link to share.
                            </div>
                        @else
                            {{-- Only the admin starts a region: nobody can take the root seat by signing up. --}}
                            @if ($can('admin.regions.root'))
                                <form method="POST" action="{{ route('admin.regions.root', $region) }}" class="rounded-3 border p-3 mb-3 bg-body-tertiary">
                                    @csrf
                                    <div class="fw-semibold small mb-1"><i class="bi bi-person-plus me-1 text-primary"></i>Create the root member</div>
                                    <div class="text-secondary mb-2" style="font-size:.74rem;">
                                        The root is the top of this region's tree and earns on every match in it, so only you can start a region. They sign in with their mobile number, choose a plan and activate. Everyone else joins through member invite links.
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-12"><input name="name" class="form-control form-control-sm" placeholder="Full name" required maxlength="255" aria-label="Root member's full name"></div>
                                        <div class="col-6"><input name="phone" class="form-control form-control-sm" placeholder="10-digit mobile" required pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric" aria-label="Mobile number"></div>
                                        <div class="col-6"><input name="email" type="email" class="form-control form-control-sm" placeholder="Email (optional)" maxlength="255" aria-label="Email"></div>
                                    </div>
                                    @if ($errors->any() && old('name') !== null && (int) request()->route('region') === $region->id)
                                    @endif
                                    <button class="btn btn-sm btn-success mt-2"><i class="bi bi-check-lg me-1"></i>Create root and open the region</button>
                                </form>
                            @else
                                <div class="alert alert-warning py-2 small mb-3">Ask the Super Admin to create this region's root member.</div>
                            @endif
                        @endif

                        @if ($region->plans_count === 0)
                            <div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>This region has no plans yet, so its members can't buy anything.</div>
                        @endif

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.regions.edit', $region) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
                            @if ($can('admin.plans.index'))<a href="{{ route('admin.plans.index', ['region' => $region->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-layers me-1"></i>Plans</a>@endif
                            @if ($can('admin.members.index'))<a href="{{ route('admin.members.index', ['region' => $region->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-people me-1"></i>Members</a>@endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var el = document.getElementById(btn.dataset.copy);
                el.select();
                if (navigator.clipboard) { navigator.clipboard.writeText(el.value); }
                btn.textContent = 'Copied';
            });
        });
    </script>
@endsection
