<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single place that moves stock in `inventory_ledgers`.
 *
 * Conventions (same as Database\Seeders\Concerns\ManagesInventoryLedger and
 * the Stock Card report):
 *  - `qty` is signed: positive for IN / TRANSFER_IN, negative for OUT / TRANSFER_OUT.
 *  - `balance` is the running balance for one product + warehouse; the latest
 *    row (highest id) holds the current stock, exactly like the reports read it.
 *
 * Rules:
 *  - The ledger is append-only. Corrections are written as reversal rows
 *    (reference "REV-<original>") instead of updating or deleting rows,
 *    because deleting a middle row would break the balance of every later row.
 *  - Every movement runs in a DB transaction. Products are locked in ascending
 *    id order, so concurrent sales of the same product are serialised and two
 *    transactions that touch the same products cannot deadlock each other.
 *  - Stock can never go below zero: a movement that would do so throws
 *    InsufficientStockException (a ValidationException) and writes nothing.
 *  - Quantities are whole numbers. The same product listed more than once in a
 *    call (e.g. one paid line and one free line) is added up before checking.
 *
 * To edit a transaction: reverse($source) and then apply the new movement,
 * both inside the caller's DB::transaction().
 */
class StockService
{
    private const IN_TYPES = [InventoryLedger::TYPE_IN, InventoryLedger::TYPE_TRANSFER_IN];

    private const OUT_TYPES = [InventoryLedger::TYPE_OUT, InventoryLedger::TYPE_TRANSFER_OUT];

    private const REVERSAL_PREFIX = 'REV-';

    /**
     * Current stock of a product in a warehouse (0 if it has no ledger rows).
     */
    public function available(int $productId, int $warehouseId): int
    {
        return (int) (InventoryLedger::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('id')
            ->value('balance') ?? 0);
    }

    /**
     * Add stock for one product.
     */
    public function increase(
        int $productId,
        int $warehouseId,
        int $qty,
        string $referenceNumber,
        ?Model $source = null,
        CarbonInterface|string|null $date = null,
        string $type = InventoryLedger::TYPE_IN
    ): InventoryLedger {
        return $this->increaseMany(
            $warehouseId,
            [['product_id' => $productId, 'qty' => $qty]],
            $referenceNumber,
            $source,
            $date,
            $type
        )->first();
    }

    /**
     * Take stock out for one product.
     *
     * @throws InsufficientStockException
     */
    public function decrease(
        int $productId,
        int $warehouseId,
        int $qty,
        string $referenceNumber,
        ?Model $source = null,
        CarbonInterface|string|null $date = null,
        string $type = InventoryLedger::TYPE_OUT
    ): InventoryLedger {
        return $this->decreaseMany(
            $warehouseId,
            [['product_id' => $productId, 'qty' => $qty]],
            $referenceNumber,
            $source,
            $date,
            $type
        )->first();
    }

    /**
     * Add stock for several lines at once (one ledger row per distinct product).
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items
     * @return Collection<int, InventoryLedger>
     */
    public function increaseMany(
        int $warehouseId,
        array $items,
        string $referenceNumber,
        ?Model $source = null,
        CarbonInterface|string|null $date = null,
        string $type = InventoryLedger::TYPE_IN
    ): Collection {
        $this->assertType($type, self::IN_TYPES);

        return $this->move($warehouseId, $this->aggregate($items), $type, $referenceNumber, $source, $date);
    }

    /**
     * Take stock out for several lines at once. Nothing is written if any
     * product is short; the exception lists every short product.
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items
     * @return Collection<int, InventoryLedger>
     *
     * @throws InsufficientStockException
     */
    public function decreaseMany(
        int $warehouseId,
        array $items,
        string $referenceNumber,
        ?Model $source = null,
        CarbonInterface|string|null $date = null,
        string $type = InventoryLedger::TYPE_OUT
    ): Collection {
        $this->assertType($type, self::OUT_TYPES);

        return $this->move($warehouseId, $this->aggregate($items), $type, $referenceNumber, $source, $date, negative: true);
    }

    /**
     * Undo everything a transaction did to stock by writing reversal rows.
     *
     * The net effect per product + warehouse of all rows tied to $source is
     * cancelled, so calling it twice is harmless (the second call finds a net
     * of zero and writes nothing). Reversing an IN fails with
     * InsufficientStockException if that stock has already been used.
     *
     * @return Collection<int, InventoryLedger>  The reversal rows (empty if nothing to undo).
     *
     * @throws InsufficientStockException
     */
    public function reverse(Model $source, CarbonInterface|string|null $date = null): Collection
    {
        return DB::transaction(function () use ($source, $date) {
            $entries = InventoryLedger::where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->orderBy('id')
                ->get();

            $plan = [];
            foreach ($entries->groupBy(fn (InventoryLedger $e) => $e->product_id.':'.$e->warehouse_id) as $group) {
                $net = (int) $group->sum('qty');
                if ($net === 0) {
                    continue;
                }

                /** @var InventoryLedger $first */
                $first = $group->first();
                $isTransfer = str_starts_with($first->type, 'TRANSFER_');

                $plan[] = [
                    'product_id' => (int) $first->product_id,
                    'warehouse_id' => (int) $first->warehouse_id,
                    'qty' => -$net,
                    'type' => $net > 0
                        ? ($isTransfer ? InventoryLedger::TYPE_TRANSFER_OUT : InventoryLedger::TYPE_OUT)
                        : ($isTransfer ? InventoryLedger::TYPE_TRANSFER_IN : InventoryLedger::TYPE_IN),
                    'reference' => self::REVERSAL_PREFIX.$this->stripReversalPrefix($first->reference_number),
                ];
            }

            if ($plan === []) {
                return collect();
            }

            usort($plan, fn ($a, $b) => [$a['product_id'], $a['warehouse_id']] <=> [$b['product_id'], $b['warehouse_id']]);

            $this->lockProducts(array_column($plan, 'product_id'));

            $shortages = [];
            foreach ($plan as $line) {
                if ($line['qty'] < 0) {
                    $current = $this->available($line['product_id'], $line['warehouse_id']);
                    if ($current < -$line['qty']) {
                        $shortages[] = $this->shortageMessage('insufficient_reverse', $line['product_id'], $line['warehouse_id'], $current, -$line['qty']);
                    }
                }
            }
            if ($shortages !== []) {
                throw InsufficientStockException::forShortages($shortages);
            }

            $moment = $this->resolveDate($date);
            $created = collect();
            foreach ($plan as $line) {
                $created->push($this->write(
                    $line['product_id'],
                    $line['warehouse_id'],
                    $line['type'],
                    $line['qty'],
                    $line['reference'],
                    $moment,
                    $source
                ));
            }

            return $created;
        });
    }

    /**
     * @param  array<int, int>  $lines  product_id => qty (already added up per product)
     * @return Collection<int, InventoryLedger>
     */
    private function move(
        int $warehouseId,
        array $lines,
        string $type,
        string $referenceNumber,
        ?Model $source,
        CarbonInterface|string|null $date,
        bool $negative = false
    ): Collection {
        if ($lines === []) {
            return collect();
        }

        ksort($lines);

        return DB::transaction(function () use ($warehouseId, $lines, $type, $referenceNumber, $source, $date, $negative) {
            $this->lockProducts(array_keys($lines));

            if ($negative) {
                $shortages = [];
                foreach ($lines as $productId => $qty) {
                    $current = $this->available($productId, $warehouseId);
                    if ($current < $qty) {
                        $shortages[] = $this->shortageMessage('insufficient', $productId, $warehouseId, $current, $qty);
                    }
                }
                if ($shortages !== []) {
                    throw InsufficientStockException::forShortages($shortages);
                }
            }

            $moment = $this->resolveDate($date);
            $created = collect();
            foreach ($lines as $productId => $qty) {
                $created->push($this->write(
                    $productId,
                    $warehouseId,
                    $type,
                    $negative ? -$qty : $qty,
                    $referenceNumber,
                    $moment,
                    $source
                ));
            }

            return $created;
        });
    }

    /**
     * Append one ledger row. Must be called inside a transaction with the
     * product already locked.
     */
    private function write(
        int $productId,
        int $warehouseId,
        string $type,
        int $signedQty,
        string $referenceNumber,
        Carbon $date,
        ?Model $source
    ): InventoryLedger {
        return InventoryLedger::create([
            'transaction_date' => $date,
            'reference_number' => $referenceNumber,
            'type' => $type,
            'qty' => $signedQty,
            'balance' => $this->available($productId, $warehouseId) + $signedQty,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);
    }

    /**
     * Lock the product rows in ascending id order (serialises concurrent
     * movements of the same product and keeps lock order consistent).
     *
     * @param  array<int, int>  $productIds
     */
    private function lockProducts(array $productIds): void
    {
        $ids = array_values(array_unique($productIds));
        sort($ids);

        foreach ($ids as $id) {
            if (Product::whereKey($id)->lockForUpdate()->value('id') === null) {
                throw new InvalidArgumentException(__('stock.product_not_found', ['id' => $id]));
            }
        }
    }

    /**
     * Validate and add up lines per product. The same product may appear more
     * than once (e.g. paid + free); its quantities are summed.
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items
     * @return array<int, int>  product_id => total qty
     */
    private function aggregate(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = $item['qty'] ?? null;

            if ($productId <= 0) {
                throw new InvalidArgumentException('Each stock line needs a valid product_id.');
            }
            if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty <= 0) {
                throw new InvalidArgumentException('Stock quantity must be a whole number greater than zero.');
            }

            $lines[$productId] = ($lines[$productId] ?? 0) + (int) $qty;
        }

        return $lines;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertType(string $type, array $allowed): void
    {
        if (! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(
                "Ledger type [{$type}] is not allowed here; expected one of: ".implode(', ', $allowed).'.'
            );
        }
    }

    private function resolveDate(CarbonInterface|string|null $date): Carbon
    {
        if ($date === null) {
            return Carbon::now();
        }

        return $date instanceof CarbonInterface ? Carbon::instance($date) : Carbon::parse($date);
    }

    private function stripReversalPrefix(string $reference): string
    {
        return str_starts_with($reference, self::REVERSAL_PREFIX)
            ? substr($reference, strlen(self::REVERSAL_PREFIX))
            : $reference;
    }

    private function shortageMessage(string $key, int $productId, int $warehouseId, int $available, int $requested): string
    {
        $product = Product::find($productId);
        $warehouse = Warehouse::find($warehouseId);

        return __("stock.{$key}", [
            'product' => $product?->name ?? '#'.$productId,
            'code' => $product?->code ?? '-',
            'warehouse' => $warehouse?->name ?? '#'.$warehouseId,
            'available' => $available,
            'requested' => $requested,
        ]);
    }
}
