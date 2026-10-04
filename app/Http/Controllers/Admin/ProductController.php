<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', ['products' => Product::with('category')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product, 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request, null));

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product->id));

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku,'.($ignoreId ?? 'NULL').',id'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'gst_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'benefit_group' => ['nullable', 'string', 'max:255'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_period' => ['nullable', 'in:year,month,lifetime'],
        ], [
            'category_id.required' => 'Please select a product category.',
            'category_id.exists' => 'The selected category does not exist.',
            'name.required' => 'Product name is required.',
            'sku.required' => 'The SKU code is required.',
            'sku.unique' => 'This SKU code is already assigned to another product.',
            'cost_price.required' => 'Cost price is required (enter 0 for complimentary items).',
            'cost_price.min' => 'Cost price cannot be negative.',
            'sell_price.required' => 'Sell price is required (taxable retail value).',
            'sell_price.min' => 'Sell price cannot be negative.',
            'gst_pct.min' => 'GST percentage cannot be negative.',
            'gst_pct.max' => 'GST percentage cannot exceed 100%.',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['gst_pct'] = $data['gst_pct'] !== null && $data['gst_pct'] !== '' ? $data['gst_pct'] : null;

        return $data;
    }
}
