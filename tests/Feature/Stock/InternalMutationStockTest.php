<?php

use App\Models\InternalMutation;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

/*
 * Helper names are prefixed "mutationTest" so they cannot clash with helpers
 * in other test files (Pest loads them all into one process).
 */
function mutationTestLogin(string $permission): User
{
    Permission::findOrCreate($permission, 'web');

    $user = User::create([
        'username' => 'kasir-test-'.uniqid(),
        'password' => Hash::make('password'),
        'role' => 'Staff',
        'is_active' => true,
    ]);
    $user->givePermissionTo($permission);

    test()->actingAs($user);

    return $user;
}

function mutationTestProduct(string $code = 'P-001'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function mutationTestWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

function mutationTestStock(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

function mutationTestSeed(Product $product, Warehouse $warehouse, int $qty): void
{
    app(StockService::class)->increase($product->id, $warehouse->id, $qty, 'PB-SEED');
}

/** @param array<int, array{product_id: int, qty: int}> $items */
function mutationTestPayload(array $items, string $status = 'completed', array $extra = [], string $number = 'MUT-001'): array
{
    return array_merge([
        'mutation_number' => $number,
        'mutation_date' => '2026-09-30',
        'status' => $status,
        // The existing controllers read $data['requested_by'] directly, so the
        // key must be present (the real form always sends it, empty when unused).
        'requested_by' => null,
        'items' => $items,
    ], $extra);
}

/*
 * Warehouse transfers
 */
it('moves stock between warehouses when a transfer is completed', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $from = mutationTestWarehouse('WH-A');
    $to = mutationTestWarehouse('WH-B');
    mutationTestSeed($product, $from, 10);

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 6],
    ], 'completed', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id]))
        ->assertRedirect(route('transactions.warehouse-transfers.index'));

    $transfer = InternalMutation::firstOrFail();
    $types = InventoryLedger::where('source_type', $transfer->getMorphClass())->pluck('type')->sort()->values()->all();

    expect(mutationTestStock($product, $from))->toBe(4)
        ->and(mutationTestStock($product, $to))->toBe(6)
        ->and($types)->toBe(['TRANSFER_IN', 'TRANSFER_OUT']);
});

it('does not move stock for a pending transfer, and moves it once it is completed', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $from = mutationTestWarehouse('WH-A');
    $to = mutationTestWarehouse('WH-B');
    mutationTestSeed($product, $from, 10);
    $extra = ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id];
    $items = [['product_id' => $product->id, 'qty' => 6]];

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload($items, 'pending', $extra))->assertRedirect();
    $transfer = InternalMutation::firstOrFail();
    expect(mutationTestStock($product, $from))->toBe(10);

    $this->put(route('transactions.warehouse-transfers.update', $transfer), mutationTestPayload($items, 'completed', $extra))->assertRedirect();

    expect(mutationTestStock($product, $from))->toBe(4)
        ->and(mutationTestStock($product, $to))->toBe(6);
});

it('refuses a completed transfer larger than the source stock', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $from = mutationTestWarehouse('WH-A');
    $to = mutationTestWarehouse('WH-B');
    mutationTestSeed($product, $from, 3);
    $rowsBefore = InventoryLedger::count();

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 5],
    ], 'completed', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id]))
        ->assertSessionHasErrors('items');

    expect(InternalMutation::count())->toBe(0)
        ->and(InventoryLedger::count())->toBe($rowsBefore);
});

it('refuses a transfer that uses the same warehouse on both sides', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 10);

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 1],
    ], 'completed', ['from_warehouse_id' => $warehouse->id, 'to_warehouse_id' => $warehouse->id]))
        ->assertSessionHasErrors('from_warehouse_id');

    expect(InternalMutation::count())->toBe(0);
});

it('moves only the difference when a completed transfer is edited', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $from = mutationTestWarehouse('WH-A');
    $to = mutationTestWarehouse('WH-B');
    mutationTestSeed($product, $from, 10);
    $extra = ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id];

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload([['product_id' => $product->id, 'qty' => 4]], 'completed', $extra));
    $transfer = InternalMutation::firstOrFail();

    $this->put(route('transactions.warehouse-transfers.update', $transfer), mutationTestPayload([['product_id' => $product->id, 'qty' => 7]], 'completed', $extra))
        ->assertRedirect();

    expect(mutationTestStock($product, $from))->toBe(3)
        ->and(mutationTestStock($product, $to))->toBe(7);
});

it('gives the stock back when a completed transfer is deleted', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $from = mutationTestWarehouse('WH-A');
    $to = mutationTestWarehouse('WH-B');
    mutationTestSeed($product, $from, 10);

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 6],
    ], 'completed', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id]));
    $transfer = InternalMutation::firstOrFail();

    $this->delete(route('transactions.warehouse-transfers.destroy', $transfer))->assertRedirect();

    expect(InternalMutation::count())->toBe(0)
        ->and(mutationTestStock($product, $from))->toBe(10)
        ->and(mutationTestStock($product, $to))->toBe(0);
});

it('refuses to delete a completed transfer whose stock the destination already used', function () {
    mutationTestLogin('transactions.warehouse-transfers.manage');
    $product = mutationTestProduct();
    $from = mutationTestWarehouse('WH-A');
    $to = mutationTestWarehouse('WH-B');
    mutationTestSeed($product, $from, 10);

    $this->post(route('transactions.warehouse-transfers.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 6],
    ], 'completed', ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id]));
    $transfer = InternalMutation::firstOrFail();
    app(StockService::class)->decrease($product->id, $to->id, 5, 'INV-001');

    $this->delete(route('transactions.warehouse-transfers.destroy', $transfer))->assertSessionHas('error');

    expect(InternalMutation::count())->toBe(1)
        ->and(mutationTestStock($product, $from))->toBe(4)
        ->and(mutationTestStock($product, $to))->toBe(1);
});

/*
 * Internal receipts: stock comes into a warehouse
 */
it('adds stock when an internal receipt is completed', function () {
    mutationTestLogin('transactions.internal-receipts.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();

    $this->post(route('transactions.internal-receipts.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 8],
    ], 'completed', ['to_warehouse_id' => $warehouse->id]))
        ->assertRedirect(route('transactions.internal-receipts.index'));

    $receipt = InternalMutation::firstOrFail();
    $entry = InventoryLedger::where('source_type', $receipt->getMorphClass())->firstOrFail();

    expect(mutationTestStock($product, $warehouse))->toBe(8)
        ->and($entry->type)->toBe('IN')
        ->and($entry->qty)->toBe(8)
        ->and($entry->reference_number)->toBe('MUT-001');
});

it('adds no stock for an approved internal receipt', function () {
    mutationTestLogin('transactions.internal-receipts.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();

    $this->post(route('transactions.internal-receipts.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 8],
    ], 'approved', ['to_warehouse_id' => $warehouse->id]))->assertRedirect();

    expect(InternalMutation::count())->toBe(1)
        ->and(InventoryLedger::count())->toBe(0);
});

it('changes stock by the difference when an internal receipt is edited', function () {
    mutationTestLogin('transactions.internal-receipts.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    $extra = ['to_warehouse_id' => $warehouse->id];

    $this->post(route('transactions.internal-receipts.store'), mutationTestPayload([['product_id' => $product->id, 'qty' => 8]], 'completed', $extra));
    $receipt = InternalMutation::firstOrFail();

    $this->put(route('transactions.internal-receipts.update', $receipt), mutationTestPayload([['product_id' => $product->id, 'qty' => 5]], 'completed', $extra))
        ->assertRedirect();

    expect(mutationTestStock($product, $warehouse))->toBe(5);
});

it('refuses to delete a completed internal receipt whose stock was already used', function () {
    mutationTestLogin('transactions.internal-receipts.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();

    $this->post(route('transactions.internal-receipts.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 8],
    ], 'completed', ['to_warehouse_id' => $warehouse->id]));
    $receipt = InternalMutation::firstOrFail();
    app(StockService::class)->decrease($product->id, $warehouse->id, 6, 'INV-001');

    $this->delete(route('transactions.internal-receipts.destroy', $receipt))->assertSessionHas('error');

    expect(InternalMutation::count())->toBe(1)
        ->and(mutationTestStock($product, $warehouse))->toBe(2);
});

it('takes the stock back out when an unused completed internal receipt is deleted', function () {
    mutationTestLogin('transactions.internal-receipts.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();

    $this->post(route('transactions.internal-receipts.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 8],
    ], 'completed', ['to_warehouse_id' => $warehouse->id]));
    $receipt = InternalMutation::firstOrFail();

    $this->delete(route('transactions.internal-receipts.destroy', $receipt))->assertRedirect();

    expect(InternalMutation::count())->toBe(0)
        ->and(mutationTestStock($product, $warehouse))->toBe(0);
});

/*
 * Internal expenditures: stock leaves a warehouse for internal use
 */
it('takes stock out when an internal expenditure is completed', function () {
    mutationTestLogin('transactions.internal-expenditures.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 10);

    $this->post(route('transactions.internal-expenditures.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 4],
    ], 'completed', ['from_warehouse_id' => $warehouse->id]))
        ->assertRedirect(route('transactions.internal-expenditures.index'));

    $expenditure = InternalMutation::firstOrFail();
    $entry = InventoryLedger::where('source_type', $expenditure->getMorphClass())->firstOrFail();

    expect(mutationTestStock($product, $warehouse))->toBe(6)
        ->and($entry->type)->toBe('OUT')
        ->and($entry->qty)->toBe(-4);
});

it('refuses a completed internal expenditure larger than the stock', function () {
    mutationTestLogin('transactions.internal-expenditures.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 2);

    $this->post(route('transactions.internal-expenditures.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 3],
    ], 'completed', ['from_warehouse_id' => $warehouse->id]))->assertSessionHasErrors('items');

    expect(InternalMutation::count())->toBe(0)
        ->and(mutationTestStock($product, $warehouse))->toBe(2);
});

it('puts the stock back when a completed internal expenditure is deleted', function () {
    mutationTestLogin('transactions.internal-expenditures.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 10);

    $this->post(route('transactions.internal-expenditures.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 4],
    ], 'completed', ['from_warehouse_id' => $warehouse->id]));
    $expenditure = InternalMutation::firstOrFail();

    $this->delete(route('transactions.internal-expenditures.destroy', $expenditure))->assertRedirect();

    expect(InternalMutation::count())->toBe(0)
        ->and(mutationTestStock($product, $warehouse))->toBe(10);
});

/*
 * Deviations: stock-opname differences in one warehouse. Quantities are
 * signed: positive = overage (stock found above the system figure),
 * negative = shortage.
 */
it('adds stock for an overage and removes it for a shortage when a deviation is completed', function () {
    mutationTestLogin('transactions.deviations.manage');
    $over = mutationTestProduct('P-OVER');
    $short = mutationTestProduct('P-SHORT');
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($short, $warehouse, 10);

    $this->post(route('transactions.deviations.store'), mutationTestPayload([
        ['product_id' => $over->id, 'qty' => 3],
        ['product_id' => $short->id, 'qty' => -4],
    ], 'completed', ['warehouse_id' => $warehouse->id]))
        ->assertRedirect(route('transactions.deviations.index'));

    $deviation = InternalMutation::firstOrFail();
    $types = InventoryLedger::where('source_type', $deviation->getMorphClass())
        ->orderBy('product_id')->pluck('type', 'product_id')->all();

    expect(mutationTestStock($over, $warehouse))->toBe(3)
        ->and(mutationTestStock($short, $warehouse))->toBe(6)
        ->and($types)->toBe([$over->id => 'IN', $short->id => 'OUT']);
});

it('does not touch stock for a pending deviation', function () {
    mutationTestLogin('transactions.deviations.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 10);

    $this->post(route('transactions.deviations.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => -4],
    ], 'pending', ['warehouse_id' => $warehouse->id]))->assertRedirect();

    expect(InternalMutation::count())->toBe(1)
        ->and(mutationTestStock($product, $warehouse))->toBe(10);
});

it('refuses a shortage larger than the stock in the warehouse', function () {
    mutationTestLogin('transactions.deviations.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 2);

    $this->post(route('transactions.deviations.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => -5],
    ], 'completed', ['warehouse_id' => $warehouse->id]))->assertSessionHasErrors('items');

    expect(InternalMutation::count())->toBe(0)
        ->and(mutationTestStock($product, $warehouse))->toBe(2);
});

it('changes stock by the difference when a completed deviation is edited, even from shortage to overage', function () {
    mutationTestLogin('transactions.deviations.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 10);
    $extra = ['warehouse_id' => $warehouse->id];

    $this->post(route('transactions.deviations.store'), mutationTestPayload([['product_id' => $product->id, 'qty' => -4]], 'completed', $extra));
    $deviation = InternalMutation::firstOrFail();
    expect(mutationTestStock($product, $warehouse))->toBe(6);

    // -4 -> -6: 2 more go out
    $this->put(route('transactions.deviations.update', $deviation), mutationTestPayload([['product_id' => $product->id, 'qty' => -6]], 'completed', $extra))->assertRedirect();
    expect(mutationTestStock($product, $warehouse))->toBe(4);

    // -6 -> +3: the shortage is undone (6 back) and 3 more are added
    $this->put(route('transactions.deviations.update', $deviation), mutationTestPayload([['product_id' => $product->id, 'qty' => 3]], 'completed', $extra))->assertRedirect();
    expect(mutationTestStock($product, $warehouse))->toBe(13);
});

it('undoes a completed deviation when it is deleted', function () {
    mutationTestLogin('transactions.deviations.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();
    mutationTestSeed($product, $warehouse, 10);

    $this->post(route('transactions.deviations.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => -4],
    ], 'completed', ['warehouse_id' => $warehouse->id]));
    $deviation = InternalMutation::firstOrFail();

    $this->delete(route('transactions.deviations.destroy', $deviation))->assertRedirect();

    expect(InternalMutation::count())->toBe(0)
        ->and(mutationTestStock($product, $warehouse))->toBe(10);
});

it('refuses to delete a completed overage whose stock was already used', function () {
    mutationTestLogin('transactions.deviations.manage');
    $product = mutationTestProduct();
    $warehouse = mutationTestWarehouse();

    $this->post(route('transactions.deviations.store'), mutationTestPayload([
        ['product_id' => $product->id, 'qty' => 5],
    ], 'completed', ['warehouse_id' => $warehouse->id]));
    $deviation = InternalMutation::firstOrFail();
    app(StockService::class)->decrease($product->id, $warehouse->id, 4, 'INV-001');

    $this->delete(route('transactions.deviations.destroy', $deviation))->assertSessionHas('error');

    expect(InternalMutation::count())->toBe(1)
        ->and(mutationTestStock($product, $warehouse))->toBe(1);
});
