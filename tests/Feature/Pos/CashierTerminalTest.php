<?php

use App\Models\Customer;
use App\Models\PriceSetup;
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
 * Helper names are prefixed "posTest" so they cannot clash with helpers in
 * other test files (Pest loads them all into one process).
 */
function posTestLogin(array $permissions = ['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage']): User
{
    $user = User::create([
        'username' => 'kasir-pos-'.uniqid(),
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

function posTestProduct(string $code, string $name): Product
{
    return Product::create(['code' => $code, 'name' => $name]);
}

function posTestWarehouse(string $code = 'WH-1'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => "Gudang {$code}"]);
}

function posTestCustomer(): Customer
{
    return Customer::firstOrCreate(['code' => 'CUST-1'], ['name' => 'Pelanggan 1']);
}

function posTestStock(Product $product, Warehouse $warehouse, int $qty): void
{
    app(StockService::class)->increase($product->id, $warehouse->id, $qty, 'PB-SEED');
}

function posTestAvailable(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

/**
 * @param  array<int, array{product_id: int, qty: int, price: int|string}>  $items
 * @param  array<string, mixed>  $overrides
 */
function posTestPayload(Warehouse $warehouse, array $items, array $overrides = []): array
{
    return array_merge([
        'invoice_number' => 'INV-'.uniqid(),
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => $items,
    ], $overrides);
}

function posTestSearch(Warehouse $warehouse, string $q)
{
    return test()->getJson(route('transactions.point-of-sale-new.products', [
        'q' => $q,
        'warehouse_id' => $warehouse->id,
    ]));
}

// ---- Product search --------------------------------------------------------------------

it('finds products by name and reports the stock of all warehouses together', function () {
    posTestLogin();
    $urea = posTestProduct('P-001', 'Pupuk Urea 50kg');
    posTestProduct('P-002', 'Pestisida Cair');
    $main = posTestWarehouse('WH-1');
    $other = posTestWarehouse('WH-2');
    posTestStock($urea, $main, 12);
    posTestStock($urea, $other, 99);

    posTestSearch($main, 'urea')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $urea->id)
        ->assertJsonPath('data.0.name', 'Pupuk Urea 50kg')
        ->assertJsonPath('data.0.stock', 111);   // 12 + 99: the sale is served from whichever warehouse has it

    // Which warehouse is named (if any) makes no difference any more.
    posTestSearch($other, 'urea')->assertJsonPath('data.0.stock', 111);
});

it('matches part of a name and also the product code', function () {
    posTestLogin();
    $p = posTestProduct('KSR-77', 'Benih Jagung Hibrida');
    $w = posTestWarehouse();

    posTestSearch($w, 'jagung')->assertJsonPath('data.0.id', $p->id);
    posTestSearch($w, 'KSR-77')->assertJsonPath('data.0.id', $p->id);
    posTestSearch($w, 'tidak-ada')->assertJsonCount(0, 'data');
});

it('reports zero stock for a product that has no stock rows', function () {
    posTestLogin();
    posTestProduct('P-001', 'Pupuk NPK');
    $w = posTestWarehouse();

    posTestSearch($w, 'npk')
        ->assertJsonPath('data.0.stock', 0)
        ->assertJsonPath('data.0.stock_label', '0 pcs');
});

it('shows the stock as box, pack and unit', function () {
    posTestLogin();
    $p = Product::create([
        'code' => 'P-009', 'name' => 'Sabun Cuci', 'unit_name' => 'pcs',
        'pack_name' => 'pack', 'pack_qty' => 12, 'box_name' => 'box', 'box_qty' => 120,
    ]);
    $w = posTestWarehouse();
    posTestStock($p, $w, 2 * 120 + 3 * 12 + 5);

    posTestSearch($w, 'sabun')->assertJsonPath('data.0.stock_label', '2 box 3 pack 5 pcs');
});

it('hands over the dated retail prices so the page can pick the one for the sale date', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    PriceSetup::create(['product_id' => $p->id, 'price_category' => 'Retail', 'amount' => 1000, 'effective_date' => '2026-01-01']);
    PriceSetup::create(['product_id' => $p->id, 'price_category' => 'Retail', 'amount' => 1200, 'effective_date' => '2026-06-01']);
    PriceSetup::create(['product_id' => $p->id, 'price_category' => 'Grosir', 'amount' => 900, 'effective_date' => '2026-06-01']);

    posTestSearch($w, 'urea')
        ->assertJsonCount(2, 'data.0.prices')
        ->assertJsonPath('data.0.prices.0.amount', 1200)
        ->assertJsonPath('data.0.prices.0.date', '2026-06-01')
        ->assertJsonPath('data.0.prices.1.amount', 1000);
});

it('returns nothing for an empty search and caps the number of matches', function () {
    posTestLogin();
    $w = posTestWarehouse();
    foreach (range(1, 25) as $i) {
        posTestProduct(sprintf('P-%03d', $i), "Pupuk {$i}");
    }

    posTestSearch($w, '')->assertOk()->assertJsonCount(0, 'data');
    posTestSearch($w, 'pupuk')->assertJsonCount(20, 'data');
});

it('searches without naming a warehouse', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk');

    $this->getJson(route('transactions.point-of-sale-new.products', ['q' => 'pupuk']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $p->id);
});

it('keeps the search behind the terminal permissions', function () {
    posTestLogin([]);
    $w = posTestWarehouse();

    posTestSearch($w, 'pupuk')->assertForbidden();
});

it('lets a cashier with only the manage permission use the search', function () {
    posTestLogin(['transactions.point-of-sale-new.manage']);
    $w = posTestWarehouse();

    posTestSearch($w, 'pupuk')->assertOk();
});

it('reads stock of many products in one go', function () {
    $a = posTestProduct('P-001', 'A');
    $b = posTestProduct('P-002', 'B');
    $c = posTestProduct('P-003', 'C');
    $w = posTestWarehouse();
    $stock = app(StockService::class);

    $stock->increase($a->id, $w->id, 10, 'PB-1');
    $stock->decrease($a->id, $w->id, 4, 'INV-1');
    $stock->increase($b->id, $w->id, 7, 'PB-2');

    expect($stock->availableMany([$a->id, $b->id, $c->id], $w->id))->toBe([
        $a->id => 6,
        $b->id => 7,
        $c->id => 0,
    ])->and($stock->availableMany([], $w->id))->toBe([]);
});

// ---- One line per product (paid) and one for free (Rp0) --------------------------------------

it('refuses the same product on two paid lines', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 20);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 2, 'price' => 1000],
        ['product_id' => $p->id, 'qty' => 3, 'price' => 1000],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(posTestAvailable($p, $w))->toBe(20);
});

it('refuses the same product on two free lines, even when written as 0.00', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 20);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 1, 'price' => 0],
        ['product_id' => $p->id, 'qty' => 1, 'price' => '0.00'],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0);
});

it('names the product in the duplicate message', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 20);

    // Sessions are JSON-serialised here, so session('errors') is a plain array
    // after the request; assertSessionHasErrors() unpacks it into a message bag.
    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 1, 'price' => 500],
        ['product_id' => $p->id, 'qty' => 1, 'price' => 500],
    ]))->assertSessionHasErrors([
        'items' => __('app.point_of_sale_new.duplicate_paid', ['product' => 'Pupuk Urea']),
    ]);
});

it('accepts one paid line and one free line of the same product, and takes both out of stock', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 20);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 10, 'price' => 1000],
        ['product_id' => $p->id, 'qty' => 1, 'price' => 0],
    ]))->assertSessionHasNoErrors();

    $sale = Sale::with('saleDetails')->firstOrFail();

    expect($sale->saleDetails)->toHaveCount(2)
        ->and((float) $sale->total_amount)->toBe(10000.0)
        ->and(posTestAvailable($p, $w))->toBe(9);
});

it('lets different products share one sale', function () {
    posTestLogin();
    $a = posTestProduct('P-001', 'Pupuk Urea');
    $b = posTestProduct('P-002', 'Pupuk NPK');
    $w = posTestWarehouse();
    posTestStock($a, $w, 5);
    posTestStock($b, $w, 5);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $a->id, 'qty' => 1, 'price' => 1000],
        ['product_id' => $b->id, 'qty' => 1, 'price' => 2000],
    ]))->assertSessionHasNoErrors();

    expect(Sale::count())->toBe(1);
});

it('counts the free line against stock too', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 10);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 10, 'price' => 1000],
        ['product_id' => $p->id, 'qty' => 1, 'price' => 0],
    ]))->assertSessionHasErrors('items');

    expect(Sale::count())->toBe(0)
        ->and(posTestAvailable($p, $w))->toBe(10);
});

// ---- Cash and credit -------------------------------------------------------------------------

it('records a credit sale for a registered customer as a receivable', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 10);
    $customer = posTestCustomer();

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 2, 'price' => 1000],
    ], ['payment_type' => 'credit', 'customer_id' => $customer->id]))->assertSessionHasNoErrors();

    $sale = Sale::firstOrFail();

    expect($sale->payment_type)->toBe('credit')
        ->and(Sale::receivable()->count())->toBe(1);
});

it('refuses a credit sale without a customer', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 10);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 2, 'price' => 1000],
    ], ['payment_type' => 'credit', 'customer_id' => '']))->assertSessionHasErrors('customer_id');

    expect(Sale::count())->toBe(0)
        ->and(posTestAvailable($p, $w))->toBe(10);
});

it('records a cash sale even when a customer is attached, and keeps it out of the receivables', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 10);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 2, 'price' => 1000],
    ], ['payment_type' => 'cash', 'customer_id' => posTestCustomer()->id]))->assertSessionHasNoErrors();

    expect(Sale::firstOrFail()->payment_type)->toBe('cash')
        ->and(Sale::receivable()->count())->toBe(0);
});

it('treats a sale without a payment type as cash', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 10);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 1, 'price' => 1000],
    ], ['customer_id' => posTestCustomer()->id]))->assertSessionHasNoErrors();

    expect(Sale::firstOrFail()->payment_type)->toBe('cash');
});

it('refuses an unknown payment type', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 10);

    $this->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
        ['product_id' => $p->id, 'qty' => 1, 'price' => 1000],
    ], ['payment_type' => 'barter']))->assertSessionHasErrors('payment_type');
});

it('only counts credit sales on the receivable card and the dashboard', function () {
    $customer = posTestCustomer();
    $w = posTestWarehouse();
    $make = fn (string $invoice, string $type, int $amount) => Sale::create([
        'invoice_number' => $invoice, 'sale_date' => '2026-10-01', 'total_amount' => $amount,
        'source' => 'pos', 'payment_type' => $type, 'customer_id' => $customer->id, 'warehouse_id' => $w->id,
    ]);
    $make('INV-CASH', 'cash', 5000);
    $make('INV-CREDIT', 'credit', 3000);

    posTestLogin([
        'reports.receivable-card.view', 'reports.receivable-aging.view', 'dashboard.view',
    ]);

    $this->get(route('reports.receivable-card', ['customer_id' => $customer->id]))
        ->assertOk()
        ->assertViewHas('totalDebit', fn ($debit) => (float) $debit === 3000.0);

    $this->get(route('reports.receivable-aging'))
        ->assertOk()
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 1);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('receivablesOutstanding', fn ($amount) => (float) $amount === 3000.0);
});

// ---- The terminal page -----------------------------------------------------------------------

it('opens the terminal without preloading every product', function () {
    posTestLogin();
    posTestProduct('P-001', 'Pupuk Urea');
    posTestWarehouse();

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertViewHas('cartSeed', [])
        ->assertViewMissing('products');
});

it('keeps the cart when a sale is refused for lack of stock', function () {
    posTestLogin();
    $p = posTestProduct('P-001', 'Pupuk Urea');
    $w = posTestWarehouse();
    posTestStock($p, $w, 2);

    $this->from(route('transactions.point-of-sale-new.index'))
        ->post(route('transactions.point-of-sale-new.store'), posTestPayload($w, [
            ['product_id' => $p->id, 'qty' => 5, 'price' => 1500],
        ]))
        ->assertSessionHasErrors('items');

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertViewHas('cartSeed', function ($seed) use ($p) {
            return count($seed) === 1
                && $seed[0]['id'] === $p->id
                && $seed[0]['qty'] === 5
                && $seed[0]['price'] === 1500.0
                && $seed[0]['stock'] === 2;
        });
});
