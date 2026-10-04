@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <p class="text-secondary mb-0" style="max-width: 620px;">
            Groups products for organizing plan bundles. Mark a category <strong>Free</strong> when it holds
            complimentary benefits (teleconsultation, discounts, etc.) rather than paid physical goods — this
            is for organization only and doesn't affect cost or compensation calculations.
        </p>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-success flex-shrink-0">
            <i class="bi bi-plus-lg me-1"></i>New category
        </a>
    </div>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Product Categories</h2>
                <div class="text-body-tertiary small">{{ $categories->count() }} categories configured</div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 260px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control" placeholder="Filter categories…" data-table-filter="#categoriesTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="categoriesTable" data-sortable>
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3" data-sort-key="text">Category Name</th>
                        <th data-sort-key="text">Slug</th>
                        <th data-sort-key="text">Category Type</th>
                        <th class="text-center" data-sort-key="number">Products</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $category->name }}</td>
                            <td class="font-monospace small text-secondary">{{ $category->slug }}</td>
                            <td data-sort-value="{{ $category->is_free ? 1 : 0 }}">
                                <span class="badge rounded-pill {{ $category->is_free ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }}">
                                    <i class="bi {{ $category->is_free ? 'bi-gift' : 'bi-box-seam' }} me-1"></i>
                                    {{ $category->is_free ? 'Free / complimentary' : 'Paid product' }}
                                </span>
                            </td>
                            <td class="text-center" data-sort-value="{{ $category->products_count }}">
                                <span class="badge bg-light text-dark border px-2 py-1">{{ $category->products_count }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    @if ($category->products_count == 0)
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete empty category">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-tertiary py-5">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection