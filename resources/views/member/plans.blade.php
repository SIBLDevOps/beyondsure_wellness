@extends('layouts.member')

@section('title', 'Plans')

@section('content')
    <p class="text-secondary mb-3">Pick a wellness plan bundle — you'll confirm billing and complete payment on the next step.</p>

    {{-- Plans Comparison Table --}}
    <div class="card border mb-4">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-table me-2 text-success"></i>Plan Bundles Comparison</h2>
                <div class="text-body-tertiary small">Compare prices, Business Volume (BV), and included wellness products side by side</div>
            </div>
            <span class="badge bg-light text-secondary border">{{ $plans->count() }} plans available</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Plan</th>
                        <th>Included Products</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total Payable</th>
                        <th class="text-end">Business Volume (BV)</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plans as $i => $plan)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $plan->name }}</div>
                                <div class="text-body-tertiary small">{{ $plan->description }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($plan->products as $product)
                                        <span class="badge bg-light text-secondary border fw-normal">{{ $product->pivot->qty }}× {{ $product->name }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end fw-bold">
                                ₹{{ number_format($plan->price, 0) }}
                                <div class="text-body-tertiary fw-normal" style="font-size: .7rem;">excl. GST</div>
                            </td>
                            <td class="text-end fw-semibold">
                                ₹{{ number_format($plan->totalPayable(), 0) }}
                                <div class="text-body-tertiary fw-normal" style="font-size: .7rem;">+ ₹{{ number_format($plan->gstAmount(), 0) }} GST</div>
                            </td>
                            <td class="text-end text-success fw-medium">₹{{ number_format($plan->bv, 0) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('member.plans.buy', $plan) }}">
                                    @csrf
                                    <button class="btn btn-sm {{ $i === 2 ? 'btn-success' : 'btn-outline-success' }}">
                                        <i class="bi bi-cart-plus me-1"></i>Buy now
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        @foreach ($plans as $i => $plan)
            <div class="col-md-6">
                <div class="card border h-100 hover-lift {{ $i === 2 ? 'plan-card-popular' : '' }}">
                    @if ($i === 2)
                        <div class="text-center">
                            <span class="badge bg-success rounded-pill position-relative" style="top: -12px;">Most popular</span>
                        </div>
                    @endif
                    <div class="card-body d-flex flex-column {{ $i === 2 ? 'pt-0' : '' }}">
                        <div class="fw-semibold fs-5">{{ $plan->name }}</div>
                        <p class="text-secondary small mt-1">{{ $plan->description }}</p>

                        <ul class="list-unstyled small text-secondary mb-3">
                            @foreach ($plan->products as $product)
                                <li class="mb-1"><i class="bi bi-check-lg text-success me-1"></i>{{ $product->pivot->qty }}× {{ $product->name }}</li>
                            @endforeach
                        </ul>

                        <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                            <div>
                                <div class="fs-3 fw-bold">₹{{ number_format($plan->totalPayable(), 0) }}</div>
                                <div class="text-body-tertiary small">₹{{ number_format($plan->price, 0) }} + ₹{{ number_format($plan->gstAmount(), 0) }} GST · BV ₹{{ number_format($plan->bv, 0) }}</div>
                            </div>
                            <form method="POST" action="{{ route('member.plans.buy', $plan) }}">
                                @csrf
                                <button class="btn {{ $i === 2 ? 'btn-success' : 'btn-outline-success' }}">Buy now</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
