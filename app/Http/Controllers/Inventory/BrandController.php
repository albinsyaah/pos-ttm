<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BrandController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.brands.view', only: ['index']),
            new Middleware('permission:inventory.brands.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $brands = Brand::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.brands.index', [
            'brands' => $brands,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBrand($request);

        Brand::create($data);

        return redirect()->route('inventory.brands.index')->with('success', __('Brand added successfully.'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validateBrand($request);

        $brand->update($data);

        return redirect()->route('inventory.brands.index')->with('success', __('Brand updated successfully.'));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', __('This brand is still used by one or more products.'));
        }

        $brand->delete();

        return redirect()->route('inventory.brands.index')->with('success', __('Brand deleted successfully.'));
    }

    protected function validateBrand(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);
    }
}
