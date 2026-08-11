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
 * Read-only Receivable Aging report ("Laporan Umur Piutang"): for every
 * customer, how much of their outstanding receivable falls into each age
 * bucket (Current 0-30 / 31-60 / 61-90 / 91+ days), as of a given date.
 *
 * There's no invoice-to-payment allocation table in this schema (AR
 * payments are recorded against a customer, not a specific sale — see
 * ArPayment/ArPaymentReportController), so outstanding balances are
 * derived rather than stored:
 *   1. Each Sale (invoice) is netted against any SalesReturn tied to it.
 *   2. The customer's AR payments are applied FIFO against their open
 *      invoices, oldest first — the same convention used by
 *      ArCardReportController's running balance, just applied per-invoice
 *      instead of per-transaction.
 *   3. Whatever remains open on each invoice is aged from its invoice
 *      date (there's no due_date column on `sales`) relative to the
 *      as-of date and dropped into a bucket.
 *
 * Only customers with a remaining balance as of the given date are shown.
 * Permission check lives on the route itself, matching the pattern used
 * by the other Reports\* controllers.
 */
class ArAgingReportController extends Controller
{
    /**
     * Bucket key => [min days old, max days old] (inclusive).
     */
    public const BUCKETS = [
        'current' => [0, 30],
        'days_31_60' => [31, 60],
        'days_61_90' => [61, 90],
        'over_90' => [91, PHP_INT_MAX],
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $customerId = $request->query('customer_id');
        $asOf = $request->query('as_of') ?: now()->toDateString();

        $customers = Customer::orderBy('name')->get();
        $asOfDate = Carbon::parse($asOf);

        $salesByCustomer = Sale::where('sale_date', '<=', $asOf)
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $returnsBySale = SalesReturn::where('return_date', '<=', $asOf)
            ->get()
            ->groupBy('sale_id');

        $paymentTotalsByCustomer = ArPayment::where('payment_date', '<=', $asOf)
            ->selectRaw('customer_id, sum(amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $rows = collect();

        foreach ($customers as $customer) {
            if ($customerId && (string) $customerId !== (string) $customer->id) {
                continue;
            }
            if ($search !== ''
                && !str_contains(strtolower($customer->name), strtolower($search))
                && !str_contains(strtolower((string) $customer->code), strtolower($search))) {
                continue;
            }

            $sales = $salesByCustomer->get($customer->id, collect());
            if ($sales->isEmpty()) {
                continue;
            }

            // Net each invoice against its own returns, then drop fully
            // returned invoices.
            $openInvoices = $sales
                ->map(fn ($sale) => [
                    'date' => $sale->sale_date,
                    'remaining' => round(
                        (float) $sale->total_amount - (float) $returnsBySale->get($sale->id, collect())->sum('total_amount'),
                        2
                    ),
                ])
                ->filter(fn ($invoice) => $invoice['remaining'] > 0.0001)
                ->values();

            if ($openInvoices->isEmpty()) {
                continue;
            }

            // Apply payments FIFO: oldest invoice gets paid down first.
            $remainingPayment = (float) ($paymentTotalsByCustomer[$customer->id] ?? 0);

            $openInvoices = $openInvoices->map(function ($invoice) use (&$remainingPayment) {
                if ($remainingPayment > 0) {
                    $applied = min($invoice['remaining'], $remainingPayment);
                    $invoice['remaining'] -= $applied;
                    $remainingPayment -= $applied;
                }

                return $invoice;
            })->filter(fn ($invoice) => $invoice['remaining'] > 0.0001)->values();

            if ($openInvoices->isEmpty()) {
                continue;
            }

            $bucketTotals = array_fill_keys(array_keys(self::BUCKETS), 0.0);

            foreach ($openInvoices as $invoice) {
                $ageInDays = Carbon::parse($invoice['date'])->diffInDays($asOfDate);

                foreach (self::BUCKETS as $key => [$min, $max]) {
                    if ($ageInDays >= $min && $ageInDays <= $max) {
                        $bucketTotals[$key] += $invoice['remaining'];
                        break;
                    }
                }
            }

            $total = array_sum($bucketTotals);
            if ($total <= 0.0001) {
                continue;
            }

            $rows->push([
                'customer' => $customer,
                'current' => $bucketTotals['current'],
                'days_31_60' => $bucketTotals['days_31_60'],
                'days_61_90' => $bucketTotals['days_61_90'],
                'over_90' => $bucketTotals['over_90'],
                'total' => $total,
            ]);
        }

        $rows = $rows->sortByDesc('total')->values();

        $grandTotals = [
            'current' => $rows->sum('current'),
            'days_31_60' => $rows->sum('days_31_60'),
            'days_61_90' => $rows->sum('days_61_90'),
            'over_90' => $rows->sum('over_90'),
            'total' => $rows->sum('total'),
        ];

        return view('reports.receivable-aging.index', [
            'rows' => $rows,
            'customers' => $customers,
            'customerId' => $customerId,
            'search' => $search,
            'asOf' => $asOf,
            'grandTotals' => $grandTotals,
        ]);
    }
}
