<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\PriceSetup;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PriceSetupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:pricing.view', only: ['index']),
            new Middleware('permission:pricing.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $priceSetups = PriceSetup::query()
            ->with('product')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('price_category', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($productQuery) use ($search) {
                            $productQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('effective_date')
            ->paginate(20)
            ->withQueryString();

        return view('pricing.price-setups.index', [
            'priceSetups' => $priceSetups,
            'search' => $search,
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePriceSetup($request);

        PriceSetup::create($data);

        return redirect()->route('pricing.price-setups.index')->with('success', 'Price setup added successfully.');
    }

    public function update(Request $request, PriceSetup $priceSetup): RedirectResponse
    {
        $data = $this->validatePriceSetup($request);

        $priceSetup->update($data);

        return redirect()->route('pricing.price-setups.index')->with('success', 'Price setup updated successfully.');
    }

    public function destroy(PriceSetup $priceSetup): RedirectResponse
    {
        $priceSetup->delete();

        return redirect()->route('pricing.price-setups.index')->with('success', 'Price setup deleted successfully.');
    }

    protected function validatePriceSetup(Request $request): array
    {
        return $request->validate([
            'price_category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'effective_date' => ['required', 'date'],
            'product_id' => ['required', 'exists:products,id'],
        ]);
    }
}
