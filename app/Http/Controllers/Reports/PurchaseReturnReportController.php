<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Read-only Purchase Return report: lets any user with reports.view filter
 * purchase returns by date range, supplier (via the related purchase) and
 * free text, and see per-return + grand totals. No create/update/delete —
 * this is reporting only, unlike Transactions\PurchaseReturnController
 * which owns the CRUD.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by InquiryController /
 * PurchaseOrderReportController / PurchaseReportController.
 */
class PurchaseReturnReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $supplierId = $request->query('supplier_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = PurchaseReturn::query()
            ->with(['purchase.supplier', 'purchaseReturnDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('purchase', function ($purchaseQuery) use ($search) {
                            $purchaseQuery->where('invoice_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($supplierId, function ($q) use ($supplierId) {
                $q->whereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('supplier_id', $supplierId));
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('return_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('return_date', '<=', $dateTo))
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. total_amount is already stored on the row (see
        // Transactions\PurchaseReturnController), so this is a plain sum.
        $totalReturns = (clone $query)->count();
        $totalAmount = (clone $query)->sum('total_amount');

        $purchaseReturns = $query->paginate(20)->withQueryString();

        return view('reports.purchase-returns.index', [
            'purchaseReturns' => $purchaseReturns,
            'search' => $search,
            'supplierId' => $supplierId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'suppliers' => Supplier::orderBy('name')->get(),
            'totalReturns' => $totalReturns,
            'totalAmount' => $totalAmount,
        ]);
    }
}
