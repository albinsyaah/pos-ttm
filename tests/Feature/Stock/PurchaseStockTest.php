<?php

use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

/*
 * Helper names are prefixed "purchaseTest" so they cannot clash with the
 * helpers in other test files (Pest loads them all into one process).
 */
function purchaseTestLogin(): User
{
    Permission::findOrCreate('transactions.purchases.view', 'web');
    Permission::findOrCreate('transactions.purchases.manage', 'web');

    // App\Models\User has no HasFactory trait, so create the user directly.
    $user = User::create([
        'username' => 'kasir-test-'.uniqid(),
        'password' => Hash::make('password'),
        'role' => 'Staff',
        'is_active' => true,
    ]);
    $user->givePermissionTo(['transactions.purchases.view', 'transactions.purchases.manage']);

    test()->actingAs($user);

    return $user;
}

function purchaseTestProduct(string $code = 'P-001'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function purchaseTestWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

function purchaseTestSupplier(): Supplier
{
    return Supplier::firstOrCreate(['code' => 'SUP-1'], ['name' => 'Supplier 1']);
}

/**
 * @param  array<int, array{product_id: int, qty: int, price?: int}>  $items
 */
function purchaseTestPayload(Warehouse $warehouse, array $items, string $status = 'received', string $invoice = 'PB-001'): array
{
    return [
        'invoice_number' => $invoice,
        'purchase_date' => '2026-09-30',
        'status' => $status,
        'supplier_id' => purchaseTestSupplier()->id,
        'warehouse_id' => $warehouse->id,
        'items' => array_map(fn ($item) => $item + ['price' => 1000], $items),
    ];
}

function purchaseTestStock(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

it('adds stock to the chosen warehouse when a purchase is received', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ]))->assertRedirect(route('transactions.purchases.index'));

    $purchase = Purchase::firstOrFail();
    $entry = InventoryLedger::firstOrFail();

    expect(purchaseTestStock($product, $warehouse))->toBe(10)
        ->and($entry->type)->toBe('IN')
        ->and($entry->reference_number)->toBe('PB-001')
        ->and($entry->source_type)->toBe($purchase->getMorphClass())
        ->and($entry->source_id)->toBe($purchase->id);
});

it('does not add stock for a pending purchase', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ], 'pending'))->assertRedirect();

    expect(Purchase::count())->toBe(1)
        ->and(InventoryLedger::count())->toBe(0);
});

it('adds up the same product listed on two lines', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 6],
        ['product_id' => $product->id, 'qty' => 4],
    ]))->assertRedirect();

    expect(purchaseTestStock($product, $warehouse))->toBe(10)
        ->and(InventoryLedger::count())->toBe(1);
});

it('adds stock when a pending purchase is changed to received', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();
    $items = [['product_id' => $product->id, 'qty' => 10]];

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, $items, 'pending'));
    $purchase = Purchase::firstOrFail();

    $this->put(route('transactions.purchases.update', $purchase), purchaseTestPayload($warehouse, $items, 'received'))
        ->assertRedirect();

    expect(purchaseTestStock($product, $warehouse))->toBe(10);
});

it('takes the stock back when a received purchase is cancelled', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();
    $items = [['product_id' => $product->id, 'qty' => 10]];

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, $items, 'received'));
    $purchase = Purchase::firstOrFail();

    $this->put(route('transactions.purchases.update', $purchase), purchaseTestPayload($warehouse, $items, 'cancelled'))
        ->assertRedirect();

    expect(purchaseTestStock($product, $warehouse))->toBe(0);
});

it('writes no new ledger rows when an edit does not change stock', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();
    $items = [['product_id' => $product->id, 'qty' => 10]];

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, $items));
    $purchase = Purchase::firstOrFail();
    $rowsBefore = InventoryLedger::count();

    $payload = purchaseTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 10, 'price' => 2500]]);
    $this->put(route('transactions.purchases.update', $purchase), $payload)->assertRedirect();

    expect(InventoryLedger::count())->toBe($rowsBefore);
});

it('lets a received purchase be increased even when part of the stock was already sold', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ]));
    $purchase = Purchase::firstOrFail();
    app(StockService::class)->decrease($product->id, $warehouse->id, 8, 'INV-001');

    $this->put(route('transactions.purchases.update', $purchase), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 12],
    ]))->assertRedirect();

    expect(purchaseTestStock($product, $warehouse))->toBe(4);
});

it('refuses an edit that would take back stock that was already sold', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ]));
    $purchase = Purchase::firstOrFail();
    app(StockService::class)->decrease($product->id, $warehouse->id, 8, 'INV-001');
    $rowsBefore = InventoryLedger::count();

    $this->put(route('transactions.purchases.update', $purchase), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 5],
    ]))->assertSessionHasErrors('items');

    expect(InventoryLedger::count())->toBe($rowsBefore)
        ->and(purchaseTestStock($product, $warehouse))->toBe(2)
        // the purchase itself was rolled back too
        ->and($purchase->fresh()->purchaseDetails()->first()->qty)->toBe(10);
});

it('moves the stock when the warehouse of a received purchase changes', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $a = purchaseTestWarehouse('WH-A');
    $b = purchaseTestWarehouse('WH-B');
    $items = [['product_id' => $product->id, 'qty' => 10]];

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($a, $items));
    $purchase = Purchase::firstOrFail();

    $this->put(route('transactions.purchases.update', $purchase), purchaseTestPayload($b, $items))->assertRedirect();

    expect(purchaseTestStock($product, $a))->toBe(0)
        ->and(purchaseTestStock($product, $b))->toBe(10);
});

it('takes the stock back when a received purchase is deleted', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ]));
    $purchase = Purchase::firstOrFail();

    $this->delete(route('transactions.purchases.destroy', $purchase))->assertRedirect(route('transactions.purchases.index'));

    expect(Purchase::count())->toBe(0)
        ->and(purchaseTestStock($product, $warehouse))->toBe(0);
});

it('refuses to delete a received purchase whose stock was already sold', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ]));
    $purchase = Purchase::firstOrFail();
    app(StockService::class)->decrease($product->id, $warehouse->id, 8, 'INV-001');

    $this->delete(route('transactions.purchases.destroy', $purchase))->assertSessionHas('error');

    expect(Purchase::count())->toBe(1)
        ->and(purchaseTestStock($product, $warehouse))->toBe(2);
});

it('deletes a pending purchase without touching stock', function () {
    purchaseTestLogin();
    $product = purchaseTestProduct();
    $warehouse = purchaseTestWarehouse();

    $this->post(route('transactions.purchases.store'), purchaseTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 10],
    ], 'pending'));
    $purchase = Purchase::firstOrFail();

    $this->delete(route('transactions.purchases.destroy', $purchase))->assertRedirect();

    expect(Purchase::count())->toBe(0)
        ->and(InventoryLedger::count())->toBe(0);
});
