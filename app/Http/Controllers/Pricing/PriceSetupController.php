<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\PriceHistory;
use App\Models\PriceSetup;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($data) {
            $created = PriceSetup::create($data);
            PriceHistory::record(PriceHistory::CREATED, null, $created);
        });

        return redirect()->route('pricing.price-setups.index')->with('success', __('Price setup added successfully.'));
    }

    public function update(Request $request, PriceSetup $priceSetup): RedirectResponse
    {
        $data = $this->validatePriceSetup($request);

        DB::transaction(function () use ($priceSetup, $data) {
            $before = clone $priceSetup;
            $priceSetup->update($data);

            // Log only real changes; saving the form untouched writes nothing.
            // (Compared by value: "1000" from the form equals "1000.00" in the DB.)
            $changed = (int) $before->product_id !== (int) $priceSetup->product_id
                || $before->price_category !== $priceSetup->price_category
                || abs((float) $before->amount - (float) $priceSetup->amount) > 0.004
                || Carbon::parse($before->effective_date)->toDateString() !== Carbon::parse($priceSetup->effective_date)->toDateString();

            if ($changed) {
                PriceHistory::record(PriceHistory::UPDATED, $before, $priceSetup);
            }
        });

        return redirect()->route('pricing.price-setups.index')->with('success', __('Price setup updated successfully.'));
    }

    public function destroy(PriceSetup $priceSetup): RedirectResponse
    {
        DB::transaction(function () use ($priceSetup) {
            $before = clone $priceSetup;
            $priceSetup->delete();
            PriceHistory::record(PriceHistory::DELETED, $before, null);
        });

        return redirect()->route('pricing.price-setups.index')->with('success', __('Price setup deleted successfully.'));
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
