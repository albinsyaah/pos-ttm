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
 * Controllers should call sync() on create, edit and delete: it writes only the
 * difference between what a transaction already did to stock and what it should do
 * now. reverse() is the blunt alternative that undoes everything a source did.
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
     * Bring the stock effect of a transaction in line with its current state,
     * writing only the difference.
     *
     * This is the method controllers call on create, edit and delete:
     *  - pass the transaction's current warehouse and lines to set its stock effect;
     *  - pass null / an empty array to cancel it (delete, or status no longer counts).
     *
     * It compares what the transaction has already done to stock (the net of the
     * ledger rows tied to $source, per product + warehouse) with what it should
     * do now, and writes one row per difference. Consequences:
     *  - unchanged transaction: nothing is written;
     *  - a purchase of 10 edited to 12 adds 2, even if 8 were already sold;
     *  - a purchase of 10 edited to 5 is refused if fewer than 5 are left in stock;
     *  - changing the warehouse takes stock out of the old one and into the new one.
     * Rows that cancel an earlier effect use the "REV-" reference prefix.
     * Only IN / OUT rows are written; transfers between warehouses use syncTransfer().
     *
     * @param  'in'|'out'  $direction  'in' for purchases and sale returns, 'out' for sales and purchase returns.
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items
     * @return Collection<int, InventoryLedger>  The rows written (empty if nothing changed).
     *
     * @throws InsufficientStockException
     */
    public function sync(
        Model $source,
        ?int $warehouseId,
        array $items,
        string $direction,
        string $referenceNumber,
        CarbonInterface|string|null $date = null
    ): Collection {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new InvalidArgumentException("Direction must be 'in' or 'out', got [{$direction}].");
        }

        $sign = $direction === 'in' ? 1 : -1;

        $desired = [];
        if ($warehouseId !== null && $items !== []) {
            foreach ($this->aggregate($items) as $productId => $qty) {
                $desired[$productId.':'.$warehouseId] = $sign * $qty;
            }
        }

        return $this->reconcile(
            $source,
            $desired,
            $referenceNumber,
            $date,
            InventoryLedger::TYPE_IN,
            InventoryLedger::TYPE_OUT
        );
    }

    /**
     * Same idea as sync(), for a transfer between two warehouses: stock goes
     * out of $fromWarehouseId and into $toWarehouseId, as TRANSFER_OUT /
     * TRANSFER_IN rows (the types the Stock Card report already knows).
     *
     * Pass null for both warehouses (or no items) to cancel the transfer. All
     * changes are checked together, so a transfer is refused as a whole when
     * the source warehouse is short, or when cancelling / reducing it would
     * take back stock the destination warehouse has already used.
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items
     * @return Collection<int, InventoryLedger>  The rows written (empty if nothing changed).
     *
     * @throws InsufficientStockException
     */
    public function syncTransfer(
        Model $source,
        ?int $fromWarehouseId,
        ?int $toWarehouseId,
        array $items,
        string $referenceNumber,
        CarbonInterface|string|null $date = null
    ): Collection {
        if ($fromWarehouseId !== null && $fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('A transfer needs two different warehouses.');
        }

        $desired = [];
        if ($fromWarehouseId !== null && $toWarehouseId !== null && $items !== []) {
            foreach ($this->aggregate($items) as $productId => $qty) {
                $desired[$productId.':'.$fromWarehouseId] = -$qty;
                $desired[$productId.':'.$toWarehouseId] = $qty;
            }
        }

        return $this->reconcile(
            $source,
            $desired,
            $referenceNumber,
            $date,
            InventoryLedger::TYPE_TRANSFER_IN,
            InventoryLedger::TYPE_TRANSFER_OUT
        );
    }

    /**
     * Same idea as sync(), for a stock adjustment such as a stock-opname
     * deviation: the quantities are signed, positive when stock is found
     * above the system figure (written as IN) and negative when it is below
     * (written as OUT), all in one warehouse.
     *
     * The same product on several lines is netted. Pass a null warehouse (or
     * no items) to cancel the adjustment. A shortage is refused when the
     * warehouse does not hold that much, and so is cancelling an overage
     * whose stock has already been used.
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items  qty is signed and never zero.
     * @return Collection<int, InventoryLedger>  The rows written (empty if nothing changed).
     *
     * @throws InsufficientStockException
     */
    public function syncAdjustment(
        Model $source,
        ?int $warehouseId,
        array $items,
        string $referenceNumber,
        CarbonInterface|string|null $date = null
    ): Collection {
        $desired = [];
        if ($warehouseId !== null && $items !== []) {
            foreach ($this->aggregateSigned($items) as $productId => $net) {
                $desired[$productId.':'.$warehouseId] = $net;
            }
        }

        return $this->reconcile(
            $source,
            $desired,
            $referenceNumber,
            $date,
            InventoryLedger::TYPE_IN,
            InventoryLedger::TYPE_OUT
        );
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
     * Shared core of sync() and syncTransfer().
     *
     * $desired maps "product_id:warehouse_id" to the signed qty the source
     * should have moved in total. It is compared with what the ledger rows
     * tied to $source already add up to, and one row is written per
     * difference. Every decrease is checked against the warehouse balance
     * first, and nothing is written if any line is short.
     *
     * @param  array<string, int>  $desired
     * @return Collection<int, InventoryLedger>
     *
     * @throws InsufficientStockException
     */
    private function reconcile(
        Model $source,
        array $desired,
        string $referenceNumber,
        CarbonInterface|string|null $date,
        string $inType,
        string $outType
    ): Collection {
        return DB::transaction(function () use ($source, $desired, $referenceNumber, $date, $inType, $outType) {
            // Lock first, then read what the source has done so far, so two
            // edits of the same transaction cannot both act on stale numbers.
            $productIds = array_map(fn ($key) => (int) explode(':', $key)[0], array_keys($desired));
            foreach (array_keys($this->netBySource($source)) as $key) {
                $productIds[] = (int) explode(':', $key)[0];
            }
            if ($productIds === []) {
                return collect();
            }
            $this->lockProducts($productIds);

            $current = $this->netBySource($source);

            $plan = [];
            foreach (array_unique(array_merge(array_keys($desired), array_keys($current))) as $key) {
                $already = $current[$key] ?? 0;
                $delta = ($desired[$key] ?? 0) - $already;
                if ($delta === 0) {
                    continue;
                }
                [$productId, $warehouse] = array_map('intval', explode(':', $key));
                $plan[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouse,
                    'delta' => $delta,
                    'already' => $already,
                ];
            }

            if ($plan === []) {
                return collect();
            }

            usort($plan, fn ($a, $b) => [$a['product_id'], $a['warehouse_id']] <=> [$b['product_id'], $b['warehouse_id']]);

            $shortages = [];
            foreach ($plan as $line) {
                if ($line['delta'] < 0) {
                    $available = $this->available($line['product_id'], $line['warehouse_id']);
                    if ($available < -$line['delta']) {
                        // Taking back stock this source had added, or taking out more?
                        $shortages[] = $this->shortageMessage(
                            $line['already'] > 0 ? 'insufficient_reverse' : 'insufficient',
                            $line['product_id'],
                            $line['warehouse_id'],
                            $available,
                            -$line['delta']
                        );
                    }
                }
            }
            if ($shortages !== []) {
                throw InsufficientStockException::forShortages($shortages);
            }

            $moment = $this->resolveDate($date);
            $created = collect();
            foreach ($plan as $line) {
                // A row that moves stock the opposite way to what the source did
                // before cancels an earlier effect, so it is marked as a reversal.
                $undoesEarlierEffect = $line['already'] !== 0 && $line['delta'] * $line['already'] < 0;

                $created->push($this->write(
                    $line['product_id'],
                    $line['warehouse_id'],
                    $line['delta'] > 0 ? $inType : $outType,
                    $line['delta'],
                    $undoesEarlierEffect
                        ? self::REVERSAL_PREFIX.$this->stripReversalPrefix($referenceNumber)
                        : $referenceNumber,
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
     * Net qty already booked for a source, per "product_id:warehouse_id".
     *
     * @return array<string, int>
     */
    private function netBySource(Model $source): array
    {
        return InventoryLedger::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->get()
            ->groupBy(fn (InventoryLedger $e) => $e->product_id.':'.$e->warehouse_id)
            ->map(fn ($group) => (int) $group->sum('qty'))
            ->filter(fn (int $net) => $net !== 0)
            ->all();
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
     * Like aggregate(), but quantities are signed: any whole number except
     * zero, added up per product (a product may end up netting to zero).
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items
     * @return array<int, int>  product_id => net qty
     */
    private function aggregateSigned(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = $item['qty'] ?? null;

            if ($productId <= 0) {
                throw new InvalidArgumentException('Each stock line needs a valid product_id.');
            }
            if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty === 0) {
                throw new InvalidArgumentException('Adjustment quantity must be a whole number other than zero.');
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
