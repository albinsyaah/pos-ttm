<?php

use App\Models\Customer;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockBackfillService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

/* Helper names start with "aw" so they cannot clash with other test files. */

function awLogin(array $permissions): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'aw-'.uniqid(),
        'password' => Hash::make('password'),
        'role' => 'Staff',
        'is_active' => true,
    ]);

    foreach ($permissions as $name) {
        $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    test()->actingAs($user);

    return $user;
}

function awWarehouse(string $name, int $stockOf = 0, ?Product $product = null): Warehouse
{
    $warehouse = Warehouse::create(['name' => $name]);

    if ($product !== null && $stockOf > 0) {
        app(StockService::class)->increase($product->id, $warehouse->id, $stockOf, 'SEED-'.uniqid());
    }

    return $warehouse;
}

function awStock(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

/** Ring up a sale on the cashier terminal; no warehouse is named. */
function awSell(array $lines, string $date = '2026-10-01', array $extra = [])
{
    return test()->post(route('transactions.point-of-sale-new.store'), array_merge([
        'sale_date' => $date,
        'items' => array_map(fn (array $l) => $l + ['price' => 1000], $lines),
    ], $extra));
}

function awSale(): Sale
{
    return Sale::orderByDesc('id')->firstOrFail();
}

// ---- Which warehouse the goods come from -----------------------------------------------------------

it('takes the goods from the warehouse with the most stock first, then the next', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Pupuk Urea']);
    $small = awWarehouse('Gudang Kecil', 5, $product);
    $big = awWarehouse('Gudang Besar', 8, $product);

    awSell([['product_id' => $product->id, 'qty' => 10]])->assertSessionHasNoErrors()->assertRedirect();

    // 8 from the big one, the missing 2 from the small one.
    expect(awStock($product, $big))->toBe(0)
        ->and(awStock($product, $small))->toBe(3)
        ->and(awSale()->warehouse_id)->toBeNull();
});

it('stays in one warehouse when it can supply the whole sale', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Benih Padi']);
    $small = awWarehouse('Gudang A', 4, $product);
    $big = awWarehouse('Gudang B', 20, $product);

    awSell([['product_id' => $product->id, 'qty' => 6]])->assertSessionHasNoErrors();

    expect(awStock($product, $big))->toBe(14)->and(awStock($product, $small))->toBe(4);
});

it('takes from the warehouse with the lower id when two have the same stock', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Cangkul']);
    $first = awWarehouse('Gudang Satu', 5, $product);
    $second = awWarehouse('Gudang Dua', 5, $product);

    awSell([['product_id' => $product->id, 'qty' => 3]])->assertSessionHasNoErrors();

    expect(awStock($product, $first))->toBe(2)->and(awStock($product, $second))->toBe(5);
});

it('decides the warehouse per product', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $urea = Product::create(['name' => 'Urea']);
    $npk = Product::create(['name' => 'NPK']);
    $w1 = awWarehouse('Gudang 1', 10, $urea);
    $w2 = awWarehouse('Gudang 2', 10, $npk);
    app(StockService::class)->increase($npk->id, $w1->id, 2, 'SEED-X');

    awSell([['product_id' => $urea->id, 'qty' => 4], ['product_id' => $npk->id, 'qty' => 4]])->assertSessionHasNoErrors();

    expect(awStock($urea, $w1))->toBe(6)
        ->and(awStock($npk, $w2))->toBe(6)
        ->and(awStock($npk, $w1))->toBe(2);
});

it('adds up a paid and a free line of the same product before spreading it', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Pestisida']);
    $a = awWarehouse('Gudang A', 6, $product);
    $b = awWarehouse('Gudang B', 5, $product);

    awSell([
        ['product_id' => $product->id, 'qty' => 7, 'price' => 1000],
        ['product_id' => $product->id, 'qty' => 2, 'price' => 0],
    ])->assertSessionHasNoErrors();

    // 9 in all: 6 from A (most stock), 3 from B.
    expect(awStock($product, $a))->toBe(0)->and(awStock($product, $b))->toBe(2);
});

it('records every warehouse it took from in the stock ledger under the invoice number', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Pupuk Urea']);
    awWarehouse('Gudang Kecil', 5, $product);
    awWarehouse('Gudang Besar', 8, $product);

    awSell([['product_id' => $product->id, 'qty' => 10]]);

    $sale = awSale();
    $rows = InventoryLedger::where('reference_number', $sale->invoice_number)->orderBy('warehouse_id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('qty')->map(fn ($q) => (int) $q)->sort()->values()->all())->toBe([-8, -2])
        ->and($rows->every(fn ($r) => $r->source_id === $sale->id))->toBeTrue();
});

// ---- Not enough stock ----------------------------------------------------------------------------------

it('refuses a sale larger than all warehouses together and writes nothing', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Pupuk Urea']);
    $a = awWarehouse('Gudang A', 4, $product);
    $b = awWarehouse('Gudang B', 3, $product);

    awSell([['product_id' => $product->id, 'qty' => 8]])->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(awStock($product, $a))->toBe(4)
        ->and(awStock($product, $b))->toBe(3);

    $message = session('errors')->first('items');
    expect($message)->toContain('semua gudang')->toContain('7')->toContain('8');
});

it('refuses the whole sale when only one of its products is short', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $ok = Product::create(['name' => 'Cukup']);
    $short = Product::create(['name' => 'Kurang']);
    $w = awWarehouse('Gudang A', 10, $ok);
    app(StockService::class)->increase($short->id, $w->id, 1, 'SEED-S');

    awSell([['product_id' => $ok->id, 'qty' => 5], ['product_id' => $short->id, 'qty' => 2]])->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)->and(awStock($ok, $w))->toBe(10);
});

it('works for the head cashier terminal too', function () {
    awLogin(['transactions.point-of-sale-induk.manage']);
    $product = Product::create(['name' => 'Pupuk Urea']);
    $a = awWarehouse('Gudang A', 3, $product);
    $b = awWarehouse('Gudang B', 9, $product);

    $this->post(route('transactions.point-of-sale-induk.store'), [
        'sale_date' => '2026-10-01',
        'driver_name' => 'Pak Joko',
        'items' => [['product_id' => $product->id, 'qty' => 11, 'price' => 1000]],
    ])->assertSessionHasNoErrors();

    expect(awStock($product, $b))->toBe(0)->and(awStock($product, $a))->toBe(1)
        ->and(awSale()->warehouse_id)->toBeNull()
        ->and(awSale()->driver_name)->toBe('Pak Joko');
});

// ---- The terminal page and search --------------------------------------------------------------------------

it('has no warehouse choice on the terminal page and says the stock is the total', function () {
    awLogin(['transactions.point-of-sale-new.view']);
    awWarehouse('Gudang A');

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertDontSee('id="warehouse_id"', false)
        ->assertSee('total dari semua gudang');
});

it('shows the total stock of all warehouses in the search and in a refused cart', function () {
    awLogin(['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Pupuk Urea']);
    awWarehouse('Gudang A', 12, $product);
    awWarehouse('Gudang B', 30, $product);

    $this->getJson(route('transactions.point-of-sale-new.products', ['q' => 'urea']))
        ->assertOk()
        ->assertJsonPath('data.0.stock', 42);

    $this->from(route('transactions.point-of-sale-new.index'))
        ->post(route('transactions.point-of-sale-new.store'), [
            'sale_date' => '2026-10-01',
            'items' => [['product_id' => $product->id, 'qty' => 50, 'price' => 1000]],
        ])->assertSessionHasErrors('items');

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertViewHas('cartSeed', fn ($seed) => count($seed) === 1 && $seed[0]['stock'] === 42);
});

// ---- Returns go back where the goods came from -----------------------------------------------------------

function awReturn(Sale $sale, Product $product, int $qty, ?SalesReturn $edit = null)
{
    $payload = [
        'return_date' => '2026-10-02',
        'total_amount' => 0,
        'sale_id' => $sale->id,
        'items' => [['product_id' => $product->id, 'qty' => $qty]],
    ];

    return $edit
        ? test()->put(route('transactions.sales-returns.update', $edit), $payload)
        : test()->post(route('transactions.sales-returns.store'), $payload);
}

/** A cashier sale of 10 taken as 8 from "Besar" and 2 from "Kecil" (3 left in Kecil). */
function awSplitSale(): array
{
    awLogin(['transactions.point-of-sale-new.manage', 'transactions.sales-returns.view', 'transactions.sales-returns.manage', 'transactions.point-of-sale.view', 'transactions.point-of-sale.manage']);
    $product = Product::create(['name' => 'Pupuk Urea']);
    $small = awWarehouse('Gudang Kecil', 5, $product);
    $big = awWarehouse('Gudang Besar', 8, $product);

    awSell([['product_id' => $product->id, 'qty' => 10]])->assertSessionHasNoErrors();

    return [awSale(), $product, $small, $big];
}

it('puts returned goods back into the warehouse the sale took most from', function () {
    [$sale, $product, $small, $big] = awSplitSale();

    awReturn($sale, $product, 5)->assertSessionHasNoErrors();

    expect(awStock($product, $big))->toBe(5)->and(awStock($product, $small))->toBe(3);
});

it('fills what each warehouse gave up before using the next one, over several returns', function () {
    [$sale, $product, $small, $big] = awSplitSale();

    awReturn($sale, $product, 5);
    awReturn($sale, $product, 5)->assertSessionHasNoErrors();

    // Big gave 8 and Small gave 2: all of it is back where it came from.
    expect(awStock($product, $big))->toBe(8)->and(awStock($product, $small))->toBe(5);
});

it('moves returned goods when a return is edited and takes them out when it is deleted', function () {
    [$sale, $product, $small, $big] = awSplitSale();

    awReturn($sale, $product, 5);
    $return = SalesReturn::firstOrFail();

    awReturn($sale, $product, 2, $return)->assertSessionHasNoErrors();
    expect(awStock($product, $big))->toBe(2)->and(awStock($product, $small))->toBe(3);

    $this->delete(route('transactions.sales-returns.destroy', $return))->assertSessionHasNoErrors();
    expect(awStock($product, $big))->toBe(0)->and(awStock($product, $small))->toBe(3);
});

it('refuses a return for more than was sold, as before', function () {
    [$sale, $product] = awSplitSale();

    awReturn($sale, $product, 11)->assertSessionHasErrors('items');

    expect(SalesReturn::count())->toBe(0);
});

// ---- Changing or removing the sale on the Point of Sale page ------------------------------------------------

it('puts the stock back into each warehouse when the sale is deleted', function () {
    [$sale, $product, $small, $big] = awSplitSale();

    $this->delete(route('transactions.point-of-sale.destroy', $sale))->assertSessionHasNoErrors();

    expect(awStock($product, $big))->toBe(8)->and(awStock($product, $small))->toBe(5)
        ->and(Sale::count())->toBe(0);
});

it('moves the stock to the chosen warehouse when the sale is edited on the Point of Sale page', function () {
    [$sale, $product, $small, $big] = awSplitSale();

    $this->put(route('transactions.point-of-sale.update', $sale), [
        'invoice_number' => $sale->invoice_number,
        'sale_date' => '2026-10-01',
        'total_amount' => 3000,
        'warehouse_id' => $big->id,
        'items' => [['product_id' => $product->id, 'qty' => 3, 'price' => 1000]],
    ])->assertSessionHasNoErrors();

    // Everything it took is released, then 3 are taken from the chosen warehouse.
    expect(awStock($product, $big))->toBe(5)->and(awStock($product, $small))->toBe(5)
        ->and($sale->fresh()->warehouse_id)->toBe($big->id);
});

it('shows "all warehouses" for such a sale in the lists', function () {
    [$sale] = awSplitSale();

    $this->get(route('transactions.point-of-sale.index'))->assertOk()->assertSee('Semua gudang');
});

// ---- Delivery note ---------------------------------------------------------------------------------------------------

it('lists on the delivery note which warehouse each item was taken from', function () {
    [$sale, $product] = awSplitSale();
    awLogin(['print.delivery-note']);

    $response = $this->get(route('transactions.sales.print.delivery-note', $sale))->assertOk();

    $response->assertSee('Pengambilan barang per gudang')
        ->assertSee('Gudang Besar')
        ->assertSee('Gudang Kecil');

    // Header names every warehouse involved, in alphabetical order.
    $response->assertSeeInOrder(['Dari gudang', 'Gudang Besar, Gudang Kecil']);
    // Quantities per warehouse: 8 from Besar, 2 from Kecil.
    $html = $response->getContent();
    expect($html)->toMatch('/Gudang Besar<\/strong><\/td>\s*<td>Pupuk Urea<\/td>\s*<td>8 pcs<\/td>/')
        ->and($html)->toMatch('/Gudang Kecil<\/strong><\/td>\s*<td>Pupuk Urea<\/td>\s*<td>2 pcs<\/td>/');
});

it('keeps the delivery note of a sale with its own warehouse as it was', function () {
    awLogin(['print.delivery-note']);
    $product = Product::create(['name' => 'Benih']);
    $warehouse = awWarehouse('Gudang Induk');
    $sale = Sale::create([
        'sale_date' => '2026-10-01', 'total_amount' => 1000, 'source' => 'sales',
        'warehouse_id' => $warehouse->id, 'customer_id' => Customer::create(['name' => 'Toko A'])->id,
    ]);
    $sale->saleDetails()->create(['product_id' => $product->id, 'qty' => 2, 'price' => 500]);

    $this->get(route('transactions.sales.print.delivery-note', $sale))
        ->assertOk()
        ->assertSee('Gudang Induk')
        ->assertDontSee('Pengambilan barang per gudang');
});

// ---- Reports and backfill ------------------------------------------------------------------------------------------------

it('finds a cashier sale under every warehouse it took goods from in the sales summary', function () {
    [$sale, $product, $small, $big] = awSplitSale();
    $other = awWarehouse('Gudang Lain');
    awLogin(['reports.sales-summary.view']);

    $listed = fn (Warehouse $w) => $this->get(route('reports.sales-summary', ['warehouse_id' => $w->id]))->assertOk()->getContent();

    expect($listed($big))->toContain($sale->invoice_number)
        ->and($listed($small))->toContain($sale->invoice_number)
        ->and($listed($other))->not->toContain($sale->invoice_number);
});

it('filters the sales by product report by the warehouse the product came from', function () {
    [$sale, $product, $small, $big] = awSplitSale();
    $other = awWarehouse('Gudang Lain');
    awLogin(['reports.sales-by-product.view']);

    $count = fn (Warehouse $w) => $this->get(route('reports.sales-by-product', ['warehouse_id' => $w->id]))->assertOk()->viewData('rows')->total();

    expect($count($big))->toBe(1)->and($count($small))->toBe(1)->and($count($other))->toBe(0);
});

it('leaves a cashier sale alone when stock is backfilled', function () {
    [$sale, $product, $small, $big] = awSplitSale();

    $report = app(StockBackfillService::class)->run([StockBackfillService::STEP_LINK, StockBackfillService::STEP_CREATE], true);

    expect(awStock($product, $big))->toBe(0)->and(awStock($product, $small))->toBe(3)
        ->and(InventoryLedger::where('source_id', $sale->id)->where('source_type', $sale->getMorphClass())->count())->toBe(2)
        ->and(json_encode($report))->not->toContain($sale->invoice_number);
});

// ---- The stock service itself ---------------------------------------------------------------------------------------------------

it('reports total stock across warehouses for many products at once', function () {
    $a = Product::create(['name' => 'A']);
    $b = Product::create(['name' => 'B']);
    $c = Product::create(['name' => 'C']);
    $w1 = awWarehouse('W1', 10, $a);
    $w2 = awWarehouse('W2', 5, $a);
    app(StockService::class)->increase($b->id, $w2->id, 7, 'SEED-B');
    app(StockService::class)->decrease($a->id, $w1->id, 4, 'OUT-A');

    expect(app(StockService::class)->totalAvailableMany([$a->id, $b->id, $c->id]))
        ->toBe([$a->id => 11, $b->id => 7, $c->id => 0]);
});

it('lets a sale keep using the warehouse it already took from when it is saved again', function () {
    awLogin(['transactions.point-of-sale-new.manage']);
    $product = Product::create(['name' => 'Pupuk']);
    $a = awWarehouse('Gudang A', 6, $product);
    $b = awWarehouse('Gudang B', 9, $product);

    awSell([['product_id' => $product->id, 'qty' => 3]]);          // 3 from B (most stock): B = 6, A = 6
    $sale = awSale();
    $service = app(StockService::class);

    // Saving the same sale again changes nothing.
    expect($service->syncFromAllWarehouses($sale, [['product_id' => $product->id, 'qty' => 3]], $sale->invoice_number, $sale->sale_date))->toHaveCount(0);

    // Asking for 8: B keeps supplying the 3 it already did, the other 5 come from A.
    $service->syncFromAllWarehouses($sale, [['product_id' => $product->id, 'qty' => 8]], $sale->invoice_number, $sale->sale_date);
    expect(awStock($product, $b))->toBe(6)->and(awStock($product, $a))->toBe(1);
});
