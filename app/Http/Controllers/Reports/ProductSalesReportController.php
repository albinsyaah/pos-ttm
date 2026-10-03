<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SaleDetail;
use App\Models\Warehouse;
use App\Services\SalesInsightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales per product that follows the price: one row per product and selling
 * price, so a product sold at Rp5.000 last week and Rp5.500 this week shows
 * two rows, each with its own quantity, revenue and date span. Free lines
 * (Rp0) get their own row. Quantities and revenue are net of sales returns, which are put on
 * the paid line they came from. Read-only.
 */
class ProductSalesReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $warehouseId = $request->query('warehouse_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $base = SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->leftJoinSub(SalesInsightService::returnedPerPaidLine(), 'rl', 'rl.line_id', '=', 'sale_details.id')
            ->when($warehouseId, fn ($q) => $q->where('sales.warehouse_id', $warehouseId))
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.sale_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.sale_date', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.code', 'like', "%{$search}%");
                });
            });

        $rows = (clone $base)
            ->selectRaw('sale_details.product_id, products.name as product_name, sale_details.price as unit_price,
                SUM(sale_details.qty) - COALESCE(SUM(rl.rqty), 0) as qty, COALESCE(SUM(rl.rqty), 0) as returned_qty,
                SUM((sale_details.qty - COALESCE(rl.rqty, 0)) * sale_details.price) as revenue,
                COUNT(DISTINCT sales.id) as sales_count, MIN(sales.sale_date) as first_date, MAX(sales.sale_date) as last_date')
            ->groupBy('sale_details.product_id', 'products.name', 'sale_details.price')
            ->orderBy('products.name')
            ->orderByDesc('sale_details.price')
            ->paginate(25)
            ->withQueryString();

        $productIds = $rows->getCollection()->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Products sold at more than one paid price in the filtered period.
        $priceChanged = (clone $base)
            ->where('sale_details.price', '>', 0)
            ->whereIn('sale_details.product_id', $productIds)
            ->selectRaw('sale_details.product_id, COUNT(DISTINCT sale_details.price) as n')
            ->groupBy('sale_details.product_id')
            ->havingRaw('COUNT(DISTINCT sale_details.price) > 1')
            ->pluck('product_id')
            ->all();

        $totals = (clone $base)
            ->selectRaw('COALESCE(SUM(sale_details.qty), 0) - COALESCE(SUM(rl.rqty), 0) as qty, COALESCE(SUM((sale_details.qty - COALESCE(rl.rqty, 0)) * sale_details.price), 0) as revenue')
            ->first();

        return view('reports.sales-by-product.index', [
            'rows' => $rows,
            'products' => $products,
            'priceChanged' => $priceChanged,
            'totalQty' => (int) $totals->qty,
            'totalRevenue' => (float) $totals->revenue,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'search' => $search,
            'warehouseId' => $warehouseId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
