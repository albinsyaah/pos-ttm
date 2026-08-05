<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ProductGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view', only: ['index']),
            new Middleware('permission:inventory.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $productGroups = ProductGroup::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('inventory.product-groups.index', [
            'productGroups' => $productGroups,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProductGroup($request);

        ProductGroup::create($data);

        return redirect()->route('inventory.product-groups.index')->with('success', 'Product group added successfully.');
    }

    public function update(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $data = $this->validateProductGroup($request);

        $productGroup->update($data);

        return redirect()->route('inventory.product-groups.index')->with('success', 'Product group updated successfully.');
    }

    public function destroy(ProductGroup $productGroup): RedirectResponse
    {
        if ($productGroup->products()->exists()) {
            return back()->with('error', 'This product group is still used by one or more products.');
        }

        $productGroup->delete();

        return redirect()->route('inventory.product-groups.index')->with('success', 'Product group deleted successfully.');
    }

    protected function validateProductGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);
    }
}
