@extends('layouts.admin')

@section('title', 'Products')

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <p class="text-secondary mb-0" style="max-width: 620px;">
            Every item that can go into a plan bundle — physical products (cost &gt; 0) and free complimentary
            benefits (cost = 0). <strong>Cost price</strong> feeds the plan cost-cap check; benefit group and
            usage limit are display-only and never affect pricing or compensation.
        </p>
        <a href="{{ route('admin.products.create') }}" class="btn btn-success flex-shrink-0">
            <i class="bi bi-plus-lg me-1"></i>New product
        </a>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-box-seam me-2 text-primary"></i>Catalog Products &amp; Benefits</h2>
                <div class="text-body-tertiary small">{{ $products->count() }} items in catalog</div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 280px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control" placeholder="Filter products by name, SKU, category…" data-table-filter="#productsTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="productsTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Product Name</th>
                        <th>SKU</th>
                        <th data-sort-key="text">Category</th>
                        <th class="text-end" data-sort-key="number">Cost Price</th>
                        <th class="text-end" data-sort-key="number">Sell Price</th>
                        <th class="text-end" data-sort-key="number">GST</th>
                        <th>Benefit Group &amp; Limit</th>
                        <th class="text-center" data-sort-key="text">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $product->name }}</div>
                                @if ($product->description)
                                    <div class="text-body-tertiary text-truncate" style="font-size: .72rem; max-width: 220px;">{{ $product->description }}</div>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-secondary border font-monospace">{{ $product->sku }}</span></td>
                            <td>
                                <span class="badge {{ $product->category->is_free ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }}">
                                    {{ $product->category->name }}
                                </span>
                            </td>
                            <td class="text-end" data-sort-value="{{ $product->cost_price }}">₹{{ number_format($product->cost_price, 2) }}</td>
                            <td class="text-end fw-medium" data-sort-value="{{ $product->sell_price }}">₹{{ number_format($product->sell_price, 2) }}</td>
                            <td class="text-end" data-sort-value="{{ $product->gstPct() }}">
                                @if ($product->gst_pct !== null)
                                    @if ((float)$product->gst_pct === 0.0)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">0% Exempt</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fw-semibold">{{ number_format($product->gst_pct, 1) }}% GST</span>
                                    @endif
                                @else
                                    <span class="badge bg-light text-secondary border font-monospace">{{ number_format($product->gstPct(), 1) }}%</span>
                                    <span class="text-body-tertiary d-block" style="font-size: .68rem;">(company default)</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($product->benefit_group || $product->usageLimitLabel())
                                    <div class="text-dark">{{ $product->benefit_group ?? 'General Benefit' }}</div>
                                    <div class="text-body-tertiary" style="font-size: .72rem;">{{ $product->usageLimitLabel() ?? 'Unlimited' }}</div>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-body-tertiary py-5">No products yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection