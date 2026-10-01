<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedger;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Position ("Kartu Posisi Stok") report: a snapshot of current
 * on-hand stock per product per warehouse, built on the same
 * `inventory_ledgers` table used by the Stock Card report — but showing
 * only the latest balance per product+warehouse pair instead of the full
 * movement history. Same "latest row per group" query
 * DashboardController::lowStockProducts() uses for the dashboard's low
 * stock widget, generalized here with filters, a per-warehouse breakdown
 * and pagination instead of a fixed top-8 list summed across warehouses.
 *
 * Only product+warehouse pairs with at least one ledger entry are shown
 * (matching the dashboard's low-stock widget) — a product that has never
 * moved stock in a warehouse has no row to report a position for.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by the other
 * Reports\* controllers.
 */
class PositionReportController extends Controller
{
    /**
     * Rows at or below this balance are flagged as low stock, matching
     * DashboardController::$lowStockThreshold.
     */
    protected int $lowStockThreshold = 10;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $warehouseId = $request->query('warehouse_id');
        $lowStockOnly = $request->boolean('low_stock_only');

        $latestPerProductWarehouse = InventoryLedger::select('product_id', 'warehouse_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('product_id', 'warehouse_id');

        $query = InventoryLedger::query()
            ->joinSub($latestPerProductWarehouse, 'latest', function ($join) {
                $join->on('inventory_ledgers.id', '=', 'latest.last_id');
            })
            ->join('products', 'products.id', '=', 'inventory_ledgers.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_ledgers.warehouse_id')
            ->select(
                'products.id as product_id',
                'products.code as product_code',
                'products.name as product_name',
                'products.unit_name',
                'products.pack_name',
                'products.pack_qty',
                'products.box_name',
                'products.box_qty',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'inventory_ledgers.balance'
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('products.code', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%");
                });
            })
            ->when($warehouseId, fn ($q) => $q->where('warehouses.id', $warehouseId))
            ->when($lowStockOnly, fn ($q) => $q->where('inventory_ledgers.balance', '<', $this->lowStockThreshold))
            ->orderBy('products.name')
            ->orderBy('warehouses.name');

        // Summary totals across the whole filtered result set (not just
        // the current page), so the cards above the table stay accurate
        // while paginating.
        $allFiltered = (clone $query)->get();
        $totalProducts = $allFiltered->pluck('product_id')->unique()->count();
        $totalBalance = $allFiltered->sum('balance');
        $lowStockCount = $allFiltered->where('balance', '<', $this->lowStockThreshold)->count();

        $positions = $query->paginate(20)->withQueryString();

        return view('reports.position.index', [
            'positions' => $positions,
            'search' => $search,
            'warehouseId' => $warehouseId,
            'lowStockOnly' => $lowStockOnly,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'lowStockThreshold' => $this->lowStockThreshold,
            'totalProducts' => $totalProducts,
            'totalBalance' => $totalBalance,
            'lowStockCount' => $lowStockCount,
        ]);
    }
}
