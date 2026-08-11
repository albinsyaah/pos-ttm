<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesReturn;
use Illuminate\Http\Request;

/**
 * Read-only Sales Return report: lets any user with reports.view filter
 * sales returns by date range, customer (via the related sale) and free
 * text, and see per-return + grand totals. No create/update/delete — this
 * is reporting only, unlike Transactions\SalesReturnController which owns
 * the CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController / PurchaseReportController /
 * PurchaseReturnReportController / ApPaymentReportController /
 * SalesOrderReportController / SaleReportController.
 */
class SalesReturnReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = SalesReturn::query()
            ->with(['sale.customer', 'salesReturnDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('sale', function ($saleQuery) use ($search) {
                            $saleQuery->where('invoice_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($customerId, function ($q) use ($customerId) {
                $q->whereHas('sale', fn ($saleQuery) => $saleQuery->where('customer_id', $customerId));
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('return_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('return_date', '<=', $dateTo))
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. total_amount is already stored on the row (see
        // Transactions\SalesReturnController), so this is a plain sum.
        $totalReturns = (clone $query)->count();
        $totalAmount = (clone $query)->sum('total_amount');

        $salesReturns = $query->paginate(20)->withQueryString();

        return view('reports.sales-returns.index', [
            'salesReturns' => $salesReturns,
            'search' => $search,
            'customerId' => $customerId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'customers' => Customer::orderBy('name')->get(),
            'totalReturns' => $totalReturns,
            'totalAmount' => $totalAmount,
        ]);
    }
}
