<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ArPayment;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only Receivable Card ("Kartu Piutang") report: a running ledger for
 * a single customer, combining every transaction that moves their
 * receivable balance —
 *   - Sale invoices (debit, from `sales`)
 *   - Sales returns (credit, from `sales_returns`, scoped to that
 *     customer's sales via the `sale_id` relation — the table itself has
 *     no customer_id)
 *   - AR payments (credit, from `ar_payments`)
 * — sorted chronologically with a running balance column.
 *
 * A customer must be selected; without one the page shows an empty state
 * rather than an unfiltered (meaningless, cross-customer) ledger. Date
 * filters narrow which rows are shown, but the opening balance is still
 * computed from everything before date_from so the running balance stays
 * correct.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by
 * ArPaymentReportController / the other Reports\* controllers.
 */
class ArCardReportController extends Controller
{
    public function index(Request $request)
    {
        $customerId = $request->query('customer_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $customers = Customer::orderBy('name')->get();
        $customer = $customerId ? $customers->firstWhere('id', (int) $customerId) : null;

        $rows = collect();
        $openingBalance = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        if ($customer) {
            $sales = Sale::where('customer_id', $customer->id)
                ->get()
                ->map(fn ($sale) => [
                    'date' => $sale->sale_date,
                    'id' => 'sale-' . $sale->id,
                    'sort_id' => $sale->id,
                    'document_number' => $sale->invoice_number,
                    'type' => __('app.reports.receivable_card.type_sale'),
                    'debit' => (float) $sale->total_amount,
                    'credit' => 0.0,
                ]);

            $salesReturns = SalesReturn::whereHas('sale', fn ($q) => $q->where('customer_id', $customer->id))
                ->with('sale')
                ->get()
                ->map(fn ($return) => [
                    'date' => $return->return_date,
                    'id' => 'return-' . $return->id,
                    'sort_id' => $return->id,
                    'document_number' => $return->return_number . ($return->sale?->invoice_number ? ' (' . $return->sale->invoice_number . ')' : ''),
                    'type' => __('app.reports.receivable_card.type_sales_return'),
                    'debit' => 0.0,
                    'credit' => (float) $return->total_amount,
                ]);

            $payments = ArPayment::where('customer_id', $customer->id)
                ->get()
                ->map(fn ($payment) => [
                    'date' => $payment->payment_date,
                    'id' => 'payment-' . $payment->id,
                    'sort_id' => $payment->id,
                    'document_number' => $payment->payment_number,
                    'type' => __('app.reports.receivable_card.type_payment'),
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                ]);

            // Chronological order across all three sources, oldest first,
            // so the running balance accumulates correctly.
            $all = $sales->concat($salesReturns)->concat($payments)
                ->sort(function ($a, $b) {
                    $dateCompare = Carbon::parse($a['date'])->timestamp <=> Carbon::parse($b['date'])->timestamp;

                    return $dateCompare !== 0 ? $dateCompare : ($a['sort_id'] <=> $b['sort_id']);
                })
                ->values();

            $balance = 0.0;
            $all = $all->map(function ($row) use (&$balance) {
                $balance += $row['debit'] - $row['credit'];
                $row['balance'] = $balance;

                return $row;
            });

            if ($dateFrom) {
                $fromCutoff = Carbon::parse($dateFrom);
                $beforeFrom = $all->last(fn ($row) => Carbon::parse($row['date'])->lt($fromCutoff));
                $openingBalance = $beforeFrom['balance'] ?? 0.0;
            }

            $rows = $all->filter(function ($row) use ($dateFrom, $dateTo) {
                $date = Carbon::parse($row['date']);

                if ($dateFrom && $date->lt(Carbon::parse($dateFrom))) {
                    return false;
                }
                if ($dateTo && $date->gt(Carbon::parse($dateTo))) {
                    return false;
                }

                return true;
            })->values();

            $totalDebit = $rows->sum('debit');
            $totalCredit = $rows->sum('credit');
        }

        $endingBalance = $openingBalance + $totalDebit - $totalCredit;

        return view('reports.receivable-card.index', [
            'customers' => $customers,
            'customer' => $customer,
            'customerId' => $customerId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'openingBalance' => $openingBalance,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'endingBalance' => $endingBalance,
        ]);
    }
}
