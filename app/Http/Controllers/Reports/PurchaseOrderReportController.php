<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\PurchaseOrderController;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Read-only Purchase Order report: lets any user with reports.view filter
 * purchase orders by date range, supplier, status and free text, and see
 * per-order + grand totals. No create/update/delete — this is reporting
 * only, unlike Transactions\PurchaseOrderController which owns the CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController.
 */
class PurchaseOrderReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $supplierId = $request->query('supplier_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = PurchaseOrder::query()
            ->with(['supplier', 'purchaseOrderDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('order_date', '<=', $dateTo))
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. Same "qty * price" calculation the transaction list
        // uses per-row, just rolled up across every matching order.
        $allFiltered = (clone $query)->with('purchaseOrderDetails:id,purchase_order_id,qty,price')->get();
        $totalOrders = $allFiltered->count();
        $totalAmount = $allFiltered->sum(
            fn ($purchaseOrder) => $purchaseOrder->purchaseOrderDetails->sum(fn ($d) => $d->qty * $d->price)
        );

        $purchaseOrders = $query->paginate(20)->withQueryString();

        return view('reports.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'search' => $search,
            'supplierId' => $supplierId,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'suppliers' => Supplier::orderBy('name')->get(),
            'statuses' => PurchaseOrderController::STATUSES,
            'totalOrders' => $totalOrders,
            'totalAmount' => $totalAmount,
        ]);
    }
}
