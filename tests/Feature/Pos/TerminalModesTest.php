<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

const TM_CASHIER = ['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage'];
const TM_HEAD = ['transactions.point-of-sale-induk.view', 'transactions.point-of-sale-induk.manage'];
const TM_PRINT = ['print.receipt-small', 'print.receipt-large', 'print.delivery-note'];

function tmLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'tm-'.uniqid(),
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

function tmStock(): array
{
    $warehouse = Warehouse::create(['code' => 'W-'.uniqid(), 'name' => 'Gudang Kasir']);
    $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pupuk Kasir']);
    app(StockService::class)->increase($product->id, $warehouse->id, 20, 'SEED-'.uniqid());

    return [$product, $warehouse];
}

function tmPayload(Product $product, Warehouse $warehouse, string $invoice, array $extra = []): array
{
    return $extra + [
        'invoice_number' => $invoice,
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1500]],
    ];
}

// ---- Cashier terminal (kasir biasa) ------------------------------------------------------

it('has no driver field on the cashier terminal', function () {
    tmLogin(TM_CASHIER);

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertDontSee('name="driver_name"', false)
        ->assertSee(route('transactions.point-of-sale-new.store'), false);
});

it('never offers the large receipt or delivery note on the cashier terminal', function () {
    tmLogin([...TM_CASHIER, ...TM_PRINT]);
    [$product, $warehouse] = tmStock();

    $this->post(route('transactions.point-of-sale-new.store'), tmPayload($product, $warehouse, 'INV-TM-1'));
    $sale = Sale::where('invoice_number', 'INV-TM-1')->firstOrFail();

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertSee('print/receipt-small', false)
        ->assertDontSee(route('transactions.sales.print.receipt-large', $sale->id), false)
        ->assertDontSee(route('transactions.sales.print.delivery-note', $sale->id), false);
});

// ---- Head cashier terminal (kasir induk) -------------------------------------------------

it('has the driver field and posts to its own route on the head cashier terminal', function () {
    tmLogin(TM_HEAD);

    $this->get(route('transactions.point-of-sale-induk.index'))
        ->assertOk()
        ->assertSee('Kasir Induk')
        ->assertSee('name="driver_name"', false)
        ->assertSee(route('transactions.point-of-sale-induk.store'), false)
        // The search URL is printed through @json, which escapes the slashes.
        ->assertSee(json_encode(route('transactions.point-of-sale-induk.products')), false)
        ->assertDontSee(route('transactions.point-of-sale-new.store'), false);
});

it('saves the driver name and offers all three prints on the head cashier terminal', function () {
    tmLogin([...TM_HEAD, ...TM_PRINT]);
    [$product, $warehouse] = tmStock();

    $response = $this->post(route('transactions.point-of-sale-induk.store'),
        tmPayload($product, $warehouse, 'INV-TM-2', ['driver_name' => '  Pak Joko ']));

    $sale = Sale::where('invoice_number', 'INV-TM-2')->firstOrFail();
    expect($sale->driver_name)->toBe('Pak Joko')
        ->and($sale->source)->toBe('pos');

    $response->assertRedirect(route('transactions.point-of-sale-induk.index'))
        ->assertSessionHas('printed_sale_id', $sale->id);

    $this->get(route('transactions.point-of-sale-induk.index'))
        ->assertOk()
        ->assertSee(route('transactions.sales.print.receipt-small', $sale->id).'?auto=1', false)
        ->assertSee(route('transactions.sales.print.receipt-large', $sale->id), false)
        ->assertSee(route('transactions.sales.print.delivery-note', $sale->id), false);
});

it('still hides a print link the head cashier has no permission for', function () {
    tmLogin([...TM_HEAD, 'print.receipt-small']);
    [$product, $warehouse] = tmStock();

    $this->post(route('transactions.point-of-sale-induk.store'), tmPayload($product, $warehouse, 'INV-TM-3'));
    $sale = Sale::where('invoice_number', 'INV-TM-3')->firstOrFail();

    $this->get(route('transactions.point-of-sale-induk.index'))
        ->assertOk()
        ->assertSee('print/receipt-small', false)
        ->assertDontSee(route('transactions.sales.print.delivery-note', $sale->id), false);
});

it('takes the stock out and stores a paid sale the same way in both terminals', function () {
    tmLogin([...TM_CASHIER, ...TM_HEAD]);
    [$product, $warehouse] = tmStock();

    $this->post(route('transactions.point-of-sale-new.store'), tmPayload($product, $warehouse, 'INV-TM-4'));
    $this->post(route('transactions.point-of-sale-induk.store'), tmPayload($product, $warehouse, 'INV-TM-5'));

    expect(Sale::whereIn('invoice_number', ['INV-TM-4', 'INV-TM-5'])->pluck('total_amount')->map(fn ($v) => (float) $v)->all())
        ->toBe([1500.0, 1500.0]);
});

// ---- Access is set per role --------------------------------------------------------------

it('keeps each terminal behind its own permission', function () {
    tmLogin(TM_CASHIER);
    $this->get(route('transactions.point-of-sale-new.index'))->assertOk();
    $this->get(route('transactions.point-of-sale-induk.index'))->assertForbidden();

    tmLogin(TM_HEAD);
    $this->get(route('transactions.point-of-sale-induk.index'))->assertOk();
    $this->get(route('transactions.point-of-sale-new.index'))->assertForbidden();
});

it('refuses a head cashier sale without the manage permission', function () {
    tmLogin(['transactions.point-of-sale-induk.view']);
    [$product, $warehouse] = tmStock();

    $this->post(route('transactions.point-of-sale-induk.store'), tmPayload($product, $warehouse, 'INV-TM-6'))
        ->assertForbidden();

    expect(Sale::where('invoice_number', 'INV-TM-6')->exists())->toBeFalse();
});

it('lets the head terminal search products with either of its permissions', function () {
    tmLogin(['transactions.point-of-sale-induk.manage']);
    [, $warehouse] = tmStock();

    $this->getJson(route('transactions.point-of-sale-induk.products', ['q' => 'pupuk', 'warehouse_id' => $warehouse->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows each terminal link in the menu only to roles that may open it', function () {
    tmLogin([...TM_CASHIER, 'dashboard.view']);
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('transactions.point-of-sale-new.index'), false)
        ->assertDontSee(route('transactions.point-of-sale-induk.index'), false);

    tmLogin([...TM_HEAD, 'dashboard.view']);
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('transactions.point-of-sale-induk.index'), false)
        ->assertDontSee(route('transactions.point-of-sale-new.index'), false);
});

it('creates the head cashier permissions', function () {
    foreach (TM_HEAD as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
});

// ---- Old Point of Sale page (for the head cashier) ---------------------------------------

it('saves, changes and clears the driver name on the Point of Sale page', function () {
    tmLogin(['transactions.point-of-sale.view', 'transactions.point-of-sale.manage']);
    [$product, $warehouse] = tmStock();

    $this->post(route('transactions.point-of-sale.store'),
        tmPayload($product, $warehouse, 'INV-TM-7', ['driver_name' => ' Pak Budi ']))->assertRedirect();
    $sale = Sale::where('invoice_number', 'INV-TM-7')->firstOrFail();
    expect($sale->driver_name)->toBe('Pak Budi');

    $this->put(route('transactions.point-of-sale.update', $sale),
        tmPayload($product, $warehouse, 'INV-TM-7', ['driver_name' => 'Pak Ali']))->assertRedirect();
    expect($sale->fresh()->driver_name)->toBe('Pak Ali');

    $this->put(route('transactions.point-of-sale.update', $sale),
        tmPayload($product, $warehouse, 'INV-TM-7', ['driver_name' => '']))->assertRedirect();
    expect($sale->fresh()->driver_name)->toBeNull();
});

it('shows the driver field and the saved driver on the Point of Sale page', function () {
    tmLogin(['transactions.point-of-sale.view', 'transactions.point-of-sale.manage']);
    [$product, $warehouse] = tmStock();
    $sale = Sale::create([
        'invoice_number' => 'INV-TM-8', 'sale_date' => '2026-10-01', 'total_amount' => 1500, 'source' => 'pos',
        'warehouse_id' => $warehouse->id, 'driver_name' => 'Pak Joko',
    ]);

    $this->get(route('transactions.point-of-sale.index'))
        ->assertOk()
        ->assertSee('name="driver_name"', false)
        ->assertSee('data-driver-name="Pak Joko"', false);
});
