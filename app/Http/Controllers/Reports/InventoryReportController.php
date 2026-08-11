<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\InventoryLedger;
use App\Models\ItemType;
use App\Models\PriceSetup;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Inventory Report: a master, per-product view of the catalog
 * (built on the `products` table and its `brand`/`item_type`/`product_group`
 * relations) combined with each product's current total on-hand stock and
 * an estimated stock value.
 *
 * Current stock reuses the same "latest inventory_ledgers row per
 * product+warehouse pair" pattern as PositionReportController and
 * InquiryController::stockForProducts(), summed across all warehouses per
 * product instead of broken out per warehouse — this report answers "how
 * much of this product do we have in total, across the whole business",
 * while the Position report answers "where is it".
 *
 * Stock value uses each product's most recent "Retail" price_setups row
 * (see Database\Seeders\PriceSetupSeeder) as the unit price; products
 * without a Retail price setup are valued at 0 rather than excluded.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by the other Reports\*
 * controllers.
 */
class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $brandId = $request->query('brand_id');
        $itemTypeId = $request->query('item_type_id');
        $productGroupId = $request->query('product_group_id');

        $productsQuery = Product::query()
            ->with(['brand', 'itemType', 'productGroup'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($itemTypeId, fn ($query) => $query->where('item_type_id', $itemTypeId))
            ->when($productGroupId, fn ($query) => $query->where('product_group_id', $productGroupId))
            ->orderBy('name');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating — same approach PositionReportController uses.
        $allFilteredIds = (clone $productsQuery)->pluck('id');
        $stockByProduct = $this->stockForProducts($allFilteredIds);
        $priceByProduct = $this->retailPriceForProducts($allFilteredIds);

        $totalProducts = $allFilteredIds->count();
        $totalStock = array_sum($stockByProduct);
        $totalValue = 0;
        foreach ($allFilteredIds as $productId) {
            $totalValue += ($stockByProduct[$productId] ?? 0) * ($priceByProduct[$productId] ?? 0);
        }

        $products = $productsQuery->paginate(20)->withQueryString();

        return view('reports.inventory.index', [
            'products' => $products,
            'search' => $search,
            'brandId' => $brandId,
            'itemTypeId' => $itemTypeId,
            'productGroupId' => $productGroupId,
            'brands' => Brand::orderBy('name')->get(),
            'itemTypes' => ItemType::orderBy('name')->get(),
            'productGroups' => ProductGroup::orderBy('name')->get(),
            'stockByProduct' => $stockByProduct,
            'priceByProduct' => $priceByProduct,
            'totalProducts' => $totalProducts,
            'totalStock' => $totalStock,
            'totalValue' => $totalValue,
        ]);
    }

    /**
     * Total on-hand stock per product, summed across every warehouse, using
     * the latest inventory_ledgers row per product+warehouse pair. Keyed by
     * product_id => int balance.
     */
    protected function stockForProducts(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $latestPerProductWarehouse = InventoryLedger::select('product_id', 'warehouse_id', DB::raw('MAX(id) as last_id'))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'warehouse_id');

        return InventoryLedger::joinSub($latestPerProductWarehouse, 'latest', function ($join) {
                $join->on('inventory_ledgers.id', '=', 'latest.last_id');
            })
            ->select('inventory_ledgers.product_id', DB::raw('SUM(inventory_ledgers.balance) as total_balance'))
            ->groupBy('inventory_ledgers.product_id')
            ->pluck('total_balance', 'product_id')
            ->map(fn ($balance) => (int) $balance)
            ->all();
    }

    /**
     * Most recent "Retail" price_setups amount per product (highest
     * effective_date wins, same tie-break as
     * InquiryController::pricesForProducts()). Keyed by product_id => float
     * amount.
     */
    protected function retailPriceForProducts(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return PriceSetup::query()
            ->whereIn('product_id', $productIds)
            ->where('price_category', 'Retail')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get(['product_id', 'amount'])
            ->groupBy('product_id')
            ->map(fn ($rows) => (float) $rows->first()->amount)
            ->all();
    }
}
