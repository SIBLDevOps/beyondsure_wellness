@extends('layouts.admin')

@section('title', $product->exists ? 'Edit product' : 'New product')

@section('content')
    @php
        $finance = auth()->user()->canSeeFinance();
        $slabs = [[0, 'Exempt', 'Free benefits, basic items'], [5, 'AYUSH / Meds', null], [12, 'Wellness', null], [18, 'Supplements', null], [28, 'Luxury', null]];
        $sell = (float) old('sell_price', $product->sell_price ?? 0);
        $cost = (float) old('cost_price', $product->cost_price ?? 0);
        $rate = old('gst_pct', $product->gst_pct);
    @endphp

    <style>
        .pf-card { border-radius: 1rem; }
        .pf-card .card-header { background: transparent; border-bottom: 0; padding: 1.1rem 1.4rem .2rem; display: flex; gap: .8rem; align-items: center; }
        .pf-chip { width: 2.3rem; height: 2.3rem; border-radius: .65rem; display: grid; place-items: center; flex: none; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
        .pf-chip.good { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
        .pf-chip.warn { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
        .pf-card h2 { font-size: 1rem; font-weight: 700; margin: 0; }
        .pf-card .sub { font-size: .8rem; color: var(--bs-secondary-color); margin: 0; }
        .pf-card .card-body { padding: .9rem 1.4rem 1.3rem; }
        .pf-label { font-size: .8rem; font-weight: 600; margin-bottom: .3rem; }
        .pf-hint { font-size: .74rem; color: var(--bs-secondary-color); margin-top: .3rem; line-height: 1.4; }
        .slab { border: 1.5px solid var(--bs-border-color); background: var(--bs-body-bg); border-radius: .7rem; padding: .45rem .8rem; text-align: center; cursor: pointer; line-height: 1.2; transition: .12s; min-width: 5.2rem; }
        .slab b { display: block; font-size: .95rem; font-variant-numeric: tabular-nums; }
        .slab span { font-size: .66rem; color: var(--bs-secondary-color); }
        .slab:hover { border-color: var(--bs-primary); }
        .slab.on { border-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), .15); }
        .sum-row { display: flex; justify-content: space-between; gap: 1rem; padding: .5rem 0; font-size: .86rem; border-bottom: 1px dashed var(--bs-border-color); }
        .sum-row:last-child { border-bottom: 0; }
        .sum-row .v { font-weight: 650; font-variant-numeric: tabular-nums; text-align: right; }
        .sum-total { background: var(--bs-success-bg-subtle); border-radius: .75rem; padding: .7rem .9rem; margin-top: .6rem; }
        .sum-total .v { font-size: 1.35rem; font-weight: 700; color: var(--bs-success-text-emphasis); font-variant-numeric: tabular-nums; }
        .save-bar { position: sticky; bottom: 0; z-index: 5; background: var(--bs-body-bg); border-top: 1px solid var(--bs-border-color); margin: 0 -1.5rem -1.5rem; padding: .8rem 1.5rem; display: flex; gap: .6rem; align-items: center; }
    </style>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-0">{{ $product->exists ? 'Edit product' : 'New product' }}@if ($product->exists)<span class="text-secondary fw-normal"> · {{ $product->name }}</span>@endif</h2>
            <div class="small text-secondary">Products are bundled into plans. Each product carries its own GST rate.</div>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to products</a>
    </div>

    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="row g-4 align-items-start">
            <div class="col-lg-8 d-grid gap-4">

                {{-- 1. Identity --}}
                <section class="card border pf-card">
                    <div class="card-header">
                        <div class="pf-chip"><i class="bi bi-box-seam"></i></div>
                        <div><h2>What is this product?</h2><p class="sub">Name, SKU code and category.</p></div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="pf-label" for="category_id">Category <span class="text-danger">*</span></label>
                                <select name="category_id" id="category_id" required class="form-select @error('category_id') is-invalid @enderror">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" data-free="{{ $category->is_free ? 1 : 0 }}" @selected(old('category_id', $product->category_id) == $category->id)>
                                            {{ $category->name }}{{ $category->is_free ? ' — complimentary' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="pf-label" for="sku">SKU code <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                    <input name="sku" id="sku" value="{{ old('sku', $product->sku) }}" placeholder="e.g. SYR-001" required
                                        class="form-control font-monospace @error('sku') is-invalid @enderror">
                                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="pf-label" for="name">Product name <span class="text-danger">*</span></label>
                                <input name="name" id="name" value="{{ old('name', $product->name) }}" placeholder="Product or benefit name" required
                                    class="form-control @error('name') is-invalid @enderror">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="pf-label" for="description">Description <span class="text-secondary fw-normal">(optional)</span></label>
                                <textarea name="description" id="description" rows="2" placeholder="Shown on plan and bundle views"
                                    class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 2. Pricing & GST --}}
                <section class="card border pf-card">
                    <div class="card-header">
                        <div class="pf-chip warn"><i class="bi bi-receipt"></i></div>
                        <div><h2>Price &amp; GST</h2><p class="sub">GST is added on top of the price, and is set for this product alone.</p></div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @if ($finance)
                                <div class="col-sm-6">
                                    <label class="pf-label" for="cost_price">Cost price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input name="cost_price" id="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price ?? 0) }}" required
                                            class="form-control @error('cost_price') is-invalid @enderror">
                                        @error('cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="pf-hint">What this product costs you. Use 0 for free benefits.</div>
                                </div>
                            @endif
                            <div class="col-sm-6">
                                <label class="pf-label" for="sell_price">Retail price, excl. GST <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input name="sell_price" id="sell_price" type="number" step="0.01" min="0" value="{{ old('sell_price', $product->sell_price ?? 0) }}" required
                                        class="form-control @error('sell_price') is-invalid @enderror">
                                    @error('sell_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="pf-hint">Used to share a plan's price between its products.</div>
                            </div>

                            @if ($finance)
                                <div class="col-12">
                                    <label class="pf-label" for="gst_pct">GST rate <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-2 mb-2" id="slabs">
                                        @foreach ($slabs as [$pct, $label])
                                            <button type="button" class="slab" data-slab="{{ $pct }}"><b>{{ $pct }}%</b><span>{{ $label }}</span></button>
                                        @endforeach
                                    </div>
                                    <div class="input-group" style="max-width: 220px;">
                                        <input name="gst_pct" id="gst_pct" type="number" step="0.01" min="0" max="100" value="{{ $rate }}" placeholder="Other rate" required
                                            class="form-control font-monospace @error('gst_pct') is-invalid @enderror">
                                        <span class="input-group-text">%</span>
                                        @error('gst_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="pf-hint">Pick a standard slab or type any rate. Every product needs one — enter 0 if it is exempt.</div>
                                    <div class="alert alert-warning py-2 px-3 small mt-2 mb-0 d-none" id="gstMissing"><i class="bi bi-exclamation-triangle me-1"></i>No GST rate set yet. Until you choose one, this product is taxed at 0%.</div>
                                </div>
                            @else
                                <div class="col-12"><div class="pf-hint"><i class="bi bi-lock me-1"></i>Cost and GST are set by someone with Finance access.</div></div>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- 3. Benefit info --}}
                <section class="card border pf-card">
                    <div class="card-header">
                        <div class="pf-chip good"><i class="bi bi-gift"></i></div>
                        <div><h2>Complimentary benefit details</h2><p class="sub">Optional. Only displayed to members; has no effect on money.</p></div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="pf-label" for="benefit_group">Benefit group</label>
                                <input name="benefit_group" id="benefit_group" value="{{ old('benefit_group', $product->benefit_group) }}" placeholder="e.g. Everyday discounts"
                                    class="form-control @error('benefit_group') is-invalid @enderror">
                                @error('benefit_group') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="pf-label" for="usage_limit">Usage limit</label>
                                <input name="usage_limit" id="usage_limit" type="number" min="1" value="{{ old('usage_limit', $product->usage_limit) }}" placeholder="e.g. 12"
                                    class="form-control @error('usage_limit') is-invalid @enderror">
                                @error('usage_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="pf-label" for="usage_period">Per</label>
                                <select name="usage_period" id="usage_period" class="form-select @error('usage_period') is-invalid @enderror">
                                    <option value="">Unlimited</option>
                                    @foreach (['year' => 'Year', 'month' => 'Month', 'lifetime' => 'Lifetime'] as $v => $l)
                                        <option value="{{ $v }}" @selected(old('usage_period', $product->usage_period) === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                @error('usage_period') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Active + save --}}
                <section class="card border pf-card">
                    <div class="card-body pt-3">
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" role="switch" id="is_active" @checked(old('is_active', $product->exists ? $product->is_active : true))>
                            <label class="form-check-label fw-semibold" for="is_active">Active</label>
                            <div class="pf-hint mt-0">Inactive products can't be added to new plans.</div>
                        </div>
                        <div class="save-bar mt-3">
                            <button type="submit" class="btn btn-success px-4"><i class="bi bi-check-lg me-1"></i>{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Live summary --}}
            @if ($finance)
                <div class="col-lg-4">
                    <div class="card border pf-card sticky-top" style="top: 80px;">
                        <div class="card-header">
                            <div class="pf-chip good"><i class="bi bi-calculator"></i></div>
                            <div><h2>What the customer sees</h2><p class="sub">Updates as you type.</p></div>
                        </div>
                        <div class="card-body">
                            <div class="sum-row"><span>Retail price</span><span class="v" id="preview-base">₹0.00</span></div>
                            <div class="sum-row"><span>GST (<span id="preview-rate">0%</span>)</span><span class="v" id="preview-gst">₹0.00</span></div>
                            <div class="sum-total d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold">Customer pays</span><span class="v" id="preview-total">₹0.00</span>
                            </div>
                            <div class="sum-row mt-2"><span>Your margin <span class="text-secondary">(price − cost)</span></span><span class="v" id="preview-margin">₹0.00</span></div>
                            <div class="pf-hint">GST goes to the government and is never part of your margin or the member pool.</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const $ = id => document.getElementById(id);
                const cost = $('cost_price'), sell = $('sell_price'), gst = $('gst_pct'), cat = $('category_id');
                const inr = n => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                function refresh() {
                    const s = parseFloat(sell?.value) || 0, c = parseFloat(cost?.value) || 0;
                    const raw = gst?.value ?? '';
                    const r = raw === '' ? 0 : (parseFloat(raw) || 0);
                    const g = s * r / 100;
                    const set = (id, t) => { const el = $(id); if (el) el.textContent = t; };
                    set('preview-base', inr(s)); set('preview-rate', r.toString().replace(/\.0+$/, '') + '%');
                    set('preview-gst', inr(g)); set('preview-total', inr(s + g)); set('preview-margin', inr(s - c));
                    document.querySelectorAll('.slab').forEach(b => b.classList.toggle('on', raw !== '' && parseFloat(b.dataset.slab) === parseFloat(raw)));
                    $('gstMissing')?.classList.toggle('d-none', raw !== '');
                }

                document.querySelectorAll('.slab').forEach(b => b.addEventListener('click', () => { gst.value = b.dataset.slab; refresh(); }));
                [cost, sell, gst].forEach(el => el?.addEventListener('input', refresh));

                // A complimentary category is free, so start it at 0% / ₹0 when the admin hasn't entered anything yet.
                cat?.addEventListener('change', () => {
                    if (cat.selectedOptions[0]?.dataset.free === '1' && gst && gst.value === '') { gst.value = 0; refresh(); }
                });

                refresh();
            });
        </script>
    @endpush
@endsection
