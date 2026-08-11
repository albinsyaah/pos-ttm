<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\SalesOrderController;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

/**
 * Read-only Sales Order report: lets any user with reports.view filter
 * sales orders by date range, customer, status and free text, and see
 * per-order + grand totals. No create/update/delete — this is reporting
 * only, unlike Transactions\SalesOrderController which owns the CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController / PurchaseReportController /
 * PurchaseReturnReportController / ApPaymentReportController.
 */
class SalesOrderReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = SalesOrder::query()
            ->with(['customer', 'salesOrderDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('so_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('order_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('order_date', '<=', $dateTo))
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. Same "qty * price" calculation the transaction list
        // uses per-row, just rolled up across every matching order (sales
        // orders have no stored total_amount column — see
        // PurchaseOrderReportController for the same pattern).
        $allFiltered = (clone $query)->with('salesOrderDetails:id,sales_order_id,qty,price')->get();
        $totalOrders = $allFiltered->count();
        $totalAmount = $allFiltered->sum(
            fn ($salesOrder) => $salesOrder->salesOrderDetails->sum(fn ($d) => $d->qty * $d->price)
        );

        $salesOrders = $query->paginate(20)->withQueryString();

        return view('reports.sales-orders.index', [
            'salesOrders' => $salesOrders,
            'search' => $search,
            'customerId' => $customerId,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'customers' => Customer::orderBy('name')->get(),
            'statuses' => SalesOrderController::STATUSES,
            'totalOrders' => $totalOrders,
            'totalAmount' => $totalAmount,
        ]);
    }
}
