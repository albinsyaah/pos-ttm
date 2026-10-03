<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

/* Helper names start with "rl" so they cannot clash with other test files. */

function rlLogin(array $permissions): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'rl-'.uniqid(),
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

/** A sale with Pupuk Urea x10 and Benih Padi x3. */
function rlSale(): array
{
    $warehouse = Warehouse::create(['name' => 'Gudang RL']);
    $urea = Product::create(['name' => 'Pupuk Urea']);
    $benih = Product::create(['name' => 'Benih Padi']);
    $other = Product::create(['name' => 'Cangkul Tidak Di Nota']);

    $sale = Sale::create([
        'sale_date' => '2026-10-01',
        'total_amount' => 0,
        'source' => 'sales',
        'customer_id' => Customer::create(['name' => 'Toko RL'])->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $sale->saleDetails()->create(['product_id' => $urea->id, 'qty' => 10, 'price' => 1000]);
    $sale->saleDetails()->create(['product_id' => $benih->id, 'qty' => 3, 'price' => 500]);

    return [$sale, $urea, $benih, $other];
}

function rlSalesReturn(Sale $sale, Product $product, int $qty): SalesReturn
{
    $return = SalesReturn::create(['return_date' => '2026-10-02', 'total_amount' => 0, 'sale_id' => $sale->id]);
    $return->salesReturnDetails()->create(['product_id' => $product->id, 'qty' => $qty]);

    return $return;
}

function rlPurchase(string $status = 'received'): array
{
    $product = Product::create(['name' => 'Pestisida']);

    $purchase = Purchase::create([
        'purchase_date' => '2026-10-01',
        'total_amount' => 0,
        'status' => $status,
        'supplier_id' => Supplier::create(['name' => 'CV RL'])->id,
        'warehouse_id' => Warehouse::create(['name' => 'Gudang Beli'])->id,
    ]);
    $purchase->purchaseDetails()->create(['product_id' => $product->id, 'qty' => 8, 'price' => 2000]);

    return [$purchase, $product];
}

// ---- Sales return: the products of the chosen sale ----------------------------------------------

it('lists only the products on the sale, with what may still be returned', function () {
    rlLogin(['transactions.sales-returns.view']);
    [$sale, $urea, $benih] = rlSale();
    rlSalesReturn($sale, $urea, 4);

    $response = $this->getJson(route('transactions.sales-returns.lines', ['sale' => $sale->id]))->assertOk();

    expect($response->json('returnable'))->toBeTrue()
        ->and($response->json('source'))->toBe($sale->invoice_number)
        ->and($response->json('lines'))->toHaveCount(2)
        // Sorted by name: Benih Padi, then Pupuk Urea.
        ->and($response->json('lines.0'))->toMatchArray([
            'product_id' => $benih->id, 'name' => 'Benih Padi', 'original' => 3, 'returned' => 0, 'remaining' => 3,
        ])
        ->and($response->json('lines.1'))->toMatchArray([
            'product_id' => $urea->id, 'name' => 'Pupuk Urea', 'original' => 10, 'returned' => 4, 'remaining' => 6,
        ]);
});

it('does not list a product that is not on the sale', function () {
    rlLogin(['transactions.sales-returns.view']);
    [$sale, , , $other] = rlSale();

    $ids = collect($this->getJson(route('transactions.sales-returns.lines', ['sale' => $sale->id]))->json('lines'))->pluck('product_id');

    expect($ids)->not->toContain($other->id);
});

it('counts the quantities of the return being edited as still available', function () {
    rlLogin(['transactions.sales-returns.view']);
    [$sale, $urea] = rlSale();
    $mine = rlSalesReturn($sale, $urea, 4);
    rlSalesReturn($sale, $urea, 2);

    $lines = fn (array $query) => collect($this->getJson(route('transactions.sales-returns.lines', ['sale' => $sale->id] + $query))->json('lines'))
        ->firstWhere('product_id', $urea->id);

    expect($lines([])['remaining'])->toBe(4)                                  // 10 - 4 - 2
        ->and($lines(['exclude' => $mine->id])['remaining'])->toBe(8)         // 10 - 2, my own 4 are free again
        ->and($lines(['exclude' => $mine->id])['returned'])->toBe(2);
});

it('shows a product with nothing left to return as remaining 0', function () {
    rlLogin(['transactions.sales-returns.view']);
    [$sale, , $benih] = rlSale();
    rlSalesReturn($sale, $benih, 3);

    $line = collect($this->getJson(route('transactions.sales-returns.lines', ['sale' => $sale->id]))->json('lines'))
        ->firstWhere('product_id', $benih->id);

    expect($line['remaining'])->toBe(0);
});

it('keeps the sales return lines behind the page permission', function () {
    rlLogin(['customers.view']);
    [$sale] = rlSale();

    $this->getJson(route('transactions.sales-returns.lines', ['sale' => $sale->id]))->assertForbidden();
});

// ---- Purchase return -----------------------------------------------------------------------------

it('lists the products of a received purchase with what may still go back', function () {
    rlLogin(['transactions.purchase-returns.view']);
    [$purchase, $product] = rlPurchase();

    $return = PurchaseReturn::create(['return_date' => '2026-10-02', 'total_amount' => 0, 'purchase_id' => $purchase->id]);
    $return->purchaseReturnDetails()->create(['product_id' => $product->id, 'qty' => 5]);

    $response = $this->getJson(route('transactions.purchase-returns.lines', ['purchase' => $purchase->id]))->assertOk();

    expect($response->json('returnable'))->toBeTrue()
        ->and($response->json('lines.0'))->toMatchArray([
            'product_id' => $product->id, 'original' => 8, 'returned' => 5, 'remaining' => 3,
        ]);

    $edit = $this->getJson(route('transactions.purchase-returns.lines', ['purchase' => $purchase->id, 'exclude' => $return->id]))->json('lines.0');
    expect($edit['remaining'])->toBe(8);
});

it('says a purchase that has not been received cannot be returned', function () {
    rlLogin(['transactions.purchase-returns.view']);
    [$purchase] = rlPurchase('ordered');

    $response = $this->getJson(route('transactions.purchase-returns.lines', ['purchase' => $purchase->id]))->assertOk();

    expect($response->json('returnable'))->toBeFalse()
        ->and($response->json('lines'))->toBe([])
        ->and($response->json('message'))->toContain($purchase->invoice_number);
});

it('keeps the purchase return lines behind the page permission', function () {
    rlLogin(['customers.view']);
    [$purchase] = rlPurchase();

    $this->getJson(route('transactions.purchase-returns.lines', ['purchase' => $purchase->id]))->assertForbidden();
});

// ---- The pages ---------------------------------------------------------------------------------------

it('builds the return pages so the products come from the chosen invoice, not the whole catalogue', function () {
    rlLogin(['transactions.sales-returns.view', 'transactions.purchase-returns.view']);
    Product::create(['name' => 'Barang Katalog Saja']);

    $sales = $this->get(route('transactions.sales-returns.index'))->assertOk();
    $sales->assertSee('data-lines-url=', false)
        ->assertSee('js/product-picker.js', false)
        ->assertSee('js/return-lines.js', false)
        ->assertDontSee('Barang Katalog Saja');

    $purchases = $this->get(route('transactions.purchase-returns.index'))->assertOk();
    $purchases->assertSee('data-lines-url=', false)
        ->assertSee('js/return-lines.js', false)
        ->assertDontSee('Barang Katalog Saja');
});

it('gives the purchase and purchase order pages the product search', function () {
    rlLogin(['transactions.purchases.view', 'transactions.purchase-orders.view']);
    Product::create(['name' => 'Pupuk Cari']);

    $this->get(route('transactions.purchases.index'))->assertOk()->assertSee('js/product-picker.js', false);
    $this->get(route('transactions.purchase-orders.index'))->assertOk()->assertSee('js/product-picker.js', false);
});
