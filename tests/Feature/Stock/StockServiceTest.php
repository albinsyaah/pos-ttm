<?php

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

/*
 * Helpers are prefixed with "stock" so they cannot clash with helpers
 * defined in other test files.
 */
function stockProduct(string $code = 'P-001'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function stockWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

/** Any saved model can be a stock "source"; a Sale is the most natural one. */
function stockSource(Warehouse $warehouse): Sale
{
    return Sale::create([
        'invoice_number' => 'INV-'.uniqid(),
        'sale_date' => now()->toDateString(),
        'total_amount' => 0,
        'source' => 'pos',
        'warehouse_id' => $warehouse->id,
    ]);
}

function stockService(): StockService
{
    return app(StockService::class);
}

it('adds the new source and index columns to inventory_ledgers', function () {
    expect(Schema::hasColumns('inventory_ledgers', ['source_type', 'source_id']))->toBeTrue();
});

it('records an IN movement with a positive qty and a running balance', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();

    $first = stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');
    $second = stockService()->increase($product->id, $warehouse->id, 5, 'PB-002');

    expect($first->type)->toBe('IN')
        ->and($first->qty)->toBe(10)
        ->and($first->balance)->toBe(10)
        ->and($second->balance)->toBe(15)
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(15);
});

it('records an OUT movement with a negative qty and lowers the balance', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');

    $entry = stockService()->decrease($product->id, $warehouse->id, 4, 'INV-001');

    expect($entry->type)->toBe('OUT')
        ->and($entry->qty)->toBe(-4)
        ->and($entry->balance)->toBe(6)
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(6);
});

it('keeps stock separate per warehouse', function () {
    $product = stockProduct();
    $a = stockWarehouse('WH-A');
    $b = stockWarehouse('WH-B');

    stockService()->increase($product->id, $a->id, 10, 'PB-A');

    expect(stockService()->available($product->id, $a->id))->toBe(10)
        ->and(stockService()->available($product->id, $b->id))->toBe(0);

    expect(fn () => stockService()->decrease($product->id, $b->id, 1, 'INV-B'))
        ->toThrow(InsufficientStockException::class);
});

it('blocks a decrease that would make stock negative and writes nothing', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    stockService()->increase($product->id, $warehouse->id, 3, 'PB-001');
    $rowsBefore = InventoryLedger::count();

    expect(fn () => stockService()->decrease($product->id, $warehouse->id, 4, 'INV-001'))
        ->toThrow(InsufficientStockException::class);

    expect(InventoryLedger::count())->toBe($rowsBefore)
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(3);
});

it('throws a ValidationException with the message under the items key', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();

    try {
        stockService()->decrease($product->id, $warehouse->id, 1, 'INV-001');
        $this->fail('Expected InsufficientStockException.');
    } catch (ValidationException $e) {
        expect($e)->toBeInstanceOf(InsufficientStockException::class)
            ->and($e->errors())->toHaveKey('items')
            ->and($e->errors()['items'][0])->toContain($product->code);
    }
});

it('adds up the same product listed twice before checking stock', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');
    $rowsBefore = InventoryLedger::count();

    // e.g. one paid line (6) and one free line (6): 12 > 10 even though each line alone fits.
    expect(fn () => stockService()->decreaseMany($warehouse->id, [
        ['product_id' => $product->id, 'qty' => 6],
        ['product_id' => $product->id, 'qty' => 6],
    ], 'INV-001'))->toThrow(InsufficientStockException::class);

    expect(InventoryLedger::count())->toBe($rowsBefore);

    $rows = stockService()->decreaseMany($warehouse->id, [
        ['product_id' => $product->id, 'qty' => 6],
        ['product_id' => $product->id, 'qty' => 4],
    ], 'INV-002');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->qty)->toBe(-10)
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(0);
});

it('writes nothing for any product when one of several is short and lists every shortage', function () {
    $ok = stockProduct('P-OK');
    $shortA = stockProduct('P-SHORT-A');
    $shortB = stockProduct('P-SHORT-B');
    $warehouse = stockWarehouse();
    stockService()->increase($ok->id, $warehouse->id, 10, 'PB-001');
    $rowsBefore = InventoryLedger::count();

    try {
        stockService()->decreaseMany($warehouse->id, [
            ['product_id' => $ok->id, 'qty' => 5],
            ['product_id' => $shortA->id, 'qty' => 1],
            ['product_id' => $shortB->id, 'qty' => 1],
        ], 'INV-001');
        $this->fail('Expected InsufficientStockException.');
    } catch (InsufficientStockException $e) {
        expect($e->shortages)->toHaveCount(2);
    }

    expect(InventoryLedger::count())->toBe($rowsBefore)
        ->and(stockService()->available($ok->id, $warehouse->id))->toBe(10);
});

it('accepts numeric strings such as those coming from a form request', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');

    $rows = stockService()->decreaseMany($warehouse->id, [
        ['product_id' => (string) $product->id, 'qty' => '3'],
    ], 'INV-001');

    expect($rows->first()->qty)->toBe(-3);
});

it('rejects quantities that are zero, negative or not whole numbers', function (mixed $qty) {
    $product = stockProduct();
    $warehouse = stockWarehouse();

    expect(fn () => stockService()->increaseMany($warehouse->id, [
        ['product_id' => $product->id, 'qty' => $qty],
    ], 'PB-BAD'))->toThrow(InvalidArgumentException::class);
})->with([0, -2, '1.5', 'abc']);

it('rejects a ledger type that does not match the direction', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    stockService()->increase($product->id, $warehouse->id, 5, 'PB-001');

    expect(fn () => stockService()->increase($product->id, $warehouse->id, 1, 'X', null, null, 'OUT'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => stockService()->decrease($product->id, $warehouse->id, 1, 'X', null, null, 'IN'))
        ->toThrow(InvalidArgumentException::class);
});

it('uses the latest existing balance, including rows written without a source', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();

    // A row like the ones the seeders write: no source.
    InventoryLedger::create([
        'transaction_date' => now(),
        'reference_number' => 'SEED-001',
        'type' => 'IN',
        'qty' => 7,
        'balance' => 7,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
    ]);

    expect(stockService()->available($product->id, $warehouse->id))->toBe(7);

    $entry = stockService()->decrease($product->id, $warehouse->id, 3, 'INV-001');

    expect($entry->balance)->toBe(4);
});

it('stores the source transaction on the ledger row', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    $sale = stockSource($warehouse);
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');

    $entry = stockService()->decrease($product->id, $warehouse->id, 2, $sale->invoice_number, $sale);

    expect($entry->source_type)->toBe($sale->getMorphClass())
        ->and($entry->source_id)->toBe($sale->id)
        ->and($entry->source->is($sale))->toBeTrue();
});

it('reverses a transaction with a reversal row and restores the balance', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    $sale = stockSource($warehouse);
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');
    stockService()->decrease($product->id, $warehouse->id, 4, 'INV-001', $sale);
    $rowsBefore = InventoryLedger::count();

    $reversal = stockService()->reverse($sale);

    expect($reversal)->toHaveCount(1)
        ->and($reversal->first()->type)->toBe('IN')
        ->and($reversal->first()->qty)->toBe(4)
        ->and($reversal->first()->reference_number)->toBe('REV-INV-001')
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(10)
        // append-only: the original row is still there, one row was added
        ->and(InventoryLedger::count())->toBe($rowsBefore + 1);
});

it('does nothing when the same transaction is reversed twice', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    $sale = stockSource($warehouse);
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');
    stockService()->decrease($product->id, $warehouse->id, 4, 'INV-001', $sale);

    stockService()->reverse($sale);
    $rowsAfterFirst = InventoryLedger::count();
    $second = stockService()->reverse($sale);

    expect($second)->toBeEmpty()
        ->and(InventoryLedger::count())->toBe($rowsAfterFirst)
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(10);
});

it('supports the edit flow: reverse the old movement, then apply the new one', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    $sale = stockSource($warehouse);
    stockService()->increase($product->id, $warehouse->id, 10, 'PB-001');
    stockService()->decrease($product->id, $warehouse->id, 4, 'INV-001', $sale);

    DB::transaction(function () use ($sale, $product, $warehouse) {
        stockService()->reverse($sale);
        stockService()->decrease($product->id, $warehouse->id, 7, 'INV-001', $sale);
    });

    expect(stockService()->available($product->id, $warehouse->id))->toBe(3);

    // Reversing again cancels the *net* effect (only the new 7 is still out).
    stockService()->reverse($sale);
    expect(stockService()->available($product->id, $warehouse->id))->toBe(10);
});

it('refuses to reverse an IN when that stock has already been used', function () {
    $product = stockProduct();
    $warehouse = stockWarehouse();
    $purchase = stockSource($warehouse);
    stockService()->increase($product->id, $warehouse->id, 5, 'PB-001', $purchase);
    stockService()->decrease($product->id, $warehouse->id, 5, 'INV-001');
    $rowsBefore = InventoryLedger::count();

    expect(fn () => stockService()->reverse($purchase))->toThrow(InsufficientStockException::class);

    expect(InventoryLedger::count())->toBe($rowsBefore)
        ->and(stockService()->available($product->id, $warehouse->id))->toBe(0);
});

it('reverses a transfer across two warehouses', function () {
    $product = stockProduct();
    $from = stockWarehouse('WH-A');
    $to = stockWarehouse('WH-B');
    $transfer = stockSource($from);

    stockService()->increase($product->id, $from->id, 10, 'PB-001');
    stockService()->decrease($product->id, $from->id, 6, 'TRF-001', $transfer, null, 'TRANSFER_OUT');
    stockService()->increase($product->id, $to->id, 6, 'TRF-001', $transfer, null, 'TRANSFER_IN');

    $reversal = stockService()->reverse($transfer);

    expect($reversal)->toHaveCount(2)
        ->and(stockService()->available($product->id, $from->id))->toBe(10)
        ->and(stockService()->available($product->id, $to->id))->toBe(0)
        ->and($reversal->pluck('type')->sort()->values()->all())->toBe(['TRANSFER_IN', 'TRANSFER_OUT']);
});

it('returns nothing when reversing a transaction that never moved stock', function () {
    $warehouse = stockWarehouse();
    $sale = stockSource($warehouse);

    expect(stockService()->reverse($sale))->toBeEmpty();
});
