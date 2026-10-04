@extends('layouts.admin')

@section('title', $product->exists ? 'Edit product' : 'New product')

@section('content')
    <div class="card border" style="max-width: 720px;">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0 fw-semibold">
                <i class="bi bi-box-seam me-2 text-primary"></i>
                {{ $product->exists ? 'Edit Product — ' . $product->name : 'Create New Product' }}
            </h2>
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Products
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
                @csrf
                @if ($product->exists) @method('PUT') @endif

                {{-- 1. Product Identity --}}
                <fieldset class="border rounded-3 p-3 mb-4">
                    <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                        1. Product Identity &amp; Category
                    </legend>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Category <span class="text-danger">*</span></label>
                            <select name="category_id" required class="form-select @error('category_id') is-invalid @enderror">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>
                                        {{ $category->name }} {{ $category->is_free ? '(Complimentary)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">SKU Code <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-upc-scan text-muted"></i></span>
                                <input name="sku" value="{{ old('sku', $product->sku) }}" placeholder="e.g. SYR-001" required
                                    class="form-control font-monospace @error('sku') is-invalid @enderror">
                                @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Product Name <span class="text-danger">*</span></label>
                            <input name="name" value="{{ old('name', $product->name) }}" placeholder="Enter product or benefit name" required
                                class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description</label>
                            <textarea name="description" rows="2" placeholder="Optional details shown on bundle views…"
                                class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- 2. Pricing & GST --}}
                <fieldset class="border rounded-3 p-3 mb-4">
                    <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                        2. Cost, Retail Pricing &amp; Product-Wise GST
                    </legend>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Cost Price (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">₹</span>
                                <input name="cost_price" id="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price ?? 0) }}" required
                                    class="form-control @error('cost_price') is-invalid @enderror">
                                @error('cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text" style="font-size: .72rem;">Base procurement/manufacturing cost. Set 0 for free complimentary benefits.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Sell / Retail Price (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">₹</span>
                                <input name="sell_price" id="sell_price" type="number" step="0.01" min="0" value="{{ old('sell_price', $product->sell_price ?? 0) }}" required
                                    class="form-control @error('sell_price') is-invalid @enderror">
                                @error('sell_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text" style="font-size: .72rem;">Taxable base value used for plan bundling &amp; MRP proportioning.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Product GST Rate (%)</label>
                            <div class="input-group">
                                <input name="gst_pct" id="gst_pct" type="number" step="0.01" min="0" max="100"
                                    value="{{ old('gst_pct', $product->gst_pct) }}"
                                    placeholder="Default ({{ number_format(\App\Support\Gst::pct(), 2) }}%)"
                                    class="form-control font-monospace @error('gst_pct') is-invalid @enderror">
                                <span class="input-group-text bg-light">%</span>
                                @error('gst_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text" style="font-size: .72rem;">Specific rate for this product. Blank = company default ({{ number_format(\App\Support\Gst::pct(), 2) }}%).</div>
                        </div>

                        {{-- Quick GST Slab Selector --}}
                        <div class="col-12 pt-1">
                            <label class="form-label text-secondary d-block mb-1" style="font-size: .75rem;"><i class="bi bi-tag me-1"></i>Quick Select Standard Indian GST Slab:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 slab-btn" data-slab="0">0% (Exempt)</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 slab-btn" data-slab="5">5% (AYUSH / Meds)</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 slab-btn" data-slab="12">12% (Wellness / Health)</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 slab-btn" data-slab="18">18% (Supplements / FMCG)</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 slab-btn" data-slab="28">28% (Luxury)</button>
                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 slab-btn" data-slab="">Use Company Default</button>
                            </div>
                        </div>

                        {{-- Live Tax & Margin Preview Box --}}
                        <div class="col-12 mt-2">
                            <div class="p-3 rounded-2 bg-light border border-light-subtle">
                                <div class="row g-2 align-items-center text-center text-md-start">
                                    <div class="col-6 col-md-3">
                                        <div class="text-secondary" style="font-size: .72rem;">Taxable Retail Base</div>
                                        <div class="fw-bold" id="preview-base">₹{{ number_format($product->sell_price ?? 0, 2) }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-secondary" style="font-size: .72rem;">Effective GST (<span id="preview-rate">{{ number_format($product->gstPct(), 1) }}%</span>)</div>
                                        <div class="fw-bold text-info-emphasis" id="preview-gst">₹{{ number_format(($product->sell_price ?? 0) * ($product->gstPct() / 100), 2) }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-secondary" style="font-size: .72rem;">Customer MRP (Incl. GST)</div>
                                        <div class="fw-bold text-success" id="preview-total">₹{{ number_format(($product->sell_price ?? 0) * (1 + $product->gstPct() / 100), 2) }}</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-secondary" style="font-size: .72rem;">Gross Margin</div>
                                        <div class="fw-bold text-primary" id="preview-margin">₹{{ number_format(max(0, ($product->sell_price ?? 0) - ($product->cost_price ?? 0)), 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- 3. Benefit Metadata --}}
                <fieldset class="border rounded-3 p-3 mb-4 bg-light bg-opacity-50">
                    <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                        3. Complimentary Benefit Metadata (Display Only)
                    </legend>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Benefit group</label>
                            <input name="benefit_group" value="{{ old('benefit_group', $product->benefit_group) }}" placeholder="e.g. Everyday Discounts"
                                class="form-control form-control-sm @error('benefit_group') is-invalid @enderror">
                            @error('benefit_group') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Usage limit</label>
                            <input name="usage_limit" type="number" min="1" value="{{ old('usage_limit', $product->usage_limit) }}" placeholder="e.g. 12"
                                class="form-control form-control-sm @error('usage_limit') is-invalid @enderror">
                            @error('usage_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Usage period</label>
                            <select name="usage_period" class="form-select form-select-sm @error('usage_period') is-invalid @enderror">
                                <option value="">— Unlimited —</option>
                                @foreach (['year', 'month', 'lifetime'] as $period)
                                    <option value="{{ $period }}" @selected(old('usage_period', $product->usage_period) === $period)>{{ ucfirst($period) }}</option>
                                @endforeach
                            </select>
                            @error('usage_period') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="p-3 border rounded-3 bg-light mb-4">
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $product->exists ? $product->is_active : true))>
                        <label class="form-check-label fw-medium small" for="is_active">Active &amp; Available for Plan Bundles</label>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i>Save Product
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const defaultGst = {{ \App\Support\Gst::pct() }};
                const costInput = document.getElementById('cost_price');
                const sellInput = document.getElementById('sell_price');
                const gstInput = document.getElementById('gst_pct');

                const previewBase = document.getElementById('preview-base');
                const previewRate = document.getElementById('preview-rate');
                const previewGst = document.getElementById('preview-gst');
                const previewTotal = document.getElementById('preview-total');
                const previewMargin = document.getElementById('preview-margin');

                function updatePreview() {
                    const sell = parseFloat(sellInput?.value) || 0;
                    const cost = parseFloat(costInput?.value) || 0;
                    let rate = parseFloat(gstInput?.value);
                    if (isNaN(rate) || gstInput?.value === '') {
                        rate = defaultGst;
                    }

                    const gstAmt = (sell * rate) / 100;
                    const total = sell + gstAmt;
                    const margin = Math.max(0, sell - cost);

                    if (previewBase) previewBase.textContent = '₹' + sell.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    if (previewRate) previewRate.textContent = rate.toFixed(1) + '%';
                    if (previewGst) previewGst.textContent = '₹' + gstAmt.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    if (previewTotal) previewTotal.textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    if (previewMargin) previewMargin.textContent = '₹' + margin.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                document.querySelectorAll('.slab-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const slab = this.dataset.slab;
                        if (gstInput) {
                            gstInput.value = slab;
                            updatePreview();
                        }
                    });
                });

                if (costInput) costInput.addEventListener('input', updatePreview);
                if (sellInput) sellInput.addEventListener('input', updatePreview);
                if (gstInput) gstInput.addEventListener('input', updatePreview);

                updatePreview();
            });
        </script>
    @endpush
@endsection