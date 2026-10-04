<?php

use App\Models\Customer;
use App\Models\InternalMutation;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\StockBackfillService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

/*
 * Helper names are prefixed "backfillTest" so they cannot clash with helpers
 * in other test files (Pest loads them all into one process).
 */
function backfillTestProduct(string $code = 'P-001'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function backfillTestWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

function backfillTestStock(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

/** A purchase saved before automatic stock handling: no ledger rows. */
function backfillTestPurchase(Warehouse $warehouse, Product $product, int $qty, string $status = 'received', string $invoice = 'PB-001', string $date = '2026-09-01'): Purchase
{
    $supplier = Supplier::firstOrCreate(['code' => 'SUP-1'], ['name' => 'Supplier 1']);

    $purchase = Purchase::create([
        'invoice_number' => $invoice,
        'purchase_date' => $date,
        'total_amount' => 0,
        'status' => $status,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $purchase->purchaseDetails()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => 1000]);

    return $purchase;
}

function backfillTestSale(Warehouse $warehouse, Product $product, int $qty, string $invoice = 'INV-001', string $date = '2026-09-02'): Sale
{
    $customer = Customer::firstOrCreate(['code' => 'CUST-1'], ['name' => 'Pelanggan 1']);

    $sale = Sale::create([
        'invoice_number' => $invoice,
        'sale_date' => $date,
        'total_amount' => 0,
        'source' => 'sales',
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $sale->saleDetails()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => 1000]);

    return $sale;
}

/** @param array<int, array{0: int, 1: int}> $lines  [product_id, qty] */
function backfillTestMutation(string $type, string $number, string $status, ?Warehouse $from, ?Warehouse $to, array $lines, string $date = '2026-09-01'): InternalMutation
{
    $mutation = InternalMutation::create([
        'mutation_number' => $number,
        'type' => $type,
        'mutation_date' => $date,
        'status' => $status,
        'from_warehouse_id' => $from?->id,
        'to_warehouse_id' => $to?->id,
    ]);
    foreach ($lines as [$productId, $qty]) {
        $mutation->internalMutationDetails()->create(['product_id' => $productId, 'qty' => $qty]);
    }

    return $mutation;
}

/** A ledger row the way the seeders write them: no source. */
function backfillTestLegacyRow(Product $product, Warehouse $warehouse, string $type, int $qty, string $reference, int $balance): InventoryLedger
{
    return InventoryLedger::create([
        'transaction_date' => '2026-09-01',
        'reference_number' => $reference,
        'type' => $type,
        'qty' => $qty,
        'balance' => $balance,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
    ]);
}

function backfillTestRun(array $steps, bool $apply): array
{
    return app(StockBackfillService::class)->run($steps, $apply);
}

/*
 * link
 */
it('a dry run reports what it would do and writes nothing', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 10);
    $row = backfillTestLegacyRow($product, $warehouse, 'IN', 10, 'PB-001', 10);
    backfillTestSale($warehouse, $product, 4);

    $report = backfillTestRun(['link', 'create-missing'], false);

    expect($report['ledger']['counts']['purchase'])->toBe(['would_link' => 1])
        ->and($report['ledger']['counts']['sale'])->toBe(['would_create' => 1])
        ->and(InventoryLedger::count())->toBe(1)
        ->and($row->fresh()->source_type)->toBeNull();
});

it('links existing ledger rows to their purchase without changing stock', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    $purchase = backfillTestPurchase($warehouse, $product, 10);
    backfillTestLegacyRow($product, $warehouse, 'IN', 10, 'PB-001', 10);

    $report = backfillTestRun(['link'], true);
    $row = InventoryLedger::firstOrFail();

    expect($report['ledger']['counts']['purchase'])->toBe(['linked' => 1])
        ->and($row->source_type)->toBe($purchase->getMorphClass())
        ->and($row->source_id)->toBe($purchase->id)
        ->and(InventoryLedger::count())->toBe(1)
        ->and(backfillTestStock($product, $warehouse))->toBe(10);
});

it('after linking, editing the old purchase writes only the difference', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    $purchase = backfillTestPurchase($warehouse, $product, 10);
    backfillTestLegacyRow($product, $warehouse, 'IN', 10, 'PB-001', 10);
    backfillTestRun(['link'], true);

    // the purchase page now raises the quantity from 10 to 12
    $rows = app(StockService::class)->sync($purchase, $warehouse->id, [
        ['product_id' => $product->id, 'qty' => 12],
    ], 'in', 'PB-001');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->qty)->toBe(2)
        ->and(backfillTestStock($product, $warehouse))->toBe(12);
});

it('does not link when the ledger rows disagree with the document, and says so', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 10);
    $row = backfillTestLegacyRow($product, $warehouse, 'IN', 7, 'PB-001', 7);

    $report = backfillTestRun(['link'], true);

    expect($report['ledger']['counts']['purchase'])->toBe(['mismatch' => 1])
        ->and($report['ledger']['problems'])->toHaveCount(1)
        ->and($report['ledger']['problems'][0])->toContain('PB-001')
        ->and($row->fresh()->source_type)->toBeNull();
});

it('leaves documents alone when two of them share the same number', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 10, 'received', 'DUP-1');
    backfillTestSale($warehouse, $product, 4, 'DUP-1');
    backfillTestLegacyRow($product, $warehouse, 'IN', 10, 'DUP-1', 10);
    backfillTestLegacyRow($product, $warehouse, 'OUT', -4, 'DUP-1', 6);

    $report = backfillTestRun(['link'], true);

    expect($report['ledger']['counts']['purchase'])->toBe(['ambiguous' => 1])
        ->and($report['ledger']['counts']['sale'])->toBe(['ambiguous' => 1])
        ->and(InventoryLedger::whereNotNull('source_type')->count())->toBe(0);
});

it('links a warehouse transfer and a sales return from their legacy rows', function () {
    $product = backfillTestProduct();
    $a = backfillTestWarehouse('WH-A');
    $b = backfillTestWarehouse('WH-B');
    $transfer = backfillTestMutation('Transfer Antar Gudang', 'TRF-001', 'Completed', $a, $b, [[$product->id, 6]]);
    backfillTestLegacyRow($product, $a, 'TRANSFER_OUT', -6, 'TRF-001', 4);
    backfillTestLegacyRow($product, $b, 'TRANSFER_IN', 6, 'TRF-001', 6);

    $sale = backfillTestSale($a, $product, 3);
    $return = SalesReturn::create(['return_number' => 'RJ-001', 'return_date' => '2026-09-03', 'total_amount' => 0, 'sale_id' => $sale->id]);
    $return->salesReturnDetails()->create(['product_id' => $product->id, 'qty' => 1]);
    backfillTestLegacyRow($product, $a, 'OUT', -3, $sale->invoice_number, 1);
    backfillTestLegacyRow($product, $a, 'IN', 1, 'RJ-001', 2);

    $report = backfillTestRun(['link'], true);

    expect($report['ledger']['counts']['warehouse transfer'])->toBe(['linked' => 1])
        ->and($report['ledger']['counts']['sale'])->toBe(['linked' => 1])
        ->and($report['ledger']['counts']['sales return'])->toBe(['linked' => 1])
        ->and(InventoryLedger::where('source_type', $transfer->getMorphClass())->where('source_id', $transfer->id)->count())->toBe(2)
        ->and(InventoryLedger::whereNull('source_type')->count())->toBe(0);
});

/*
 * create-missing
 */
it('writes ledger rows for old transactions, oldest first, so later sales find stock', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    // the sale has the lower id on purpose; the purchase is older and must go first
    backfillTestSale($warehouse, $product, 4, 'INV-001', '2026-09-02');
    $purchase = backfillTestPurchase($warehouse, $product, 10, 'received', 'PB-001', '2026-09-01');

    $report = backfillTestRun(['create-missing'], true);

    expect($report['ledger']['counts']['purchase'])->toBe(['created' => 1])
        ->and($report['ledger']['counts']['sale'])->toBe(['created' => 1])
        ->and(backfillTestStock($product, $warehouse))->toBe(6)
        ->and(InventoryLedger::where('source_type', $purchase->getMorphClass())->where('source_id', $purchase->id)->count())->toBe(1);
});

it('reports a transaction it cannot write because stock is short and writes nothing for it', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestSale($warehouse, $product, 4, 'INV-001');

    $report = backfillTestRun(['create-missing'], true);

    expect($report['ledger']['counts']['sale'])->toBe(['failed' => 1])
        ->and($report['ledger']['problems'][0])->toContain('INV-001')
        ->and(InventoryLedger::count())->toBe(0);
});

it('is safe to run twice', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 10);
    backfillTestSale($warehouse, $product, 4);

    backfillTestRun(['link', 'create-missing'], true);
    $rowsAfterFirst = InventoryLedger::count();
    $second = backfillTestRun(['link', 'create-missing'], true);

    expect($second['ledger']['counts']['purchase'])->toBe(['already' => 1])
        ->and($second['ledger']['counts']['sale'])->toBe(['already' => 1])
        ->and(InventoryLedger::count())->toBe($rowsAfterFirst)
        ->and(backfillTestStock($product, $warehouse))->toBe(6);
});

it('skips a pending purchase but counts one that old data marked Completed', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 5, 'pending', 'PB-PENDING');
    backfillTestPurchase($warehouse, $product, 8, 'Completed', 'PB-OLD');

    $report = backfillTestRun(['create-missing'], true);

    expect($report['ledger']['counts']['purchase'])->toBe(['not_counted' => 1, 'created' => 1])
        ->and(backfillTestStock($product, $warehouse))->toBe(8);
});

it('writes a completed transfer as TRANSFER_OUT and TRANSFER_IN', function () {
    $product = backfillTestProduct();
    $a = backfillTestWarehouse('WH-A');
    $b = backfillTestWarehouse('WH-B');
    backfillTestPurchase($a, $product, 10);
    backfillTestMutation('Transfer Antar Gudang', 'TRF-001', 'completed', $a, $b, [[$product->id, 6]]);
    backfillTestMutation('Transfer Antar Gudang', 'TRF-002', 'pending', $a, $b, [[$product->id, 3]]);

    $report = backfillTestRun(['create-missing'], true);

    expect($report['ledger']['counts']['warehouse transfer'])->toBe(['not_counted' => 1, 'created' => 1])
        ->and(backfillTestStock($product, $a))->toBe(4)
        ->and(backfillTestStock($product, $b))->toBe(6)
        ->and(InventoryLedger::whereIn('type', ['TRANSFER_OUT', 'TRANSFER_IN'])->count())->toBe(2);
});

it('writes a completed deviation with signed quantities', function () {
    $short = backfillTestProduct('P-SHORT');
    $over = backfillTestProduct('P-OVER');
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $short, 10);
    backfillTestMutation('Deviation', 'DEV-001', 'completed', $warehouse, null, [[$short->id, -4], [$over->id, 3]]);

    backfillTestRun(['create-missing'], true);

    expect(backfillTestStock($short, $warehouse))->toBe(6)
        ->and(backfillTestStock($over, $warehouse))->toBe(3);
});

it('never moves stock for an item request', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestMutation('Item Request', 'REQ-001', 'completed', null, $warehouse, [[$product->id, 5]]);

    $report = backfillTestRun(['link', 'create-missing'], true);

    expect($report['ledger']['counts'])->toBe([])
        ->and(InventoryLedger::count())->toBe(0);
});

/*
 * normalize-statuses
 */
it('rewrites old status spellings and reports ones it does not know', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    $done = backfillTestPurchase($warehouse, $product, 5, 'Completed', 'PB-1');
    $waiting = backfillTestPurchase($warehouse, $product, 5, 'Pending', 'PB-2');
    $already = backfillTestPurchase($warehouse, $product, 5, 'received', 'PB-3');
    $odd = backfillTestPurchase($warehouse, $product, 5, 'on the way', 'PB-4');
    $mutation = backfillTestMutation('Internal Receipt', 'REC-1', 'Completed', null, $warehouse, [[$product->id, 2]]);

    $dry = backfillTestRun(['normalize-statuses'], false);
    expect($dry['statuses']['purchases'])->toBe(2)
        ->and($dry['statuses']['mutations'])->toBe(1)
        ->and($done->fresh()->status)->toBe('Completed');

    $report = backfillTestRun(['normalize-statuses'], true);

    expect($done->fresh()->status)->toBe('received')
        ->and($waiting->fresh()->status)->toBe('pending')
        ->and($already->fresh()->status)->toBe('received')
        ->and($odd->fresh()->status)->toBe('on the way')
        ->and($mutation->fresh()->status)->toBe('completed')
        ->and($report['statuses']['unknown'])->toHaveCount(1);
});

/*
 * the command
 */
it('refuses --apply without choosing a step', function () {
    $this->artisan('stock:backfill', ['--apply' => true, '--force' => true])->assertFailed();
});

it('does a read-only look at everything when no step is given', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 10);

    $this->artisan('stock:backfill')->assertSuccessful();

    expect(InventoryLedger::count())->toBe(0);
});

it('writes only after confirmation, and not when it is declined', function () {
    $product = backfillTestProduct();
    $warehouse = backfillTestWarehouse();
    backfillTestPurchase($warehouse, $product, 10);

    $this->artisan('stock:backfill', ['--create-missing' => true, '--apply' => true])
        ->expectsConfirmation('This writes to the database (create-missing). Have you made a backup?', 'no')
        ->assertFailed();
    expect(InventoryLedger::count())->toBe(0);

    $this->artisan('stock:backfill', ['--create-missing' => true, '--apply' => true, '--force' => true])->assertSuccessful();
    expect(backfillTestStock($product, $warehouse))->toBe(10);
});
