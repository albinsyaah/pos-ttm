<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\InventoryLedger;
use App\Models\ItemType;
use App\Models\PriceHistory;
use App\Models\PriceSetup;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only "Inquiry" tool: lets any user with inquiry.view quickly search
 * products and see their current stock (per warehouse, from the latest
 * inventory_ledgers row per product/warehouse pair), current price setups and
 * the log of price changes, without needing access to the full Inventory /
 * Pricing / Warehouse modules.
 */
class InquiryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $brandId = $request->query('brand_id');
        $itemTypeId = $request->query('item_type_id');
        $warehouseId = $request->query('warehouse_id');

        $products = Product::query()
            ->with(['brand', 'itemType'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($itemTypeId, fn ($query) => $query->where('item_type_id', $itemTypeId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $productIds = $products->pluck('id');

        return view('inquiry.index', [
            'products' => $products,
            'search' => $search,
            'brandId' => $brandId,
            'itemTypeId' => $itemTypeId,
            'warehouseId' => $warehouseId,
            'brands' => Brand::orderBy('name')->get(),
            'itemTypes' => ItemType::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'stockByProduct' => $this->stockForProducts($productIds, $warehouseId),
            'pricesByProduct' => $this->pricesForProducts($productIds),
            'historyByProduct' => $this->priceHistoryForProducts($productIds),
        ]);
    }

    /**
     * Latest inventory_ledgers balance per product/warehouse (same
     * "latest row per group" pattern used by DashboardController's
     * lowStockProducts), keyed by product_id.
     *
     * Returns: [$productId => ['total' => int, 'warehouses' => [['name' => .., 'balance' => ..], ...]]]
     */
    protected function stockForProducts(Collection $productIds, ?string $warehouseId): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $latestPerProductWarehouse = InventoryLedger::select('product_id', 'warehouse_id', DB::raw('MAX(id) as last_id'))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'warehouse_id');

        $rows = InventoryLedger::joinSub($latestPerProductWarehouse, 'latest', function ($join) {
                $join->on('inventory_ledgers.id', '=', 'latest.last_id');
            })
            ->join('warehouses', 'warehouses.id', '=', 'inventory_ledgers.warehouse_id')
            ->when($warehouseId, fn ($query) => $query->where('inventory_ledgers.warehouse_id', $warehouseId))
            ->orderBy('warehouses.name')
            ->select(
                'inventory_ledgers.product_id',
                'warehouses.name as warehouse_name',
                'inventory_ledgers.balance'
            )
            ->get();

        $stock = [];

        foreach ($rows as $row) {
            $stock[$row->product_id]['total'] = ($stock[$row->product_id]['total'] ?? 0) + (int) $row->balance;
            $stock[$row->product_id]['warehouses'][] = [
                'name' => $row->warehouse_name,
                'balance' => (int) $row->balance,
            ];
        }

        return $stock;
    }

    /**
     * Current price per category for each product (most recent effective_date
     * wins per price_category), keyed by product_id.
     */
    protected function pricesForProducts(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return PriceSetup::query()
            ->whereIn('product_id', $productIds)
            // A price dated in the future does not apply yet.
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->unique('price_category')->values())
            ->all();
    }

    /** How many recent price changes the Inquiry page shows per product. */
    public const HISTORY_LIMIT = 10;

    /**
     * Latest price changes (who, when, from what to what) for each product on
     * the page, newest first, keyed by product_id. One small query per product
     * so each product keeps its own last HISTORY_LIMIT rows.
     */
    protected function priceHistoryForProducts(Collection $productIds): array
    {
        $history = [];

        foreach ($productIds as $productId) {
            $history[$productId] = PriceHistory::query()
                ->with('user')
                ->where('product_id', $productId)
                ->orderByDesc('id')
                ->limit(self::HISTORY_LIMIT)
                ->get();
        }

        return $history;
    }
}
