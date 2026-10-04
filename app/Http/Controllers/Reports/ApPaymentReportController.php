<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ApPayment;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Read-only Payable Payment report: lets any user with reports.view filter
 * AP payments by date range, supplier, payment method and free text, and
 * see per-payment + grand totals. No create/update/delete — this is
 * reporting only, unlike Transactions\ApPaymentController which owns the
 * CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController / PurchaseReportController /
 * PurchaseReturnReportController.
 */
class ApPaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $supplierId = $request->query('supplier_id');
        $paymentMethod = $request->query('payment_method_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = ApPayment::query()
            ->with(['supplier', 'paymentMethod'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($paymentMethod, fn ($q) => $q->where('payment_method_id', $paymentMethod))
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. amount is already stored on the row (see
        // Transactions\ApPaymentController), so this is a plain sum.
        $totalPayments = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');

        $payablePayments = $query->paginate(20)->withQueryString();

        return view('reports.payable-payments.index', [
            'payablePayments' => $payablePayments,
            'search' => $search,
            'supplierId' => $supplierId,
            'paymentMethod' => $paymentMethod,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'suppliers' => Supplier::orderBy('name')->get(),
            // Inactive methods stay filterable: old payments still point at them.
            'paymentMethods' => PaymentMethod::orderBy('name')->get(),
            'totalPayments' => $totalPayments,
            'totalAmount' => $totalAmount,
        ]);
    }
}
