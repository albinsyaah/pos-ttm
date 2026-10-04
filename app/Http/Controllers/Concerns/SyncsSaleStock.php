<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Sale;
use App\Services\StockService;

/**
 * Shared by every controller that writes to the `sales` table (Sales,
 * Point of Sale, Point of Sale terminal, Sales SPG), so they all move stock
 * the same way.
 *
 * Call it inside the controller's DB::transaction(), after the sale's
 * header and lines are saved:
 *  - store / update: $this->syncSaleStock($sale, $data['items']);
 *  - destroy:        $this->syncSaleStock($sale);   // puts the stock back
 *
 * StockService::sync() writes only the difference from what the sale already
 * did to stock, and throws InsufficientStockException (a ValidationException)
 * if a warehouse does not have enough. Thrown inside the transaction, that
 * rolls the whole save back and sends the user back to the form with the
 * message under the "items" error key.
 */
trait SyncsSaleStock
{
    /**
     * @param  array<int, array{product_id: int|string, qty: int|string}>|null  $items
     *         The sale's current lines, or null to cancel its stock effect (delete).
     */
    protected function syncSaleStock(Sale $sale, ?array $items = null): void
    {
        app(StockService::class)->sync(
            $sale,
            $items === null ? null : (int) $sale->warehouse_id,
            $items ?? [],
            'out',
            $sale->invoice_number,
            $sale->sale_date
        );
    }

    /**
     * Same, for the cashier terminals: the sale has no warehouse of its own and its
     * stock is taken from whichever warehouses have it (see
     * StockService::syncFromAllWarehouses). Pass null to put the stock back.
     *
     * @param  array<int, array{product_id: int|string, qty: int|string}>|null  $items
     */
    protected function syncSaleStockFromAllWarehouses(Sale $sale, ?array $items = null): void
    {
        app(StockService::class)->syncFromAllWarehouses(
            $sale,
            $items,
            $sale->invoice_number,
            $sale->sale_date
        );
    }
}
