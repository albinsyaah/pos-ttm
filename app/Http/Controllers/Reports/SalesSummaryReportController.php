<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\SalesInsightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Sales Summary report ("Laporan Penjualan"): unlike
 * SaleReportController (the "Sales Report" page, which only covers regular
 * invoiced sales — source = 'sales'), this report rolls up every sales
 * channel that shares the `sales` table: regular Sales, Point of Sale, and
 * Sales SPG (see Transactions\SaleController::SOURCE /
 * PointOfSaleController::SOURCE / SalesSpgController::SOURCE). It lets any
 * user with reports.view filter by date range, customer, warehouse and an
 * optional channel, and shows a per-channel breakdown alongside the grand
 * totals and the cost of the free items given away in those sales. No create/update/delete — this is reporting only.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by the other report
 * controllers in this namespace.
 */
class SalesSummaryReportController extends Controller
{
    /**
     * Every distinct `source` value the `sales` table is tagged with,
     * across the three transaction pages that share it.
     */
    public const SOURCES = ['sales', 'pos', 'spg'];

    public function index(Request $request, SalesInsightService $insight)
    {
        $search = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');
        $warehouseId = $request->query('warehouse_id');
        $source = $request->query('source');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Sale::query()
            ->with(['customer', 'warehouse', 'saleDetails'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($dateFrom, fn ($q) => $q->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sale_date', '<=', $dateTo))
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        // Grand totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. total_amount is already stored on the row (see
        // Transactions\SaleController), so this is a plain sum.
        $totalSales = (clone $query)->count();
        $grossAmount = (float) (clone $query)->sum('total_amount');
        $returned = $insight->returnsForSales($query);
        $totalAmount = $grossAmount - $returned['total'];

        // Per-channel breakdown (count + amount for each of sales/pos/spg
        // within the current filters), computed as one grouped aggregate
        // query rather than pulling every row into memory.
        $bySource = (clone $query)
            ->select('source', DB::raw('count(*) as total_count'), DB::raw('sum(total_amount) as total_amount'))
            ->groupBy('source')
            ->reorder()
            ->get()
            ->keyBy('source');

        // Net of returns, per channel.
        foreach ($bySource as $channelKey => $channel) {
            $channel->total_amount = (float) $channel->total_amount - ($returned['by_source'][$channelKey] ?? 0.0);
        }

        // Free items (lines priced Rp0) cost the shop their last purchase price.
        // Computed over the whole filtered set so the card matches the filters.
        $freeLoss = $insight->freeGoodsLoss($query);

        $sales = $query->withSum('salesReturns as returned_total', 'total_amount')->paginate(20)->withQueryString();

        return view('reports.sales-summary.index', [
            'sales' => $sales,
            'search' => $search,
            'customerId' => $customerId,
            'warehouseId' => $warehouseId,
            'source' => $source,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'customers' => Customer::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'sources' => self::SOURCES,
            'totalSales' => $totalSales,
            'totalAmount' => $totalAmount,
            'bySource' => $bySource,
            'freeLoss' => $freeLoss,
            'grossAmount' => $grossAmount,
            'returnsTotal' => $returned['total'],
        ]);
    }
}
