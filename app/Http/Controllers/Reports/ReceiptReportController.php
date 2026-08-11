<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

/**
 * Read-only Receipt ("Penerimaan") report: lets any user with
 * reports.view filter cash-in transactions by date range, account and
 * free text, and see per-transaction + grand totals.
 *
 * Built on the same `cash_flows` table/model as
 * Transactions\CashManagementController, scoped to type = 'in' — the
 * mirror image of ExpenditureReportController, which covers type = 'out'.
 * No create/update/delete — this is reporting only, matching the pattern
 * used by the other Reports\* controllers.
 */
class ReceiptReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $accountId = $request->query('account_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = CashFlow::query()
            ->where('type', 'in')
            ->with('account')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhereHas('account', function ($accountQuery) use ($search) {
                            $accountQuery->where('account_code', 'like', "%{$search}%")
                                ->orWhere('account_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($dateFrom, fn ($q) => $q->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('transaction_date', '<=', $dateTo))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        // Grand totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating. amount is already stored on the row (see
        // Transactions\CashManagementController), so this is a plain sum.
        $totalTransactions = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');

        $receipts = $query->paginate(20)->withQueryString();

        return view('reports.receipt.index', [
            'receipts' => $receipts,
            'search' => $search,
            'accountId' => $accountId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'accounts' => ChartOfAccount::orderBy('account_code')->get(),
            'totalTransactions' => $totalTransactions,
            'totalAmount' => $totalAmount,
        ]);
    }
}
