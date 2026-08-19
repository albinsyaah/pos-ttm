<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ItemType;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.products.view', only: ['index']),
            new Middleware('permission:inventory.products.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->with(['brand', 'itemType', 'productGroup'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.products.index', [
            'products' => $products,
            'search' => $search,
            'brands' => Brand::orderBy('name')->get(),
            'itemTypes' => ItemType::orderBy('name')->get(),
            'productGroups' => ProductGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);

        Product::create($data);

        return redirect()->route('inventory.products.index')->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateProduct($request, $product->id);

        $product->update($data);

        return redirect()->route('inventory.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $hasMovement = $product->inventoryLedgers()->exists()
            || $product->purchaseDetails()->exists()
            || $product->saleDetails()->exists();

        if ($hasMovement) {
            return back()->with('error', 'This product already has transaction history and cannot be deleted.');
        }

        $product->delete();

        return redirect()->route('inventory.products.index')->with('success', 'Product deleted successfully.');
    }

    protected function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('products', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'item_type_id' => ['nullable', 'exists:item_types,id'],
            'product_group_id' => ['nullable', 'exists:product_groups,id'],
        ]);
    }
}
