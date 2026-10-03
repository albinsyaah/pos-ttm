<?php

namespace App\Services;

use App\Models\ArPayment;
use App\Models\Sale;

/**
 * What customers still owe, with the same rules as the Receivable Aging report:
 * each credit invoice is netted against its own sales returns, then the
 * customer's payments are applied to the open invoices (oldest first). A customer
 * never owes less than zero, so extra payments do not offset another customer.
 */
class ReceivableService
{
    /** Total still owed by all customers on $asOf (today when null). */
    public function totalOutstanding(?string $asOf = null): float
    {
        $asOf ??= now()->toDateString();

        $sales = Sale::query()
            ->receivable()
            ->whereDate('sale_date', '<=', $asOf)
            ->select(['sales.id', 'sales.customer_id', 'sales.total_amount'])
            ->withSum(['salesReturns as returned_total' => fn ($q) => $q->whereDate('return_date', '<=', $asOf)], 'total_amount')
            ->get();

        $payments = ArPayment::query()
            ->whereDate('payment_date', '<=', $asOf)
            ->selectRaw('customer_id, SUM(amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $total = 0.0;

        foreach ($sales->groupBy('customer_id') as $customerId => $customerSales) {
            $open = $customerSales->sum(function (Sale $sale) {
                $remaining = round((float) $sale->total_amount - (float) ($sale->returned_total ?? 0), 2);

                return $remaining > 0.0001 ? $remaining : 0.0;
            });

            $total += max(0.0, round($open - (float) ($payments[$customerId] ?? 0), 2));
        }

        return round($total, 2);
    }
}
