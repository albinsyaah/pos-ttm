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
use App\Models\InventoryLedger;
use Illuminate\Support\Facades\DB;
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
            'stockByProduct' => $this->stockForProducts($products->pluck('id')),
            'search' => $search,
            'brands' => Brand::orderBy('name')->get(),
            'itemTypes' => ItemType::orderBy('name')->get(),
            'productGroups' => ProductGroup::orderBy('name')->get(),
        ]);
    }

    /**
     * Total on-hand stock (in satuan) per product across all warehouses, from
     * the latest inventory_ledgers row per product + warehouse.
     */
    protected function stockForProducts($productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $latest = InventoryLedger::select('product_id', 'warehouse_id', DB::raw('MAX(id) as last_id'))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'warehouse_id');

        return InventoryLedger::joinSub($latest, 'latest', function ($join) {
                $join->on('inventory_ledgers.id', '=', 'latest.last_id');
            })
            ->select('inventory_ledgers.product_id', DB::raw('SUM(inventory_ledgers.balance) as total_balance'))
            ->groupBy('inventory_ledgers.product_id')
            ->pluck('total_balance', 'product_id')
            ->map(fn ($b) => (int) $b)
            ->all();
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
        $validator = validator($request->all(), [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('products', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'unit_name' => ['required', 'string', 'max:30'],
            // Pack and box are optional. When a name is given the conversion
            // (isi) is required and must be a whole number of satuan >= 2.
            'pack_name' => ['nullable', 'string', 'max:30'],
            'pack_qty' => ['nullable', 'required_with:pack_name', 'integer', 'min:2', 'max:1000000'],
            'box_name' => ['nullable', 'string', 'max:30'],
            'box_qty' => ['nullable', 'required_with:box_name', 'integer', 'min:2', 'max:1000000'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'item_type_id' => ['nullable', 'exists:item_types,id'],
            'product_group_id' => ['nullable', 'exists:product_groups,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            // A quantity without a name is meaningless; ask for the name.
            foreach (['pack', 'box'] as $kind) {
                if (filled($request->input("{$kind}_qty")) && blank($request->input("{$kind}_name"))) {
                    $validator->errors()->add("{$kind}_name", __('app.products.name_required_with_qty', ['kind' => $kind]));
                }
            }

            // Pack and box are independent, both counted in satuan. The only
            // sanity check: when both exist, a box must hold more than a pack.
            $pack = (int) $request->input('pack_qty');
            $box = (int) $request->input('box_qty');
            if ($pack > 1 && $box > 1 && $box <= $pack) {
                $validator->errors()->add('box_qty', __('app.products.box_must_exceed_pack'));
            }
        });

        $data = $validator->validate();

        // Normalise empty strings to null so a cleared pack/box really clears.
        foreach (['pack_name', 'pack_qty', 'box_name', 'box_qty'] as $key) {
            if (blank($data[$key] ?? null)) {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
