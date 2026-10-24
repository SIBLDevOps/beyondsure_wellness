@extends('layouts.admin')

@section('title', 'Plans')

@section('content')
    <p class="text-secondary mb-4" style="max-width: 640px;">
        The plan bundles members can buy and what's inside each one. This is a read-only view — pricing and
        changes are handled by the Super Admin.
    </p>

    @include('admin.partials.region-filter')

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-layers me-2 text-primary"></i>Plan bundles</h2>
            <span class="badge bg-primary-subtle text-primary">{{ $plans->count() }} Plans</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Plan</th>
                        <th>Included products</th>
                        <th class="text-end">Price (excl. GST)</th>
                        <th class="text-center pe-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $plan->name }} <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-normal"><i class="bi bi-geo-alt me-1"></i>{{ $plan->region?->name }}</span></div>
                                <div class="text-body-tertiary small">{{ $plan->description }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($plan->products as $product)
                                        <span class="badge bg-light text-secondary border fw-normal">{{ $product->pivot->qty }}× {{ $product->name }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end fw-bold">₹{{ number_format($plan->price, 0) }}</td>
                            <td class="text-center pe-3">
                                <span class="badge rounded-pill {{ $plan->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-tertiary py-5">No plans configured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
