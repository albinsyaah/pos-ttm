<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InternalMutation;
use App\Models\InventoryLedger;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One-off clean-up for transactions that were saved BEFORE the pages started
 * moving stock themselves (see StockService).
 *
 * Such a transaction has no ledger rows tied to it (source_type / source_id
 * empty), so editing or deleting it later would make StockService believe it
 * never touched stock: an edited purchase would add its whole quantity a
 * second time, a deleted sale would give nothing back. This service brings old
 * data in line, in up to three independent steps:
 *
 *  - STEP_NORMALIZE: old data (e.g. the seeders) wrote "Completed", "Pending"
 *    and so on; the pages use 'received' / 'completed' / 'pending'. Rewrites the
 *    status values so old rows are treated like new ones.
 *  - STEP_LINK: ledger rows that already exist and belong to a transaction
 *    (same reference number) are tied to it. No stock changes; balances stay
 *    exactly as they are. A transaction is only linked when its rows add up to
 *    exactly what the transaction should have done, otherwise it is reported.
 *  - STEP_CREATE: transactions that should have moved stock but have no ledger
 *    rows at all get their rows written now, oldest first. This DOES change
 *    stock balances, so use it only when that stock has never been counted.
 *
 * Nothing is written unless $apply is true; without it the report shows what
 * would happen. Running it again is safe: transactions that already have
 * linked rows are left alone.
 */
class StockBackfillService
{
    public const STEP_NORMALIZE = 'normalize-statuses';

    public const STEP_LINK = 'link';

    public const STEP_CREATE = 'create-missing';

    /** Stock-increasing documents first within a day, so later sales find stock. */
    private const ORDER = [
        'purchase' => 1,
        'internal receipt' => 1,
        'warehouse transfer' => 2,
        'sales return' => 3,
        'deviation' => 4,
        'sale' => 5,
        'purchase return' => 6,
        'internal expenditure' => 6,
    ];

    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * @param  array<int, string>  $steps  Any of the STEP_* constants.
     * @return array<string, mixed>
     */
    public function run(array $steps, bool $apply): array
    {
        $report = ['applied' => $apply, 'steps' => $steps];

        if (in_array(self::STEP_NORMALIZE, $steps, true)) {
            $report['statuses'] = $this->normalizeStatuses($apply);
        }

        if (array_intersect([self::STEP_LINK, self::STEP_CREATE], $steps) !== []) {
            $report['ledger'] = $this->backfillLedger($steps, $apply);
        }

        return $report;
    }

    /**
     * Rewrite old status spellings to the ones the pages use.
     *
     * @return array{purchases: int, mutations: int, unknown: array<int, string>}
     */
    public function normalizeStatuses(bool $apply): array
    {
        $result = ['purchases' => 0, 'mutations' => 0, 'unknown' => []];

        $purchaseStatus = fn (string $s): ?string => match (strtolower(trim($s))) {
            'received', 'completed' => 'received',
            'pending' => 'pending',
            'cancelled', 'canceled' => 'cancelled',
            default => null,
        };
        $mutationStatus = fn (string $s): ?string => in_array(strtolower(trim($s)), ['pending', 'approved', 'completed', 'rejected'], true)
            ? strtolower(trim($s))
            : null;

        foreach ([[Purchase::class, $purchaseStatus, 'purchases'], [InternalMutation::class, $mutationStatus, 'mutations']] as [$class, $canonical, $key]) {
            $changes = [];

            foreach ($class::query()->select(['id', 'status'])->cursor() as $row) {
                $wanted = $canonical((string) $row->status);
                if ($wanted === null) {
                    $result['unknown'][] = class_basename($class)." #{$row->id}: \"{$row->status}\"";

                    continue;
                }
                if ($wanted !== $row->status) {
                    $changes[$wanted][] = $row->id;
                }
            }

            foreach ($changes as $wanted => $ids) {
                $result[$key] += count($ids);

                if ($apply) {
                    foreach (array_chunk($ids, 500) as $chunk) {
                        $class::query()->whereIn('id', $chunk)->update(['status' => $wanted]);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $steps
     * @return array<string, mixed>
     */
    private function backfillLedger(array $steps, bool $apply): array
    {
        $link = in_array(self::STEP_LINK, $steps, true);
        $create = in_array(self::STEP_CREATE, $steps, true);

        $entries = $this->collectEntries();
        $numberCount = array_count_values(array_map(fn ($e) => $e['number'], $entries));

        $unsourced = InventoryLedger::query()
            ->whereNull('source_type')
            ->get(['id', 'reference_number', 'qty', 'product_id', 'warehouse_id'])
            ->groupBy('reference_number');

        $sourced = [];
        foreach (InventoryLedger::query()->whereNotNull('source_type')->select('source_type', 'source_id')->distinct()->get() as $row) {
            $sourced[$row->source_type.'#'.$row->source_id] = true;
        }

        $counts = [];
        $problems = [];
        $toLink = [];
        $toCreate = [];

        $bump = function (string $kind, string $class) use (&$counts): void {
            $counts[$kind][$class] = ($counts[$kind][$class] ?? 0) + 1;
        };

        foreach ($entries as $entry) {
            $kind = $entry['kind'];
            $number = $entry['number'];
            $model = $entry['model'];

            if (isset($sourced[$model->getMorphClass().'#'.$model->getKey()])) {
                $bump($kind, 'already');

                continue;
            }

            $rows = $unsourced->get($number, collect());

            if ($entry['desired'] === []) {
                if ($rows->isNotEmpty()) {
                    $bump($kind, 'stray');
                    $problems[] = "{$kind} {$number}: has ledger rows but should not move stock (status or data); left alone.";
                } else {
                    $bump($kind, 'not_counted');
                }

                continue;
            }

            if ($numberCount[$number] > 1) {
                $bump($kind, 'ambiguous');
                $problems[] = "{$kind} {$number}: the same number is used by more than one document; left alone.";

                continue;
            }

            if ($rows->isEmpty()) {
                $bump($kind, 'missing');
                $toCreate[] = $entry;

                continue;
            }

            $found = [];
            foreach ($rows as $row) {
                $key = $row->product_id.':'.$row->warehouse_id;
                $found[$key] = ($found[$key] ?? 0) + (int) $row->qty;
            }
            $found = array_filter($found, fn ($qty) => $qty !== 0);

            if ($found == $entry['desired']) {
                $bump($kind, 'linkable');
                $toLink[] = ['entry' => $entry, 'ids' => $rows->pluck('id')->all()];
            } else {
                $bump($kind, 'mismatch');
                $problems[] = "{$kind} {$number}: ledger rows add up to ".json_encode($found).' but the document should have moved '.json_encode($entry['desired']).'; left alone.';
            }
        }

        if ($link) {
            foreach ($toLink as $item) {
                $kind = $item['entry']['kind'];
                $model = $item['entry']['model'];

                if ($apply) {
                    foreach (array_chunk($item['ids'], 500) as $chunk) {
                        InventoryLedger::query()->whereIn('id', $chunk)->update([
                            'source_type' => $model->getMorphClass(),
                            'source_id' => $model->getKey(),
                        ]);
                    }
                }

                $counts[$kind][$apply ? 'linked' : 'would_link'] = ($counts[$kind][$apply ? 'linked' : 'would_link'] ?? 0) + 1;
                $counts[$kind]['linkable']--;
            }
        }

        if ($create) {
            usort($toCreate, fn ($a, $b) => [$a['date'], self::ORDER[$a['kind']] ?? 9, $a['model']->getKey()]
                <=> [$b['date'], self::ORDER[$b['kind']] ?? 9, $b['model']->getKey()]);

            foreach ($toCreate as $entry) {
                $kind = $entry['kind'];

                if (! $apply) {
                    $counts[$kind]['would_create'] = ($counts[$kind]['would_create'] ?? 0) + 1;
                    $counts[$kind]['missing']--;

                    continue;
                }

                try {
                    DB::transaction(fn () => ($entry['apply'])($this->stock));

                    $counts[$kind]['created'] = ($counts[$kind]['created'] ?? 0) + 1;
                } catch (InsufficientStockException $e) {
                    $counts[$kind]['failed'] = ($counts[$kind]['failed'] ?? 0) + 1;
                    $problems[] = "{$kind} {$entry['number']}: not written, ".implode(' ', $e->shortages);
                } catch (InvalidArgumentException $e) {
                    $counts[$kind]['failed'] = ($counts[$kind]['failed'] ?? 0) + 1;
                    $problems[] = "{$kind} {$entry['number']}: not written, {$e->getMessage()}";
                }

                $counts[$kind]['missing']--;
            }
        }

        // Drop zero counters left behind by the moves above.
        foreach ($counts as $kind => $classes) {
            $counts[$kind] = array_filter($classes, fn ($n) => $n > 0);
        }

        return ['counts' => $counts, 'problems' => $problems];
    }

    /**
     * Every document that can move stock, with what it should have done.
     *
     * @return array<int, array{kind: string, model: Model, number: string, date: string, desired: array<string, int>, apply: Closure}>
     */
    private function collectEntries(): array
    {
        $entries = [];

        foreach (Purchase::with('purchaseDetails')->lazyById(200) as $purchase) {
            $counts = $this->purchaseCounts($purchase);
            $warehouse = (int) $purchase->warehouse_id;
            $items = $this->positiveItems($purchase->purchaseDetails);

            $entries[] = $this->entry(
                'purchase', $purchase, (string) $purchase->invoice_number, $purchase->purchase_date,
                $counts ? $this->net([[$warehouse, 1, $items]]) : [],
                fn (StockService $s) => $s->sync($purchase, $warehouse, $items, 'in', $purchase->invoice_number, $purchase->purchase_date)
            );
        }

        foreach (Sale::with('saleDetails')->lazyById(200) as $sale) {
            // A cashier-terminal sale has no warehouse of its own: its stock is always written
            // when it is saved (from whichever warehouses had it), so there is nothing to rebuild.
            if ($sale->warehouse_id === null) {
                continue;
            }

            $warehouse = (int) $sale->warehouse_id;
            $items = $this->positiveItems($sale->saleDetails);

            $entries[] = $this->entry(
                'sale', $sale, (string) $sale->invoice_number, $sale->sale_date,
                $this->net([[$warehouse, -1, $items]]),
                fn (StockService $s) => $s->sync($sale, $warehouse, $items, 'out', $sale->invoice_number, $sale->sale_date)
            );
        }

        foreach (PurchaseReturn::with(['purchase', 'purchaseReturnDetails'])->lazyById(200) as $return) {
            $counts = $return->purchase !== null && $this->purchaseCounts($return->purchase);
            $warehouse = (int) ($return->purchase->warehouse_id ?? 0);
            $items = $this->positiveItems($return->purchaseReturnDetails);

            $entries[] = $this->entry(
                'purchase return', $return, (string) $return->return_number, $return->return_date,
                $counts ? $this->net([[$warehouse, -1, $items]]) : [],
                fn (StockService $s) => $s->sync($return, $warehouse, $items, 'out', $return->return_number, $return->return_date)
            );
        }

        foreach (SalesReturn::with(['sale', 'salesReturnDetails'])->lazyById(200) as $return) {
            // Same for returns of such a sale: they went back to the warehouses the goods came from.
            if ($return->sale !== null && $return->sale->warehouse_id === null) {
                continue;
            }

            $warehouse = (int) ($return->sale->warehouse_id ?? 0);
            $items = $this->positiveItems($return->salesReturnDetails);

            $entries[] = $this->entry(
                'sales return', $return, (string) $return->return_number, $return->return_date,
                $return->sale !== null ? $this->net([[$warehouse, 1, $items]]) : [],
                fn (StockService $s) => $s->sync($return, $warehouse, $items, 'in', $return->return_number, $return->return_date)
            );
        }

        foreach (InternalMutation::with('internalMutationDetails')->lazyById(200) as $mutation) {
            $counts = strtolower(trim((string) $mutation->status)) === 'completed';
            $from = $mutation->from_warehouse_id !== null ? (int) $mutation->from_warehouse_id : null;
            $to = $mutation->to_warehouse_id !== null ? (int) $mutation->to_warehouse_id : null;
            $number = (string) $mutation->mutation_number;
            $date = $mutation->mutation_date;

            switch ($mutation->type) {
                case 'Transfer Antar Gudang':
                    $items = $this->positiveItems($mutation->internalMutationDetails);
                    $valid = $counts && $from !== null && $to !== null && $from !== $to;
                    $entries[] = $this->entry(
                        'warehouse transfer', $mutation, $number, $date,
                        $valid ? $this->net([[$from, -1, $items], [$to, 1, $items]]) : [],
                        fn (StockService $s) => $s->syncTransfer($mutation, $from, $to, $items, $number, $date)
                    );
                    break;

                case 'Internal Receipt':
                    $items = $this->positiveItems($mutation->internalMutationDetails);
                    $entries[] = $this->entry(
                        'internal receipt', $mutation, $number, $date,
                        $counts && $to !== null ? $this->net([[$to, 1, $items]]) : [],
                        fn (StockService $s) => $s->sync($mutation, $to, $items, 'in', $number, $date)
                    );
                    break;

                case 'Internal Expenditure':
                    $items = $this->positiveItems($mutation->internalMutationDetails);
                    $entries[] = $this->entry(
                        'internal expenditure', $mutation, $number, $date,
                        $counts && $from !== null ? $this->net([[$from, -1, $items]]) : [],
                        fn (StockService $s) => $s->sync($mutation, $from, $items, 'out', $number, $date)
                    );
                    break;

                case 'Deviation':
                    $items = $this->signedItems($mutation->internalMutationDetails);
                    $entries[] = $this->entry(
                        'deviation', $mutation, $number, $date,
                        $counts && $from !== null ? $this->net([[$from, 1, $items]]) : [],
                        fn (StockService $s) => $s->syncAdjustment($mutation, $from, $items, $number, $date)
                    );
                    break;

                // "Item Request" and any other type never move stock.
            }
        }

        return $entries;
    }

    private function entry(string $kind, Model $model, string $number, mixed $date, array $desired, Closure $apply): array
    {
        return [
            'kind' => $kind,
            'model' => $model,
            'number' => $number,
            'date' => (string) $date,
            'desired' => $desired,
            'apply' => $apply,
        ];
    }

    /** Old data says "Completed", the pages say "received". */
    private function purchaseCounts(Purchase $purchase): bool
    {
        return in_array(strtolower(trim((string) $purchase->status)), ['received', 'completed'], true);
    }

    /**
     * @param  iterable<Model>  $details
     * @return array<int, array{product_id: int, qty: int}>
     */
    private function positiveItems(iterable $details): array
    {
        $items = [];
        foreach ($details as $detail) {
            if ((int) $detail->qty > 0) {
                $items[] = ['product_id' => (int) $detail->product_id, 'qty' => (int) $detail->qty];
            }
        }

        return $items;
    }

    /**
     * @param  iterable<Model>  $details
     * @return array<int, array{product_id: int, qty: int}>
     */
    private function signedItems(iterable $details): array
    {
        $items = [];
        foreach ($details as $detail) {
            if ((int) $detail->qty !== 0) {
                $items[] = ['product_id' => (int) $detail->product_id, 'qty' => (int) $detail->qty];
            }
        }

        return $items;
    }

    /**
     * Net qty per "product_id:warehouse_id" for one or more (warehouse, sign, items) groups.
     *
     * @param  array<int, array{0: int, 1: int, 2: array<int, array{product_id: int, qty: int}>}>  $groups
     * @return array<string, int>
     */
    private function net(array $groups): array
    {
        $net = [];
        foreach ($groups as [$warehouse, $sign, $items]) {
            foreach ($items as $item) {
                $key = $item['product_id'].':'.$warehouse;
                $net[$key] = ($net[$key] ?? 0) + $sign * $item['qty'];
            }
        }

        return array_filter($net, fn ($qty) => $qty !== 0);
    }
}
