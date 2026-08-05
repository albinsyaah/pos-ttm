<?php

namespace Database\Seeders\Concerns;

use App\Models\InventoryLedger;
use Carbon\Carbon;

/**
 * Shared helper so every seeder that moves stock (purchases, sales,
 * returns, internal mutations) writes consistent, running-balance
 * inventory_ledgers rows instead of each re-implementing the math.
 *
 * Balances are cached in memory per product+warehouse for the duration
 * of a single `db:seed` run, seeded from whatever is already on disk
 * (0 on a fresh database).
 */
trait ManagesInventoryLedger
{
    /**
     * @var array<string, int> "productId:warehouseId" => running balance
     */
    protected static array $stockBalances = [];

    protected function stockKey(int $productId, int $warehouseId): string
    {
        return $productId.':'.$warehouseId;
    }

    protected function currentStock(int $productId, int $warehouseId): int
    {
        $key = $this->stockKey($productId, $warehouseId);

        if (! array_key_exists($key, static::$stockBalances)) {
            $last = InventoryLedger::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->value('balance');

            static::$stockBalances[$key] = $last ?? 0;
        }

        return static::$stockBalances[$key];
    }

    /**
     * Record a movement and return the resulting balance.
     *
     * @param  int  $qty  Positive for IN movements, negative for OUT movements.
     */
    protected function moveStock(
        int $productId,
        int $warehouseId,
        string $type,
        int $qty,
        string $referenceNumber,
        Carbon $date
    ): int {
        $key = $this->stockKey($productId, $warehouseId);
        $balance = $this->currentStock($productId, $warehouseId) + $qty;
        $balance = max($balance, 0);
        static::$stockBalances[$key] = $balance;

        InventoryLedger::create([
            'transaction_date' => $date,
            'reference_number' => $referenceNumber,
            'type' => $type,
            'qty' => $qty,
            'balance' => $balance,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
        ]);

        return $balance;
    }
}
