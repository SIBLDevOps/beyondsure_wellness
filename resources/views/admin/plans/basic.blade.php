@extends('layouts.admin')

@section('title', 'Plans')

@section('content')
    @php $inr = fn ($n) => '₹' . number_format($n, 0); @endphp

    <div class="mb-3" style="max-width: 640px;">
        <h2 class="h5 fw-bold mb-1">Plans</h2>
        <p class="text-secondary small mb-0">The plans members can buy and what is inside each one. This is a view-only page; prices and changes are handled by the Super Admin.</p>
    </div>

    @include('admin.partials.region-filter')

    @if ($plans->isEmpty())
        <div class="card border text-center py-5"><div class="card-body text-body-tertiary"><i class="bi bi-layers fs-1"></i><div class="mt-2">No plans have been set up yet.</div></div></div>
    @else
        <div class="row g-4">
            @foreach ($plans as $plan)
                <div class="col-md-6 col-xl-4">
                    <div class="card border h-100" style="border-radius: 1rem; {{ $plan->is_active ? '' : 'opacity:.72;' }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="min-w-0">
                                    <div class="fw-bold fs-5 text-truncate">{{ $plan->name }}</div>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-normal"><i class="bi bi-geo-alt me-1"></i>{{ $plan->region?->name }}</span>
                                </div>
                                <span class="badge rounded-pill {{ $plan->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <div class="mt-3">
                                <div class="text-secondary small">Price (GST added on top)</div>
                                <div class="fw-bold" style="font-size: 1.9rem; line-height: 1;">{{ $inr($plan->price) }}</div>
                            </div>
                            @if ($plan->description)
                                <div class="text-secondary small mt-3 mb-0">{{ \App\Support\RichText::snippet($plan->description, 200) }}</div>
                            @endif
                            <div class="mt-3">
                                <div class="small fw-semibold mb-1">What's inside</div>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse ($plan->products as $product)
                                        <span class="badge bg-light text-secondary border fw-normal">{{ $product->pivot->qty }}× {{ $product->name }}</span>
                                    @empty
                                        <span class="text-body-tertiary small">No products added yet.</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
