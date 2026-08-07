<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\PurchaseController;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * Read-only Purchase report: lets any user with reports.view filter
 * purchases by date range, supplier, warehouse, status and free text, and
 * see per-purchase + grand totals. No create/update/delete — this is
 * reporting only, unlike Transactions\PurchaseController which owns the
 * CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController.
 */
class PurchaseReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $supplierId = $request->query('supplier_id');
        $warehouseId = $request->query('warehouse_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Purchase::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder', 'purchaseDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('purchase_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('purchase_date', '<=', $dateTo))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. total_amount is already stored on the row (see
        // Transactions\PurchaseController), so this is a plain sum.
        $totalPurchases = (clone $query)->count();
        $totalAmount = (clone $query)->sum('total_amount');

        $purchases = $query->paginate(20)->withQueryString();

        return view('reports.purchases.index', [
            'purchases' => $purchases,
            'search' => $search,
            'supplierId' => $supplierId,
            'warehouseId' => $warehouseId,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'statuses' => PurchaseController::STATUSES,
            'totalPurchases' => $totalPurchases,
            'totalAmount' => $totalAmount,
        ]);
    }
}
