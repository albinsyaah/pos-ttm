<?php

namespace App\Services;

use App\Models\ArPayment;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * What each customer still owes, with the open invoices behind it, as of today.
     *
     * Same rules as the Receivable Aging report and totalOutstanding(): each
     * credit invoice is netted against its own sales returns, then the customer's
     * payments are applied to the open invoices oldest first. Payments are made
     * for the customer, not for one invoice, so "paid" per invoice is only how
     * the customer's payments fall across them.
     *
     * Each customer comes back as:
     *   total     what is still owed (never below 0)
     *   invoices  open invoices, oldest first: id, invoice_number, sale_date,
     *             net (after returns), paid (applied to it), outstanding
     *
     * @param  int|array<int>|null  $customers  one id, several, or null for every customer that owes something
     * @param  int|null  $excludePaymentId  leave this payment out (when it is being edited)
     * @return Collection<int, array{total: float, invoices: array<int, array>}>  keyed by customer id
     */
    public function balances(int|array|null $customers = null, ?int $excludePaymentId = null): Collection
    {
        $customerIds = $customers === null ? null : array_values(array_unique((array) $customers));

        $sales = Sale::query()
            ->receivable()
            ->when($customerIds !== null, fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->select(['sales.id', 'sales.invoice_number', 'sales.sale_date', 'sales.customer_id', 'sales.total_amount'])
            ->withSum('salesReturns as returned_total', 'total_amount')
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $payments = ArPayment::query()
            ->when($customerIds !== null, fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->when($excludePaymentId, fn ($q) => $q->where('id', '!=', $excludePaymentId))
            ->selectRaw('customer_id, SUM(amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $result = collect();

        foreach ($sales as $customerId => $customerSales) {
            $left = round((float) ($payments[$customerId] ?? 0), 2);
            $invoices = [];

            foreach ($customerSales as $sale) {
                $net = round((float) $sale->total_amount - (float) ($sale->returned_total ?? 0), 2);

                if ($net <= 0.0001) {
                    continue; // fully returned: nothing owed on it
                }

                $applied = min($net, $left);
                $left = round($left - $applied, 2);
                $outstanding = round($net - $applied, 2);

                if ($outstanding <= 0.0001) {
                    continue; // settled by payments
                }

                $invoices[] = [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'sale_date' => Carbon::parse($sale->sale_date)->format('d M Y'),
                    'net' => $net,
                    'paid' => round($applied, 2),
                    'outstanding' => $outstanding,
                ];
            }

            if ($invoices !== []) {
                $result->put($customerId, [
                    'total' => round(array_sum(array_column($invoices, 'outstanding')), 2),
                    'invoices' => $invoices,
                ]);
            }
        }

        return $result;
    }

    /** customer_id => total owed, only for customers that owe something. */
    public function totalsByCustomer(): array
    {
        return $this->balances()->map(fn (array $row) => $row['total'])->all();
    }
}
