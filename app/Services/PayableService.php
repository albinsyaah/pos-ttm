<?php

namespace App\Services;

use App\Models\ApPayment;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the business still owes each supplier, per purchase invoice.
 *
 * An invoice is payable once it is received. Its balance is:
 *
 *     total - purchase returns - payments made against that invoice
 *
 * Payments recorded before payments were tied to an invoice have no
 * purchase_id. They are treated as supplier-level credit and applied to that
 * supplier's oldest invoices first (by due date), so an old, fully paid
 * invoice never raises a reminder just because the payment was not linked.
 */
class PayableService
{
    /**
     * Every payable invoice for the given supplier(s), oldest due date first,
     * each as an array: purchase, net, paid, outstanding, due_date, days_left.
     * Settled invoices are included (outstanding 0); filter with openInvoices().
     *
     * @param  int|array<int>|null  $suppliers  one id, several, or null for all
     * @param  int|null  $excludePaymentId  ignore this payment (when editing it)
     * @return Collection<int, array>  keyed by purchase id
     */
    public function invoices(int|array|null $suppliers = null, ?int $excludePaymentId = null): Collection
    {
        $supplierIds = $suppliers === null ? null : array_values(array_unique((array) $suppliers));

        $purchases = Purchase::query()
            ->with('supplier')
            ->whereIn('status', Purchase::PAYABLE_STATUSES)
            ->when($supplierIds !== null, fn ($q) => $q->whereIn('supplier_id', $supplierIds))
            ->withSum('purchaseReturns as returns_total', 'total_amount')
            ->withSum([
                'payments as paid_total' => fn ($q) => $q->when(
                    $excludePaymentId,
                    fn ($q) => $q->where('id', '!=', $excludePaymentId)
                ),
            ], 'amount')
            ->get()
            ->sortBy(fn (Purchase $p) => [$this->dueDate($p)->toDateString(), $p->id])
            ->values();

        // Supplier-level credit from payments that were never tied to an invoice.
        $credit = ApPayment::query()
            ->whereNull('purchase_id')
            ->when($supplierIds !== null, fn ($q) => $q->whereIn('supplier_id', $supplierIds))
            ->when($excludePaymentId, fn ($q) => $q->where('id', '!=', $excludePaymentId))
            ->selectRaw('supplier_id, SUM(amount) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $today = Carbon::today();
        $result = collect();

        foreach ($purchases as $purchase) {
            $net = round((float) $purchase->total_amount - (float) ($purchase->returns_total ?? 0), 2);
            $paid = round((float) ($purchase->paid_total ?? 0), 2);

            $left = max(0, round($net - $paid, 2));
            $available = $credit[$purchase->supplier_id] ?? 0.0;

            if ($available > 0 && $left > 0) {
                $applied = min($available, $left);
                $credit[$purchase->supplier_id] = $available - $applied;
                $paid = round($paid + $applied, 2);
            }

            $due = $this->dueDate($purchase);

            $result->put($purchase->id, [
                'purchase' => $purchase,
                'net' => $net,
                'paid' => $paid,
                'outstanding' => max(0, round($net - $paid, 2)),
                'due_date' => $due,
                'days_left' => (int) round($today->diffInDays($due, false)),
            ]);
        }

        return $result;
    }

    /** Only the invoices that still owe money. */
    public function openInvoices(int|array|null $suppliers = null, ?int $excludePaymentId = null): Collection
    {
        return $this->invoices($suppliers, $excludePaymentId)
            ->filter(fn (array $row) => $row['outstanding'] > 0);
    }

    /** Everything still owed to suppliers: the sum of the open invoices' balances. */
    public function totalOutstanding(): float
    {
        return round((float) $this->openInvoices()->sum('outstanding'), 2);
    }

    /**
     * supplier_id => what is still owed to that supplier (open invoices only).
     *
     * @param  int|null  $excludePaymentId  leave this payment out (when it is being edited)
     * @return array<int, float>
     */
    public function totalsBySupplier(?int $excludePaymentId = null): array
    {
        return $this->openInvoices(null, $excludePaymentId)
            ->groupBy(fn (array $row) => $row['purchase']->supplier_id)
            ->map(fn ($rows) => round((float) $rows->sum('outstanding'), 2))
            ->all();
    }

    /** Balance of one invoice, or 0 if it is not payable (pending or cancelled). */
    public function outstandingFor(int $purchaseId, ?int $excludePaymentId = null): float
    {
        $supplierId = Purchase::whereKey($purchaseId)->value('supplier_id');

        if ($supplierId === null) {
            return 0.0;
        }

        return (float) ($this->invoices((int) $supplierId, $excludePaymentId)->get($purchaseId)['outstanding'] ?? 0);
    }

    /**
     * Invoices that need attention: overdue, or due within the next
     * REMINDER_DAYS days (the reminder starts on day 23 of the 30). Each row
     * gains a 'state' of overdue, today or soon. Paying the invoice in full
     * removes it from this list, which is what stops the reminder.
     */
    public function reminders(): Collection
    {
        return $this->openInvoices()
            ->filter(fn (array $row) => $row['days_left'] <= Purchase::REMINDER_DAYS)
            ->map(function (array $row) {
                $row['state'] = $row['days_left'] < 0 ? 'overdue' : ($row['days_left'] === 0 ? 'today' : 'soon');

                return $row;
            })
            ->sortBy(fn (array $row) => [$row['due_date']->toDateString(), $row['purchase']->id])
            ->values();
    }

    private function dueDate(Purchase $purchase): Carbon
    {
        return ($purchase->due_date ?? Carbon::parse($purchase->purchase_date)->addDays(Purchase::PAYMENT_TERM_DAYS))
            ->copy()
            ->startOfDay();
    }
}
