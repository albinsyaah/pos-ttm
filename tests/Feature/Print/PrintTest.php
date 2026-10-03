<?php

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// Teks cetak diperiksa dalam bahasa Indonesia; locale bawaan aplikasi 'en'.
beforeEach(fn () => config(['app.locale' => 'id']));

function prtLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'prt-'.uniqid(),
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

/** A finished sale: 2 paid lines + 1 free line, ready to print. */
function prtSale(array $attributes = []): Sale
{
    $warehouse = Warehouse::create(['code' => 'W-'.uniqid(), 'name' => 'Gudang Induk']);
    $customer = Customer::create([
        'code' => 'C-'.uniqid(), 'name' => 'Toko Subur', 'phone' => '0812000111', 'address' => 'Jl. Tani 12',
    ]);
    $urea = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pupuk Urea']);
    $npk = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pupuk NPK']);

    $sale = Sale::create($attributes + [
        'invoice_number' => 'INV-'.uniqid(),
        'sale_date' => '2026-10-01',
        'total_amount' => 12000,
        'source' => 'sales',
        'payment_type' => Sale::PAYMENT_CASH,
        'payment_method_id' => PaymentMethod::where('code', 'cash')->value('id'),
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $sale->saleDetails()->createMany([
        ['product_id' => $urea->id, 'qty' => 2, 'price' => 5000],
        ['product_id' => $npk->id, 'qty' => 1, 'price' => 2000],
        ['product_id' => $npk->id, 'qty' => 1, 'price' => 0],
    ]);

    return $sale;
}

// ---- Tokens and the public digital receipt ----------------------------------------------

it('gives every new sale an unguessable public token', function () {
    $a = prtSale();
    $b = prtSale();

    expect($a->public_token)->toHaveLength(32)
        ->and($b->public_token)->toHaveLength(32)
        ->and($a->public_token)->not->toBe($b->public_token)
        ->and($a->digitalReceiptUrl())->toContain('/n/'.$a->public_token);
});

it('opens the digital receipt without signing in', function () {
    $sale = prtSale();

    $this->get(route('receipts.show', $sale->public_token))
        ->assertOk()
        ->assertSee($sale->invoice_number)
        ->assertSee('Pupuk Urea')
        ->assertSee('Rp 12.000')
        ->assertSee('Gratis')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps internal details off the digital receipt', function () {
    $sale = prtSale(['driver_name' => 'Pak Joko']);

    $this->get(route('receipts.show', $sale->public_token))
        ->assertOk()
        ->assertDontSee('Gudang Induk')
        ->assertDontSee('Pak Joko');
});

it('shows a credit sale as credit and a cash sale as paid', function () {
    $credit = prtSale(['payment_type' => Sale::PAYMENT_CREDIT, 'payment_method_id' => null]);
    $cash = prtSale();

    $this->get(route('receipts.show', $credit->public_token))->assertSee('Kredit')->assertDontSee('LUNAS');
    $this->get(route('receipts.show', $cash->public_token))->assertSee('LUNAS')->assertSee('Tunai');
});

it('does not open a receipt for an unknown or malformed token', function () {
    prtSale();

    $this->get(route('receipts.show', str_repeat('a', 32)))->assertNotFound();
    $this->get('/n/short')->assertNotFound();
    $this->get('/n/1')->assertNotFound();
});

// ---- Small receipt ----------------------------------------------------------------------

it('prints the small receipt on 100 x 150 mm paper with the barcode link', function () {
    prtLogin(['print.receipt-small']);
    $sale = prtSale();

    $this->get(route('transactions.sales.print.receipt-small', $sale))
        ->assertOk()
        ->assertSee($sale->invoice_number)
        ->assertSee('Pupuk Urea')
        ->assertSee('Rp 12.000')
        ->assertSee('data-url="'.$sale->digitalReceiptUrl().'"', false)
        ->assertSee('size: 100mm 150mm', false);
});

it('ignores a width in the query: the small receipt is always 100 x 150 mm', function () {
    prtLogin(['print.receipt-small']);
    $sale = prtSale();

    $this->get(route('transactions.sales.print.receipt-small', [$sale, 'width' => 58]))
        ->assertOk()->assertSee('size: 100mm 150mm', false);
});

it('shows the small receipt quantity in the product units', function () {
    prtLogin(['print.receipt-small']);
    $sale = prtSale();

    $detail = $sale->saleDetails()->first();
    $detail->product->update(['unit_name' => 'sak']);

    $this->get(route('transactions.sales.print.receipt-small', $sale))->assertSee('2 sak');
});

// ---- Large receipt ----------------------------------------------------------------------

it('prints the large receipt with customer, lines, total and barcode', function () {
    prtLogin(['print.receipt-large']);
    $sale = prtSale();

    $this->get(route('transactions.sales.print.receipt-large', $sale))
        ->assertOk()
        ->assertSee('FAKTUR PENJUALAN')
        ->assertSee('Toko Subur')
        ->assertSee('Jl. Tani 12')
        ->assertSee('Rp 10.000')
        ->assertSee('Rp 12.000')
        ->assertSee('data-url="'.$sale->digitalReceiptUrl().'"', false);
});

// ---- Delivery note ----------------------------------------------------------------------

it('prints the delivery note with driver, warehouse, three signatures and no prices', function () {
    prtLogin(['print.delivery-note']);
    $sale = prtSale(['driver_name' => 'Pak Joko']);

    $this->get(route('transactions.sales.print.delivery-note', $sale))
        ->assertOk()
        ->assertSee('SURAT JALAN')
        ->assertSee('Pak Joko')
        ->assertSee('Gudang Induk')
        ->assertSee('Toko Subur')
        ->assertSee('Pupuk Urea')
        ->assertSee('Admin')
        ->assertSee('Kepala Gudang')
        ->assertSee('Penerima')
        ->assertDontSee('Rp ');
});

it('leaves a blank line for the driver when none was recorded', function () {
    prtLogin(['print.delivery-note']);
    $sale = prtSale();

    $this->get(route('transactions.sales.print.delivery-note', $sale))
        ->assertOk()
        ->assertSee('class="blank-line"', false);
});

// ---- Who may print what -----------------------------------------------------------------

it('keeps each document behind its own permission', function () {
    $sale = prtSale();

    // A cashier (small receipt only) cannot open the main cashier's documents.
    prtLogin(['print.receipt-small']);
    $this->get(route('transactions.sales.print.receipt-small', $sale))->assertOk();
    $this->get(route('transactions.sales.print.receipt-large', $sale))->assertForbidden();
    $this->get(route('transactions.sales.print.delivery-note', $sale))->assertForbidden();
});

it('lets the main cashier print the large receipt and the delivery note', function () {
    $sale = prtSale();

    prtLogin(['print.receipt-large', 'print.delivery-note']);
    $this->get(route('transactions.sales.print.receipt-large', $sale))->assertOk();
    $this->get(route('transactions.sales.print.delivery-note', $sale))->assertOk();
    $this->get(route('transactions.sales.print.receipt-small', $sale))->assertForbidden();
});

it('sends signed-out visitors to the login page instead of printing', function () {
    $sale = prtSale();

    $this->get(route('transactions.sales.print.receipt-small', $sale))->assertRedirect();
    $this->get(route('transactions.sales.print.delivery-note', $sale))->assertRedirect();
});

it('creates the three print permissions', function () {
    foreach (['print.receipt-small', 'print.receipt-large', 'print.delivery-note'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
});

// ---- Older sales without a token --------------------------------------------------------

it('gives an older sale its token the first time it is printed', function () {
    prtLogin(['print.receipt-small']);
    $sale = prtSale();
    DB::table('sales')->where('id', $sale->id)->update(['public_token' => null]);

    $this->get(route('transactions.sales.print.receipt-small', $sale->id))->assertOk();

    $token = $sale->fresh()->public_token;
    expect($token)->toHaveLength(32);
    $this->get(route('receipts.show', $token))->assertOk();
});

// ---- Driver name on the sale pages ------------------------------------------------------

function prtStockedProduct(): array
{
    $warehouse = Warehouse::create(['code' => 'W-'.uniqid(), 'name' => 'Gudang Kirim']);
    $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pupuk Kirim']);
    app(StockService::class)->increase($product->id, $warehouse->id, 20, 'SEED-'.uniqid());

    return [$product, $warehouse];
}

it('saves, changes and clears the driver name on the Sales page', function () {
    prtLogin(['transactions.sales.view', 'transactions.sales.manage']);
    [$product, $warehouse] = prtStockedProduct();
    $customer = Customer::create(['code' => 'C-'.uniqid(), 'name' => 'Pelanggan Kirim']);

    $payload = fn (array $extra = []) => $extra + [
        'invoice_number' => 'INV-DRV-1',
        'sale_date' => '2026-10-01',
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1000]],
    ];

    $this->post(route('transactions.sales.store'), $payload(['driver_name' => '  Pak Joko ']))->assertRedirect();
    $sale = Sale::where('invoice_number', 'INV-DRV-1')->firstOrFail();
    expect($sale->driver_name)->toBe('Pak Joko');

    $this->put(route('transactions.sales.update', $sale), $payload(['driver_name' => 'Pak Budi']))->assertRedirect();
    expect($sale->fresh()->driver_name)->toBe('Pak Budi');

    $this->put(route('transactions.sales.update', $sale), $payload(['driver_name' => '']))->assertRedirect();
    expect($sale->fresh()->driver_name)->toBeNull();
});

it('ignores a driver name sent to the cashier terminal and offers only the small receipt', function () {
    prtLogin([
        'transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage',
        'print.receipt-small', 'print.receipt-large', 'print.delivery-note',
    ]);
    [$product, $warehouse] = prtStockedProduct();

    $response = $this->post(route('transactions.point-of-sale-new.store'), [
        'invoice_number' => 'INV-POS-DRV',
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'driver_name' => 'Pak Joko',
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1500]],
    ]);

    $sale = Sale::where('invoice_number', 'INV-POS-DRV')->firstOrFail();
    expect($sale->driver_name)->toBeNull();
    $response->assertRedirect(route('transactions.point-of-sale-new.index'))
        ->assertSessionHas('printed_sale_id', $sale->id);

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertSee(route('transactions.sales.print.receipt-small', $sale->id).'?auto=1', false)
        ->assertDontSee('print/receipt-large', false)
        ->assertDontSee('print/delivery-note', false);
});

it('does not offer print links to a cashier without print permissions', function () {
    prtLogin(['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage']);
    [$product, $warehouse] = prtStockedProduct();

    $this->post(route('transactions.point-of-sale-new.store'), [
        'invoice_number' => 'INV-POS-NOPRINT',
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1500]],
    ]);

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertDontSee('print/receipt-small', false);
});

it('shows the print shortcuts on the Sales list only for permitted documents', function () {
    prtLogin(['transactions.sales.view', 'print.delivery-note']);
    $sale = prtSale();

    $this->get(route('transactions.sales.index'))
        ->assertOk()
        ->assertSee(route('transactions.sales.print.delivery-note', $sale), false)
        ->assertDontSee(route('transactions.sales.print.receipt-large', $sale), false);
});

// ---- Money ------------------------------------------------------------------------------

it('formats rupiah with dots and no decimals', function () {
    expect(Money::rupiah(1250000))->toBe('Rp 1.250.000')
        ->and(Money::rupiah('5000.00'))->toBe('Rp 5.000')
        ->and(Money::rupiah(0))->toBe('Rp 0')
        ->and(Money::rupiah(null))->toBe('Rp 0');
});
