<?php

use App\Models\Customer;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
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
 * Helper names are prefixed "returnTest" so they cannot clash with helpers in
 * other test files (Pest loads them all into one process).
 */
function returnTestLogin(string $permission): User
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

function returnTestProduct(string $code = 'P-001'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function returnTestWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

function returnTestStock(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

/** A purchase whose stock has already been booked, like one saved through the purchase page. */
function returnTestPurchase(Warehouse $warehouse, Product $product, int $qty, string $status = 'received', string $invoice = 'PB-001'): Purchase
{
    $supplier = Supplier::firstOrCreate(['code' => 'SUP-1'], ['name' => 'Supplier 1']);

    $purchase = Purchase::create([
        'invoice_number' => $invoice,
        'purchase_date' => '2026-09-30',
        'total_amount' => 0,
        'status' => $status,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $purchase->purchaseDetails()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => 1000]);

    if ($status === 'received') {
        app(StockService::class)->increase($product->id, $warehouse->id, $qty, $invoice, $purchase);
    }

    return $purchase;
}

/** A sale whose stock has already been booked (stock is added first so it can go out). */
function returnTestSale(Warehouse $warehouse, Product $product, int $qty, string $invoice = 'INV-001'): Sale
{
    $customer = Customer::firstOrCreate(['code' => 'CUST-1'], ['name' => 'Pelanggan 1']);

    app(StockService::class)->increase($product->id, $warehouse->id, $qty, 'PB-SEED');

    $sale = Sale::create([
        'invoice_number' => $invoice,
        'sale_date' => '2026-09-30',
        'total_amount' => 0,
        'source' => 'sales',
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $sale->saleDetails()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => 1000]);
    app(StockService::class)->decrease($product->id, $warehouse->id, $qty, $invoice, $sale);

    return $sale;
}

/** @param array<int, array{product_id: int, qty: int}> $items */
function returnTestPurchasePayload(Purchase $purchase, array $items, string $number = 'RB-001'): array
{
    return [
        'return_number' => $number,
        'return_date' => '2026-09-30',
        'total_amount' => 0,
        'purchase_id' => $purchase->id,
        'items' => $items,
    ];
}

/** @param array<int, array{product_id: int, qty: int}> $items */
function returnTestSalePayload(Sale $sale, array $items, string $number = 'RJ-001'): array
{
    return [
        'return_number' => $number,
        'return_date' => '2026-09-30',
        'total_amount' => 0,
        'sale_id' => $sale->id,
        'items' => $items,
    ];
}

/*
 * Purchase returns: goods go back to the supplier, so stock leaves the
 * warehouse the purchase was received into.
 */
it('takes stock out of the purchase warehouse when goods are returned to the supplier', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10);

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 4],
    ]))->assertRedirect(route('transactions.purchase-returns.index'));

    $return = PurchaseReturn::firstOrFail();
    $entry = InventoryLedger::where('source_type', $return->getMorphClass())->firstOrFail();

    expect(returnTestStock($product, $warehouse))->toBe(6)
        ->and($entry->type)->toBe('OUT')
        ->and($entry->qty)->toBe(-4)
        ->and($entry->warehouse_id)->toBe($warehouse->id)
        ->and($entry->reference_number)->toBe('RB-001');
});

it('refuses a purchase return when part of the stock was already sold', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10);
    app(StockService::class)->decrease($product->id, $warehouse->id, 8, 'INV-001');

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 5],
    ]))->assertSessionHasErrors('items');

    expect(PurchaseReturn::count())->toBe(0)
        ->and(returnTestStock($product, $warehouse))->toBe(2);
});

it('refuses to return more than was bought minus earlier returns', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10);

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 6],
    ], 'RB-001'))->assertRedirect();

    // 10 bought, 6 already returned: only 4 left to return
    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 5],
    ], 'RB-002'))->assertSessionHasErrors('items');

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 4],
    ], 'RB-003'))->assertRedirect();

    expect(PurchaseReturn::count())->toBe(2)
        ->and(returnTestStock($product, $warehouse))->toBe(0);
});

it('adds up the same product on two lines of a purchase return', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10);

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 6],
        ['product_id' => $product->id, 'qty' => 6],
    ]))->assertSessionHasErrors('items');

    expect(PurchaseReturn::count())->toBe(0)
        ->and(returnTestStock($product, $warehouse))->toBe(10);
});

it('refuses to return a product that is not on the purchase', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $bought = returnTestProduct('P-BOUGHT');
    $other = returnTestProduct('P-OTHER');
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $bought, 10);

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $other->id, 'qty' => 1],
    ]))->assertSessionHasErrors('items');

    expect(PurchaseReturn::count())->toBe(0);
});

it('refuses a return against a purchase that was never received', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10, 'pending');

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 1],
    ]))->assertSessionHasErrors('purchase_id');

    expect(PurchaseReturn::count())->toBe(0)
        ->and(InventoryLedger::count())->toBe(0);
});

it('takes only the difference when a purchase return is edited, and does not count itself as an earlier return', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10);

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 6],
    ]));
    $return = PurchaseReturn::firstOrFail();
    expect(returnTestStock($product, $warehouse))->toBe(4);

    // 6 -> 9: 9 is within the 10 bought only because this return is not counted twice
    $this->put(route('transactions.purchase-returns.update', $return), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 9],
    ]))->assertRedirect();
    expect(returnTestStock($product, $warehouse))->toBe(1);

    // 9 -> 2 gives 7 back
    $this->put(route('transactions.purchase-returns.update', $return), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 2],
    ]))->assertRedirect();
    expect(returnTestStock($product, $warehouse))->toBe(8);
});

it('puts the stock back when a purchase return is deleted', function () {
    returnTestLogin('transactions.purchase-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $purchase = returnTestPurchase($warehouse, $product, 10);

    $this->post(route('transactions.purchase-returns.store'), returnTestPurchasePayload($purchase, [
        ['product_id' => $product->id, 'qty' => 4],
    ]));
    $return = PurchaseReturn::firstOrFail();

    $this->delete(route('transactions.purchase-returns.destroy', $return))->assertRedirect();

    expect(PurchaseReturn::count())->toBe(0)
        ->and(returnTestStock($product, $warehouse))->toBe(10);
});

/*
 * Sales returns: goods come back from the customer, so stock goes into the
 * warehouse the sale was taken from.
 */
it('puts stock back into the sale warehouse when a customer returns goods', function () {
    returnTestLogin('transactions.sales-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse('WH-A');
    $other = returnTestWarehouse('WH-B');
    $sale = returnTestSale($warehouse, $product, 10);
    expect(returnTestStock($product, $warehouse))->toBe(0);

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 3],
    ]))->assertRedirect(route('transactions.sales-returns.index'));

    $return = SalesReturn::firstOrFail();
    $entry = InventoryLedger::where('source_type', $return->getMorphClass())->firstOrFail();

    expect(returnTestStock($product, $warehouse))->toBe(3)
        ->and(returnTestStock($product, $other))->toBe(0)
        ->and($entry->type)->toBe('IN')
        ->and($entry->qty)->toBe(3)
        ->and($entry->reference_number)->toBe('RJ-001');
});

it('refuses to accept back more than was sold minus earlier returns', function () {
    returnTestLogin('transactions.sales-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $sale = returnTestSale($warehouse, $product, 10);

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 6],
    ], 'RJ-001'))->assertRedirect();

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 5],
    ], 'RJ-002'))->assertSessionHasErrors('items');

    expect(SalesReturn::count())->toBe(1)
        ->and(returnTestStock($product, $warehouse))->toBe(6);
});

it('refuses a sales return for a product that was not on the sale', function () {
    returnTestLogin('transactions.sales-returns.manage');
    $sold = returnTestProduct('P-SOLD');
    $other = returnTestProduct('P-OTHER');
    $warehouse = returnTestWarehouse();
    $sale = returnTestSale($warehouse, $sold, 10);
    $rowsBefore = InventoryLedger::count();

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $other->id, 'qty' => 1],
    ]))->assertSessionHasErrors('items');

    expect(SalesReturn::count())->toBe(0)
        ->and(InventoryLedger::count())->toBe($rowsBefore);
});

it('changes stock by the difference when a sales return is edited', function () {
    returnTestLogin('transactions.sales-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $sale = returnTestSale($warehouse, $product, 10);

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 3],
    ]));
    $return = SalesReturn::firstOrFail();

    // 3 -> 8: only 5 more come back, and 8 is within the 10 sold
    $this->put(route('transactions.sales-returns.update', $return), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 8],
    ]))->assertRedirect();
    expect(returnTestStock($product, $warehouse))->toBe(8);

    // 8 -> 1 takes 7 back out
    $this->put(route('transactions.sales-returns.update', $return), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 1],
    ]))->assertRedirect();
    expect(returnTestStock($product, $warehouse))->toBe(1);
});

it('takes the stock back out when a sales return is deleted', function () {
    returnTestLogin('transactions.sales-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $sale = returnTestSale($warehouse, $product, 10);

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 4],
    ]));
    $return = SalesReturn::firstOrFail();
    expect(returnTestStock($product, $warehouse))->toBe(4);

    $this->delete(route('transactions.sales-returns.destroy', $return))->assertRedirect();

    expect(SalesReturn::count())->toBe(0)
        ->and(returnTestStock($product, $warehouse))->toBe(0);
});

it('refuses to delete a sales return whose goods were already sold again', function () {
    returnTestLogin('transactions.sales-returns.manage');
    $product = returnTestProduct();
    $warehouse = returnTestWarehouse();
    $sale = returnTestSale($warehouse, $product, 10);

    $this->post(route('transactions.sales-returns.store'), returnTestSalePayload($sale, [
        ['product_id' => $product->id, 'qty' => 4],
    ]));
    $return = SalesReturn::firstOrFail();
    app(StockService::class)->decrease($product->id, $warehouse->id, 4, 'INV-002');

    $this->delete(route('transactions.sales-returns.destroy', $return))->assertSessionHas('error');

    expect(SalesReturn::count())->toBe(1)
        ->and(returnTestStock($product, $warehouse))->toBe(0);
});
