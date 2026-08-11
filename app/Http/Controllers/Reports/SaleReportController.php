<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\SaleController;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * Read-only Sales report: lets any user with reports.view filter regular
 * (non point-of-sale) sales by date range, customer, warehouse and free
 * text, and see per-sale + grand totals. No create/update/delete — this is
 * reporting only, unlike Transactions\SaleController which owns the CRUD.
 *
 * Only records tagged with SaleController::SOURCE are included, matching
 * the same filter the transaction list applies, so Point of Sale
 * transactions (which share the `sales` table) stay out of this report.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController / PurchaseReportController /
 * PurchaseReturnReportController / ApPaymentReportController /
 * SalesOrderReportController.
 */
class SaleReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');
        $warehouseId = $request->query('warehouse_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Sale::query()
            ->where('source', SaleController::SOURCE)
            ->with(['customer', 'warehouse', 'salesman', 'saleDetails.product'])
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
            ->when($dateFrom, fn ($q) => $q->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sale_date', '<=', $dateTo))
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. total_amount is already stored on the row (see
        // Transactions\SaleController), so this is a plain sum.
        $totalSales = (clone $query)->count();
        $totalAmount = (clone $query)->sum('total_amount');

        $sales = $query->paginate(20)->withQueryString();

        return view('reports.sales.index', [
            'sales' => $sales,
            'search' => $search,
            'customerId' => $customerId,
            'warehouseId' => $warehouseId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'customers' => Customer::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'totalSales' => $totalSales,
            'totalAmount' => $totalAmount,
        ]);
    }
}
