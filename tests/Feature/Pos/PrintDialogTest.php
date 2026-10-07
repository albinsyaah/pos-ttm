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

function pdLogin(array $permissions): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'pd-'.uniqid(),
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

/** Makes one sale on the given terminal and returns it. */
function pdSell(string $terminal, string $invoice): Sale
{
    $warehouse = Warehouse::create(['code' => 'W-'.uniqid(), 'name' => 'Gudang']);
    $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pupuk']);
    app(StockService::class)->increase($product->id, $warehouse->id, 20, 'SEED-'.uniqid());

    test()->post(route("transactions.$terminal.store"), [
        'invoice_number' => $invoice,
        'sale_date' => '2026-10-01',
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1500]],
    ]);

    return Sale::where('invoice_number', $invoice)->firstOrFail();
}

const PD_CASHIER = ['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage'];
const PD_HEAD = ['transactions.point-of-sale-induk.view', 'transactions.point-of-sale-induk.manage'];

it('asks the cashier a yes/no question about the small receipt after a sale', function () {
    pdLogin([...PD_CASHIER, 'print.receipt-small']);
    $sale = pdSell('point-of-sale-new', 'INV-PD-1');

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertSee('id="printDialog"', false)
        ->assertSee('data-print="small"', false)
        ->assertSee('data-print="none"', false)
        ->assertDontSee('data-print="large"', false)
        ->assertDontSee('data-print="note"', false)
        ->assertDontSee('data-print="large+note"', false)
        ->assertSee(route('transactions.sales.print.receipt-small', $sale->id).'?auto=1', false)
        ->assertSee('Cetak nota kecil?');
});

it('offers the head cashier five choices', function () {
    pdLogin([...PD_HEAD, 'print.receipt-small', 'print.receipt-large', 'print.delivery-note']);
    $sale = pdSell('point-of-sale-induk', 'INV-PD-2');

    $this->get(route('transactions.point-of-sale-induk.index'))
        ->assertOk()
        ->assertSee('data-print="large"', false)
        ->assertSee('data-print="small"', false)
        ->assertSee('data-print="note"', false)
        ->assertSee('data-print="large+note"', false)
        ->assertSee('data-print="none"', false)
        ->assertSee(route('transactions.sales.print.receipt-large', $sale->id), false)
        ->assertSee(route('transactions.sales.print.delivery-note', $sale->id), false);
});

it('leaves out the choices the head cashier may not print', function () {
    pdLogin([...PD_HEAD, 'print.receipt-large']);
    pdSell('point-of-sale-induk', 'INV-PD-3');

    $this->get(route('transactions.point-of-sale-induk.index'))
        ->assertOk()
        ->assertSee('data-print="large"', false)
        ->assertDontSee('data-print="small"', false)
        ->assertDontSee('data-print="note"', false)
        ->assertDontSee('data-print="large+note"', false)
        ->assertDontSee('print/delivery-note', false);
});

it('shows no dialog without print permission or without a sale just made', function () {
    pdLogin(PD_CASHIER);
    pdSell('point-of-sale-new', 'INV-PD-4');

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertDontSee('id="printDialog"', false);

    pdLogin([...PD_CASHIER, 'print.receipt-small']);
    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertDontSee('id="printDialog"', false);
});
