<?php

use App\Models\Customer;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

/*
 * Helper names are prefixed "saleTest" so they cannot clash with helpers in
 * other test files (Pest loads them all into one process).
 */
function saleTestLogin(string $permission): User
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

function saleTestProduct(string $code = 'P-001'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function saleTestWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

function saleTestCustomer(): Customer
{
    return Customer::firstOrCreate(['code' => 'CUST-1'], ['name' => 'Pelanggan 1']);
}

/** Put stock in a warehouse the way a received purchase would. */
function saleTestStock(Product $product, Warehouse $warehouse, int $qty): void
{
    app(StockService::class)->increase($product->id, $warehouse->id, $qty, 'PB-SEED');
}

function saleTestAvailable(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

/**
 * @param  array<int, array{product_id: int, qty: int, price?: int}>  $items
 */
function saleTestPayload(Warehouse $warehouse, array $items, string $invoice = 'INV-001'): array
{
    return [
        'invoice_number' => $invoice,
        'sale_date' => '2026-09-30',
        'customer_id' => saleTestCustomer()->id,
        'warehouse_id' => $warehouse->id,
        'items' => array_map(fn ($item) => $item + ['price' => 1000], $items),
    ];
}

/*
 * The three pages with a full create / edit / delete cycle. They all share
 * the `sales` table and must move stock the same way.
 */
dataset('sale pages', [
    'sales' => [[
        'permission' => 'transactions.sales.manage',
        'store' => 'transactions.sales.store',
        'update' => 'transactions.sales.update',
        'destroy' => 'transactions.sales.destroy',
    ]],
    'point of sale' => [[
        'permission' => 'transactions.point-of-sale.manage',
        'store' => 'transactions.point-of-sale.store',
        'update' => 'transactions.point-of-sale.update',
        'destroy' => 'transactions.point-of-sale.destroy',
    ]],
    'sales spg' => [[
        'permission' => 'transactions.sales-spg.manage',
        'store' => 'transactions.sales-spg.store',
        'update' => 'transactions.sales-spg.update',
        'destroy' => 'transactions.sales-spg.destroy',
    ]],
]);

it('takes stock out of the chosen warehouse when a sale is saved', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route($page['store']), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 4],
    ]))->assertRedirect();

    $sale = Sale::firstOrFail();
    $entry = InventoryLedger::where('source_type', $sale->getMorphClass())->firstOrFail();

    expect(saleTestAvailable($product, $warehouse))->toBe(6)
        ->and($entry->type)->toBe('OUT')
        ->and($entry->qty)->toBe(-4)
        ->and($entry->reference_number)->toBe('INV-001')
        ->and($entry->source_id)->toBe($sale->id);
})->with('sale pages');

it('blocks a sale larger than the stock and saves nothing', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 3);
    $rowsBefore = InventoryLedger::count();

    $this->post(route($page['store']), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 4],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(InventoryLedger::count())->toBe($rowsBefore)
        ->and(saleTestAvailable($product, $warehouse))->toBe(3);
})->with('sale pages');

it('checks stock in the chosen warehouse only', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $stocked = saleTestWarehouse('WH-A');
    $empty = saleTestWarehouse('WH-B');
    saleTestStock($product, $stocked, 10);

    $this->post(route($page['store']), saleTestPayload($empty, [
        ['product_id' => $product->id, 'qty' => 1],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(saleTestAvailable($product, $stocked))->toBe(10);
})->with('sale pages');

it('adds up a paid line and a free line of the same product before checking stock', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    // 6 paid + 6 free = 12 > 10, even though each line alone would fit.
    $this->post(route($page['store']), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 6, 'price' => 1000],
        ['product_id' => $product->id, 'qty' => 6, 'price' => 0],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(saleTestAvailable($product, $warehouse))->toBe(10);

    // 6 paid + 4 free = 10 fits; both lines are saved, stock goes out once.
    $this->post(route($page['store']), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 6, 'price' => 1000],
        ['product_id' => $product->id, 'qty' => 4, 'price' => 0],
    ], 'INV-002'))->assertRedirect();

    $sale = Sale::firstOrFail();
    expect($sale->saleDetails()->count())->toBe(2)
        ->and(saleTestAvailable($product, $warehouse))->toBe(0)
        ->and(InventoryLedger::where('source_id', $sale->id)->count())->toBe(1);
})->with('sale pages');

it('gives stock back when a sale is edited to a smaller quantity', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route($page['store']), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 6]]));
    $sale = Sale::firstOrFail();

    $this->put(route($page['update'], $sale), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 2]]))
        ->assertRedirect();

    expect(saleTestAvailable($product, $warehouse))->toBe(8);
})->with('sale pages');

it('takes more stock out when a sale is edited to a bigger quantity that still fits', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route($page['store']), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 4]]));
    $sale = Sale::firstOrFail();

    $this->put(route($page['update'], $sale), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 9]]))
        ->assertRedirect();

    expect(saleTestAvailable($product, $warehouse))->toBe(1);
})->with('sale pages');

it('refuses an edit to a quantity that stock cannot cover and keeps the sale as it was', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route($page['store']), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 4]]));
    $sale = Sale::firstOrFail();
    $rowsBefore = InventoryLedger::count();

    // only 6 are left; going from 4 to 12 needs 8 more
    $this->put(route($page['update'], $sale), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 12]]))
        ->assertSessionHasErrors('items');

    expect(InventoryLedger::count())->toBe($rowsBefore)
        ->and(saleTestAvailable($product, $warehouse))->toBe(6)
        ->and($sale->fresh()->saleDetails()->first()->qty)->toBe(4);
})->with('sale pages');

it('writes no new ledger rows when an edit does not change stock', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route($page['store']), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 4, 'price' => 1000]]));
    $sale = Sale::firstOrFail();
    $rowsBefore = InventoryLedger::count();

    // only the price changes
    $this->put(route($page['update'], $sale), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 4, 'price' => 2500]]))
        ->assertRedirect();

    expect(InventoryLedger::count())->toBe($rowsBefore);
})->with('sale pages');

it('moves the stock effect when the warehouse of a sale changes', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $a = saleTestWarehouse('WH-A');
    $b = saleTestWarehouse('WH-B');
    saleTestStock($product, $a, 10);
    saleTestStock($product, $b, 10);
    $items = [['product_id' => $product->id, 'qty' => 4]];

    $this->post(route($page['store']), saleTestPayload($a, $items));
    $sale = Sale::firstOrFail();

    $this->put(route($page['update'], $sale), saleTestPayload($b, $items))->assertRedirect();

    expect(saleTestAvailable($product, $a))->toBe(10)
        ->and(saleTestAvailable($product, $b))->toBe(6);
})->with('sale pages');

it('puts the stock back when a sale is deleted', function (array $page) {
    saleTestLogin($page['permission']);
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route($page['store']), saleTestPayload($warehouse, [['product_id' => $product->id, 'qty' => 4]]));
    $sale = Sale::firstOrFail();
    expect(saleTestAvailable($product, $warehouse))->toBe(6);

    $this->delete(route($page['destroy'], $sale))->assertRedirect();

    expect(Sale::count())->toBe(0)
        ->and(saleTestAvailable($product, $warehouse))->toBe(10);
})->with('sale pages');

/*
 * The point-of-sale terminal is create-only.
 */
it('takes stock out from the point-of-sale terminal', function () {
    saleTestLogin('transactions.point-of-sale-new.manage');
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route('transactions.point-of-sale-new.store'), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 3],
    ]))->assertRedirect(route('transactions.point-of-sale-new.index'));

    expect(saleTestAvailable($product, $warehouse))->toBe(7);
});

it('blocks a terminal sale larger than the stock', function () {
    saleTestLogin('transactions.point-of-sale-new.manage');
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 2);

    $this->post(route('transactions.point-of-sale-new.store'), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 3],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(saleTestAvailable($product, $warehouse))->toBe(2);
});

it('adds up a paid and a free line of the same product on the terminal', function () {
    saleTestLogin('transactions.point-of-sale-new.manage');
    $product = saleTestProduct();
    $warehouse = saleTestWarehouse();
    saleTestStock($product, $warehouse, 10);

    $this->post(route('transactions.point-of-sale-new.store'), saleTestPayload($warehouse, [
        ['product_id' => $product->id, 'qty' => 6, 'price' => 1000],
        ['product_id' => $product->id, 'qty' => 6, 'price' => 0],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(saleTestAvailable($product, $warehouse))->toBe(10);
});
