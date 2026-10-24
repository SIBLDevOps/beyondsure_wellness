{{-- Region chips. Needs $regions and $regionId. Only shown when there is more than one region. --}}
@if (($regions ?? collect())->count() > 1)
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="small fw-semibold text-secondary"><i class="bi bi-geo-alt me-1"></i>Region</span>
        <a href="{{ request()->url() }}" class="btn btn-sm rounded-pill {{ $regionId ? 'btn-outline-secondary' : 'btn-primary' }}">All</a>
        @foreach ($regions as $r)
            <a href="{{ request()->url() }}?region={{ $r->id }}" class="btn btn-sm rounded-pill {{ (int) $regionId === $r->id ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $r->name }}</a>
        @endforeach
    </div>
@endif
