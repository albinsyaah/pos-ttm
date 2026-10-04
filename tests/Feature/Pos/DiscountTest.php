<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReceivableService;
use App\Services\SaleDiscountService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

/* Helper names start with "dc" so they cannot clash with other test files. */

function dcLogin(array $permissions = ['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage']): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'dc-'.uniqid(),
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

/** A product with plenty of stock in one warehouse. */
function dcStocked(string $name = 'Pupuk Urea', int $stock = 100): array
{
    $product = Product::create(['name' => $name]);
    $warehouse = Warehouse::create(['name' => 'Gudang '.uniqid()]);
    app(StockService::class)->increase($product->id, $warehouse->id, $stock, 'SEED-'.uniqid());

    return [$product, $warehouse];
}

function dcStock(Product $product, Warehouse $warehouse): int
{
    return app(StockService::class)->available($product->id, $warehouse->id);
}

/**
 * Ring up a sale on the cashier terminal. One line of 2 x 50.000 (a subtotal of 100.000)
 * unless other lines are given.
 *
 * @param  array<string, mixed>  $extra
 */
function dcSell(Product $product, array $extra = [], ?array $items = null, string $route = 'transactions.point-of-sale-new.store')
{
    return test()->post(route($route), array_merge([
        'sale_date' => '2026-10-01',
        'items' => $items ?? [['product_id' => $product->id, 'qty' => 2, 'price' => 50000]],
    ], $extra));
}

function dcSale(): Sale
{
    return Sale::orderByDesc('id')->firstOrFail();
}

// ---- Working out the discount -----------------------------------------------------------------------------

it('takes a percentage off the subtotal', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 10])->assertSessionHasNoErrors();

    $sale = dcSale();
    expect((float) $sale->total_amount)->toBe(90000.0)
        ->and((float) $sale->discount_total)->toBe(10000.0)
        ->and((float) $sale->discount_percent)->toBe(10.0)
        ->and((float) $sale->discount_amount)->toBe(0.0);
});

it('takes a fixed amount off the subtotal', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product, ['discount_amount' => 10000])->assertSessionHasNoErrors();

    $sale = dcSale();
    expect((float) $sale->total_amount)->toBe(90000.0)
        ->and((float) $sale->discount_total)->toBe(10000.0)
        ->and((float) $sale->discount_percent)->toBe(0.0)
        ->and((float) $sale->discount_amount)->toBe(10000.0);
});

it('takes both off the same subtotal when a percentage and an amount are given', function () {
    dcLogin();
    [$product] = dcStocked();

    // 10% of 100.000 is 10.000, plus 5.000: the percentage is not taken from what the amount left.
    dcSell($product, ['discount_percent' => 10, 'discount_amount' => 5000])->assertSessionHasNoErrors();

    $sale = dcSale();
    expect((float) $sale->discount_total)->toBe(15000.0)
        ->and((float) $sale->total_amount)->toBe(85000.0);
});

it('leaves a sale without a discount as it was', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product)->assertSessionHasNoErrors();
    dcSell($product, ['discount_percent' => '', 'discount_amount' => ''])->assertSessionHasNoErrors();

    foreach (Sale::all() as $sale) {
        expect((float) $sale->total_amount)->toBe(100000.0)
            ->and((float) $sale->discount_total)->toBe(0.0)
            ->and($sale->hasDiscount())->toBeFalse();
    }
});

it('rounds the percentage part to cents', function () {
    dcLogin();
    [$product] = dcStocked();

    // 33,33% of 100.000 is 33.330.
    dcSell($product, ['discount_percent' => 33.33])->assertSessionHasNoErrors();

    expect((float) dcSale()->discount_total)->toBe(33330.0)
        ->and((float) dcSale()->total_amount)->toBe(66670.0);
});

it('does not count free lines in the subtotal', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 10], [
        ['product_id' => $product->id, 'qty' => 2, 'price' => 50000],
        ['product_id' => $product->id, 'qty' => 1, 'price' => 0],
    ])->assertSessionHasNoErrors();

    $sale = dcSale();
    expect((float) $sale->discount_total)->toBe(10000.0)
        ->and((float) $sale->total_amount)->toBe(90000.0)
        ->and($sale->saleDetails()->count())->toBe(2);
});

it('allows a discount that is exactly the whole subtotal', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 100])->assertSessionHasNoErrors();

    expect((float) dcSale()->total_amount)->toBe(0.0)
        ->and((float) dcSale()->discount_total)->toBe(100000.0);
});

// ---- What is refused ---------------------------------------------------------------------------------------

it('refuses a discount larger than the subtotal and saves nothing', function () {
    dcLogin();
    [$product, $warehouse] = dcStocked();

    dcSell($product, ['discount_amount' => 100001])->assertSessionHasErrors('discount_amount');
    dcSell($product, ['discount_percent' => 50, 'discount_amount' => 60000])->assertSessionHasErrors('discount_amount');

    expect(Sale::count())->toBe(0)
        ->and(dcStock($product, $warehouse))->toBe(100);
});

it('refuses a percentage above 100 and a negative discount', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 101])->assertSessionHasErrors('discount_percent');
    dcSell($product, ['discount_percent' => -5])->assertSessionHasErrors('discount_percent');
    dcSell($product, ['discount_amount' => -1])->assertSessionHasErrors('discount_amount');
    dcSell($product, ['discount_amount' => 'abc'])->assertSessionHasErrors('discount_amount');

    expect(Sale::count())->toBe(0);
});

it('refuses a discount on a sale made only of free items', function () {
    dcLogin();
    [$product] = dcStocked();

    dcSell($product, ['discount_amount' => 1000], [['product_id' => $product->id, 'qty' => 1, 'price' => 0]])
        ->assertSessionHasErrors('discount_amount');

    expect(Sale::count())->toBe(0);
});

it('ignores a discount the browser did not work out: the server does its own sums', function () {
    dcLogin();
    [$product] = dcStocked();

    // A request that also sends a total of its own cannot change what is charged.
    dcSell($product, ['discount_percent' => 10, 'total_amount' => 1, 'discount_total' => 99999])->assertSessionHasNoErrors();

    expect((float) dcSale()->total_amount)->toBe(90000.0)
        ->and((float) dcSale()->discount_total)->toBe(10000.0);
});

// ---- Both terminals ----------------------------------------------------------------------------------------

it('works on the head cashier terminal too', function () {
    dcLogin(['transactions.point-of-sale-induk.view', 'transactions.point-of-sale-induk.manage']);
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 5, 'discount_amount' => 1000], null, 'transactions.point-of-sale-induk.store')
        ->assertSessionHasNoErrors();

    // 5% of 100.000 is 5.000, plus 1.000.
    expect((float) dcSale()->discount_total)->toBe(6000.0)
        ->and((float) dcSale()->total_amount)->toBe(94000.0);
});

it('shows the discount boxes on both terminals', function () {
    dcLogin(['transactions.point-of-sale-new.view', 'transactions.point-of-sale-induk.view']);

    foreach (['transactions.point-of-sale-new.index', 'transactions.point-of-sale-induk.index'] as $route) {
        $this->get(route($route))->assertOk()
            ->assertSee('name="discount_percent"', false)
            ->assertSee('name="discount_amount"', false)
            ->assertSee('id="discountError"', false);
    }
});

it('keeps what the cashier typed when the sale is refused', function () {
    dcLogin();
    [$product] = dcStocked('Pupuk Urea', 1);   // only 1 in stock, 2 are asked for

    dcSell($product, ['discount_percent' => 12.5, 'discount_amount' => 2000])->assertSessionHasErrors('items');

    $this->get(route('transactions.point-of-sale-new.index'))->assertOk()
        ->assertSee('value="12.5"', false)
        ->assertSee('value="2000"', false);
});

it('still lets a sale through without any discount field at all (old clients)', function () {
    dcLogin();
    [$product] = dcStocked();

    $this->post(route('transactions.point-of-sale-new.store'), [
        'sale_date' => '2026-10-01',
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1000]],
    ])->assertSessionHasNoErrors();

    expect((float) dcSale()->total_amount)->toBe(1000.0);
});

// ---- Money that follows from the total ---------------------------------------------------------------------

it('bases the receivable of a credit sale on the discounted total', function () {
    dcLogin();
    [$product] = dcStocked();
    $customer = Customer::create(['name' => 'Toko Tani']);

    dcSell($product, [
        'payment_type' => 'credit',
        'customer_id' => $customer->id,
        'discount_percent' => 10,
    ])->assertSessionHasNoErrors();

    expect(app(ReceivableService::class)->totalOutstanding('2026-12-31'))->toBe(90000.0);
});

// ---- Receipts ---------------------------------------------------------------------------------------------

it('prints subtotal and discount on the receipts when there is one', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'print.receipt-small', 'print.receipt-large']);
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 10, 'discount_amount' => 5000])->assertSessionHasNoErrors();
    $sale = dcSale();

    foreach (['transactions.sales.print.receipt-small', 'transactions.sales.print.receipt-large'] as $route) {
        $this->get(route($route, $sale))->assertOk()
            ->assertSee('Subtotal')
            ->assertSee('Rp 100.000')
            ->assertSee('Diskon')
            ->assertSee('10%')
            ->assertSee('-Rp 15.000')
            ->assertSee('Rp 85.000');
    }

    $this->get(route('receipts.show', $sale->public_token))->assertOk()
        ->assertSee('Diskon')
        ->assertSee('-Rp 15.000')
        ->assertSee('Rp 85.000');
});

it('leaves the discount lines off the receipts of a sale without a discount', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'print.receipt-small', 'print.receipt-large']);
    [$product] = dcStocked();

    dcSell($product)->assertSessionHasNoErrors();
    $sale = dcSale();

    $this->get(route('transactions.sales.print.receipt-small', $sale))->assertOk()->assertDontSee('Diskon');
    $this->get(route('transactions.sales.print.receipt-large', $sale))->assertOk()->assertDontSee('Diskon');
    $this->get(route('receipts.show', $sale->public_token))->assertOk()->assertDontSee('Diskon');
});

it('does not repeat the amount in the label when only a fixed amount was given', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'print.receipt-small']);
    [$product] = dcStocked();

    dcSell($product, ['discount_amount' => 10000])->assertSessionHasNoErrors();

    expect(dcSale()->discountDescription())->toBe('Rp 10.000');

    $html = $this->get(route('transactions.sales.print.receipt-small', dcSale()))->assertOk()->getContent();
    expect($html)->not->toContain('Diskon (')
        ->and($html)->toContain('-Rp 10.000');
});

// ---- Editing the sale on another page keeps the discount --------------------------------------------------

it('keeps the discount when the sale is edited on the Point of Sale page', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'transactions.point-of-sale.manage']);
    [$product, $warehouse] = dcStocked();

    dcSell($product, ['discount_percent' => 10, 'discount_amount' => 5000])->assertSessionHasNoErrors();
    $sale = dcSale();

    // The lines are changed to 4 x 50.000 = 200.000: 10% is 20.000, plus 5.000.
    $this->put(route('transactions.point-of-sale.update', $sale), [
        'invoice_number' => $sale->invoice_number,
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 4, 'price' => 50000]],
    ])->assertSessionHasNoErrors();

    $sale->refresh();
    expect((float) $sale->discount_total)->toBe(25000.0)
        ->and((float) $sale->total_amount)->toBe(175000.0)
        ->and((float) $sale->discount_percent)->toBe(10.0)
        ->and((float) $sale->discount_amount)->toBe(5000.0);
});

it('refuses an edit that leaves too little to cover the discount, and changes nothing', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'transactions.point-of-sale.manage']);
    [$product, $warehouse] = dcStocked();

    dcSell($product, ['discount_amount' => 60000])->assertSessionHasNoErrors();
    $sale = dcSale();
    $stockBefore = dcStock($product, $warehouse);

    // 1 x 50.000 is less than the 60.000 discount.
    $this->put(route('transactions.point-of-sale.update', $sale), [
        'invoice_number' => $sale->invoice_number,
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 50000]],
    ])->assertSessionHasErrors('items');

    $sale->refresh();
    expect((float) $sale->total_amount)->toBe(40000.0)
        ->and((int) $sale->saleDetails()->first()->qty)->toBe(2)
        ->and(dcStock($product, $warehouse))->toBe($stockBefore);
});

it('leaves sales made on other pages alone: no discount, total as the lines add up', function () {
    dcLogin(['transactions.point-of-sale.manage']);
    [$product, $warehouse] = dcStocked();

    $this->post(route('transactions.point-of-sale.store'), [
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 2, 'price' => 50000]],
    ])->assertSessionHasNoErrors();

    $sale = dcSale();
    expect((float) $sale->total_amount)->toBe(100000.0)
        ->and((float) $sale->discount_total)->toBe(0.0);

    $this->put(route('transactions.point-of-sale.update', $sale), [
        'invoice_number' => $sale->invoice_number,
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 3, 'price' => 50000]],
    ])->assertSessionHasNoErrors();

    expect((float) $sale->fresh()->total_amount)->toBe(150000.0);
});

// ---- Report ------------------------------------------------------------------------------------------------

it('shows the discount in the sales summary report', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'reports.sales-summary.view']);
    [$product] = dcStocked();

    dcSell($product, ['discount_percent' => 10])->assertSessionHasNoErrors();

    $this->get(route('reports.sales-summary'))->assertOk()
        ->assertSee('id="discountTotalHint"', false)
        ->assertSee('Total Diskon: Rp 10.000')
        // The sales total is what was charged, after the discount.
        ->assertSee('Rp 90.000');
});

it('leaves the discount note off the report when nothing was discounted', function () {
    dcLogin(['transactions.point-of-sale-new.manage', 'reports.sales-summary.view']);
    [$product] = dcStocked();

    dcSell($product)->assertSessionHasNoErrors();

    $this->get(route('reports.sales-summary'))->assertOk()
        ->assertDontSee('id="discountTotalHint"', false);
});

// ---- The calculation on its own --------------------------------------------------------------------------------

it('calculates a discount and refuses one that is too large', function () {
    $service = new SaleDiscountService;

    $r = $service->calculate(200000, 10, 5000);
    expect($r['discount_total'])->toBe(25000.0)
        ->and($r['total_amount'])->toBe(175000.0)
        ->and($r['subtotal'])->toBe(200000.0);

    $none = $service->calculate(1234.5, 0, 0);
    expect($none['discount_total'])->toBe(0.0)->and($none['total_amount'])->toBe(1234.5);

    expect(fn () => $service->calculate(1000, 0, 1000.01))->toThrow(ValidationException::class);
    expect(fn () => $service->calculate(1000, 100.01, 0))->toThrow(ValidationException::class);
    expect($service->calculate(1000, 100, 0)['total_amount'])->toBe(0.0);
});

it('describes how the discount was entered', function () {
    $sale = new Sale(['discount_percent' => 12.5, 'discount_amount' => 0, 'discount_total' => 1]);
    expect($sale->discountDescription())->toBe('12,5%');

    $sale = new Sale(['discount_percent' => 10, 'discount_amount' => 5000, 'discount_total' => 1]);
    expect($sale->discountDescription())->toBe('10% + Rp 5.000');

    $sale = new Sale(['discount_percent' => 0, 'discount_amount' => 0, 'discount_total' => 0]);
    expect($sale->discountDescription())->toBe('')->and($sale->hasDiscount())->toBeFalse();
});
