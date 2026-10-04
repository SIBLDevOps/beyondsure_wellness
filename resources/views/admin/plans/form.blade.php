@extends('layouts.admin')

@section('title', $plan->exists ? 'Edit plan' : 'New plan')

@section('content')
    @php
        $qtyOf = fn ($productId) => $plan->exists ? optional($plan->products->firstWhere('id', $productId))->pivot?->qty : 0;
    @endphp

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold">
                        <i class="bi bi-layers me-2 text-primary"></i>
                        {{ $plan->exists ? 'Edit Plan Bundle — ' . $plan->name : 'Create New Plan Bundle' }}
                    </h2>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Plans
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}">
                        @csrf
                        @if ($plan->exists) @method('PUT') @endif

                        {{-- 1. Plan Details & Pricing --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                1. Bundle Identity &amp; Pricing
                            </legend>
                            <div class="row g-3 mt-1">
                                <div class="col-12">
                                    <label class="form-label small fw-medium">Plan Name <span class="text-danger">*</span></label>
                                    <input name="name" value="{{ old('name', $plan->name) }}" placeholder="e.g. BeyondSure Essential / Plus / Pro" required
                                        class="form-control @error('name') is-invalid @enderror">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-medium">Description</label>
                                    <textarea name="description" rows="2" placeholder="Summary of who this bundle is for…"
                                        class="form-control @error('description') is-invalid @enderror">{{ old('description', $plan->description) }}</textarea>
                                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-medium">Plan Price (excl. GST) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">₹</span>
                                        <input id="input-price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $plan->price) }}" required
                                            class="form-control @error('price') is-invalid @enderror">
                                        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-text" style="font-size: .72rem;">
                                        The taxable base. GST is added on top at checkout —
                                        @if ($plan->exists)
                                            currently ₹{{ number_format($plan->gstAmount(), 2) }}, for a total payable of ₹{{ number_format($plan->totalPayable(), 2) }}.
                                        @else
                                            set the bundle's products first to see the exact GST.
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-medium">Business Volume / BV <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">₹</span>
                                        <input id="input-bv" name="bv" type="number" step="0.01" min="0" value="{{ old('bv', $plan->bv) }}" required
                                            class="form-control @error('bv') is-invalid @enderror">
                                        @error('bv') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-text" style="font-size: .72rem;">Basis for Self, Sponsor, Matching, and Rank payouts.</div>
                                </div>
                            </div>
                        </fieldset>

                        {{-- 2. Bundle Composition Table --}}
                        <fieldset class="border rounded-3 p-3 mb-4">
                            <legend class="float-none w-auto small fw-semibold text-secondary px-2 mb-0" style="font-size: .78rem;">
                                2. Bundle Composition (Products &amp; Benefits Included)
                            </legend>
                            <div class="table-responsive border rounded-3 mt-2">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light text-secondary small">
                                        <tr>
                                            <th class="ps-3">Product / Benefit</th>
                                            <th>Category</th>
                                            <th class="text-end">Unit Cost</th>
                                            <th class="text-center" style="width: 125px;">Qty in Bundle</th>
                                            <th class="text-end pe-3">Line Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($products as $product)
                                            @php $curQty = old('qty.'.$product->id, $qtyOf($product->id) ?? 0); @endphp
                                            <tr class="bundle-product-row">
                                                <td class="ps-3">
                                                    <div class="fw-medium small">{{ $product->name }}</div>
                                                    <div class="font-monospace text-body-tertiary" style="font-size: .68rem;">{{ $product->sku }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-secondary border" style="font-size: .68rem;">
                                                        {{ $product->category?->name ?? 'General' }}
                                                    </span>
                                                </td>
                                                <td class="text-end small text-secondary">₹{{ number_format($product->cost_price, 2) }}</td>
                                                <td class="py-1">
                                                    <input type="number" min="0" data-cost="{{ $product->cost_price }}"
                                                           class="qty-input form-control form-control-sm text-center"
                                                           name="qty[{{ $product->id }}]"
                                                           value="{{ $curQty }}">
                                                </td>
                                                <td class="text-end pe-3 small fw-semibold line-cost-cell">
                                                    ₹{{ number_format(((float) $curQty) * ((float) $product->cost_price), 0) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light fw-semibold small">
                                        <tr>
                                            <td colspan="4" class="ps-3">Total Actual Bundle Cost</td>
                                            <td class="text-end pe-3 text-primary" id="table-total-cost">₹0</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </fieldset>

                        <div class="p-3 border rounded-3 bg-light mb-4">
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $plan->exists ? $plan->is_active : true))>
                                <label class="form-check-label fw-medium small" for="is_active">Active &amp; Available for Purchase</label>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 pt-2 border-top">
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-check-lg me-1"></i>Save Plan
                            </button>
                            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Live preview --}}
        <div class="col-lg-4">
            <div class="card border sticky-top" style="top: 80px;">
                <div class="card-header bg-light-subtle border-bottom py-3">
                    <div class="fw-semibold"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Live Financial Preview</div>
                    <div class="text-body-tertiary" style="font-size: .75rem;">Updates as you type — shows cost cap &amp; BV payouts</div>
                </div>
                <div class="card-body">
                    <div class="small text-secondary mb-1">Price split (target)</div>
                    <div class="split-bar">
                        <div id="bar-cost" class="bg-warning" style="width: {{ $settings['costPct'] }}%"></div>
                        <div class="bg-primary" style="width: {{ $settings['poolPct'] }}%"></div>
                        <div class="bg-secondary" style="width: {{ $settings['companyPct'] }}%"></div>
                    </div>

                    <table class="table table-sm align-middle mt-3 mb-0 small">
                        <tbody>
                            <tr>
                                <td class="text-secondary">Cost target ({{ $settings['costPct'] }}%)</td>
                                <td class="text-end fw-semibold" id="preview-cost-target">₹0</td>
                            </tr>
                            <tr>
                                <td class="text-secondary">Actual bundle cost</td>
                                <td class="text-end fw-bold" id="preview-actual-cost">₹0</td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="preview-cap-warning" class="alert alert-danger py-2 px-3 small mt-2 mb-0 d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Exceeds cost target!
                    </div>

                    <div class="mt-3 pt-3 border-top">
                        <div class="small text-secondary mb-2">Compensation from this plan's BV</div>
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="bg-success-subtle rounded-3 py-2">
                                    <div id="preview-self" class="fw-semibold text-success">₹0</div>
                                    <div class="text-secondary" style="font-size: .68rem;">Self ({{ $settings['selfPct'] }}%)</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-info-subtle rounded-3 py-2">
                                    <div id="preview-sponsor" class="fw-semibold text-info-emphasis">₹0</div>
                                    <div class="text-secondary" style="font-size: .68rem;">Sponsor ({{ $settings['sponsorPct'] }}%)</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="rounded-3 py-2" style="background:#ede9fe;">
                                    <div id="preview-matching" class="fw-semibold" style="color:#6d28d9;">₹0</div>
                                    <div class="text-secondary" style="font-size: .68rem;">Per match ({{ $settings['matchingPct'] }}%)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const priceInput = document.getElementById('input-price');
            const bvInput = document.getElementById('input-bv');
            const qtyInputs = document.querySelectorAll('.qty-input');
            const tableTotalCost = document.getElementById('table-total-cost');
            const settings = {!! json_encode($settings) !!};

            function fmt(n) {
                return '₹' + Math.round(n).toLocaleString('en-IN');
            }

            function recalc() {
                const price = parseFloat(priceInput.value) || 0;
                const bv = parseFloat(bvInput.value) || 0;

                const costTarget = price * settings.costPct / 100;
                let actualCost = 0;
                qtyInputs.forEach(function (input) {
                    const qty = parseFloat(input.value) || 0;
                    const unitCost = parseFloat(input.dataset.cost) || 0;
                    const lineCost = qty * unitCost;
                    actualCost += lineCost;
                    const row = input.closest('.bundle-product-row');
                    if (row) {
                        const cell = row.querySelector('.line-cost-cell');
                        if (cell) cell.textContent = fmt(lineCost);
                    }
                });

                document.getElementById('preview-cost-target').textContent = fmt(costTarget);
                document.getElementById('preview-actual-cost').textContent = fmt(actualCost);
                if (tableTotalCost) tableTotalCost.textContent = fmt(actualCost);

                const warning = document.getElementById('preview-cap-warning');
                const bar = document.getElementById('bar-cost');
                if (actualCost > costTarget && price > 0) {
                    warning.classList.remove('d-none');
                    bar.classList.remove('bg-warning');
                    bar.classList.add('bg-danger');
                } else {
                    warning.classList.add('d-none');
                    bar.classList.add('bg-warning');
                    bar.classList.remove('bg-danger');
                }

                document.getElementById('preview-self').textContent = fmt(bv * settings.selfPct / 100);
                document.getElementById('preview-sponsor').textContent = fmt(bv * settings.sponsorPct / 100);
                document.getElementById('preview-matching').textContent = fmt(bv * settings.matchingPct / 100);
            }

            priceInput.addEventListener('input', recalc);
            bvInput.addEventListener('input', recalc);
            qtyInputs.forEach(function (input) { input.addEventListener('input', recalc); });
            recalc();
        })();
    </script>
@endsection