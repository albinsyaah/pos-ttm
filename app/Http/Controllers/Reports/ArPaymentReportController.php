<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\ArPaymentController;
use App\Models\ArPayment;
use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * Read-only Receivable Payment report: lets any user with reports.view
 * filter AR payments by date range, customer, payment method and free
 * text, and see per-payment + grand totals. No create/update/delete —
 * this is reporting only, unlike Transactions\ArPaymentController which
 * owns the CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController / PurchaseReportController /
 * PurchaseReturnReportController / ApPaymentReportController /
 * SalesOrderReportController / SaleReportController /
 * SalesReturnReportController.
 */
class ArPaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');
        $paymentMethod = $request->query('payment_method');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = ArPayment::query()
            ->with('customer')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($paymentMethod, fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. amount is already stored on the row (see
        // Transactions\ArPaymentController), so this is a plain sum.
        $totalPayments = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');

        $receivablePayments = $query->paginate(20)->withQueryString();

        return view('reports.receivable-payments.index', [
            'receivablePayments' => $receivablePayments,
            'search' => $search,
            'customerId' => $customerId,
            'paymentMethod' => $paymentMethod,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'customers' => Customer::orderBy('name')->get(),
            'paymentMethods' => ArPaymentController::PAYMENT_METHODS,
            'totalPayments' => $totalPayments,
            'totalAmount' => $totalAmount,
        ]);
    }
}
