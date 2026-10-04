<?php

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function pmLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'pm-'.uniqid(),
        'password' => 'secret-password',
        'role' => 'Staff',
        'is_active' => true,
    ]);

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    test()->actingAs($user);

    return $user;
}

function pmMethod(string $code): PaymentMethod
{
    return PaymentMethod::where('code', $code)->firstOrFail();
}

/** A product with stock in a fresh warehouse, ready to be sold at the terminal. */
function pmStockedProduct(int $qty = 10): array
{
    $warehouse = Warehouse::create(['code' => 'W-'.uniqid(), 'name' => 'Gudang Uji']);
    $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Pupuk Uji']);
    app(StockService::class)->increase($product->id, $warehouse->id, $qty, 'SEED-'.uniqid());

    return [$product, $warehouse];
}

function pmSalePayload(Product $product, Warehouse $warehouse, array $extra = []): array
{
    return $extra + [
        'invoice_number' => 'INV-'.uniqid(),
        'sale_date' => now()->format('Y-m-d'),
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1000]],
    ];
}

// ---- Master data ---------------------------------------------------------------------

it('starts with tunai, transfer and QRIS, and only tunai counts as cash', function () {
    expect(PaymentMethod::orderBy('id')->pluck('name')->all())->toBe(['Tunai', 'Transfer', 'QRIS']);
    expect(PaymentMethod::where('is_cash', true)->pluck('code')->all())->toBe(['cash']);
    expect(PaymentMethod::where('is_active', false)->exists())->toBeFalse();
});

it('lets an authorised user add a method and gives it a code', function () {
    pmLogin(['finance.payment-methods.manage']);

    $this->post(route('finance.payment-methods.store'), ['name' => 'Kartu Debit', 'is_cash' => '0', 'is_active' => '1'])
        ->assertRedirect(route('finance.payment-methods.index'));

    $method = PaymentMethod::where('name', 'Kartu Debit')->firstOrFail();
    expect($method->code)->toBe('kartu_debit')
        ->and($method->is_cash)->toBeFalse()
        ->and($method->is_active)->toBeTrue();
});

it('refuses a duplicate name', function () {
    pmLogin(['finance.payment-methods.manage']);

    $this->post(route('finance.payment-methods.store'), ['name' => 'QRIS'])
        ->assertSessionHasErrors('name');

    expect(PaymentMethod::where('name', 'QRIS')->count())->toBe(1);
});

it('renames a method but keeps its code', function () {
    pmLogin(['finance.payment-methods.manage']);
    $method = pmMethod('qris');

    $this->put(route('finance.payment-methods.update', $method), ['name' => 'QRIS Toko', 'is_cash' => '0', 'is_active' => '1'])
        ->assertRedirect();

    expect($method->fresh()->name)->toBe('QRIS Toko')
        ->and($method->fresh()->code)->toBe('qris');
});

it('keeps at least one active cash method', function () {
    pmLogin(['finance.payment-methods.manage']);
    $cash = pmMethod('cash');

    // Deactivate, or turn off the cash flag: both would leave no cash method.
    $this->put(route('finance.payment-methods.update', $cash), ['name' => 'Tunai', 'is_cash' => '1', 'is_active' => '0'])
        ->assertSessionHasErrors('name');
    $this->put(route('finance.payment-methods.update', $cash), ['name' => 'Tunai', 'is_cash' => '0', 'is_active' => '1'])
        ->assertSessionHasErrors('name');
    $this->delete(route('finance.payment-methods.destroy', $cash))->assertSessionHas('error');

    expect($cash->fresh()->is_cash)->toBeTrue()->and($cash->fresh()->is_active)->toBeTrue();
});

it('allows deactivating tunai once another active cash method exists', function () {
    pmLogin(['finance.payment-methods.manage']);
    PaymentMethod::create(['code' => 'kas_kecil', 'name' => 'Kas Kecil', 'is_cash' => true, 'is_active' => true]);

    $this->put(route('finance.payment-methods.update', pmMethod('cash')), ['name' => 'Tunai', 'is_cash' => '1', 'is_active' => '0'])
        ->assertSessionHasNoErrors();

    expect(pmMethod('cash')->is_active)->toBeFalse();
});

it('does not delete a method that transactions use, but deletes an unused one', function () {
    pmLogin(['finance.payment-methods.manage']);
    $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Supplier Uji']);
    ApPayment::create([
        'payment_number' => 'AP-1', 'amount' => 100, 'payment_date' => now(),
        'payment_method_id' => pmMethod('qris')->id, 'supplier_id' => $supplier->id,
    ]);

    $this->delete(route('finance.payment-methods.destroy', pmMethod('qris')))->assertSessionHas('error');
    expect(PaymentMethod::where('code', 'qris')->exists())->toBeTrue();

    $this->delete(route('finance.payment-methods.destroy', pmMethod('bank_transfer')))->assertSessionHas('success');
    expect(PaymentMethod::where('code', 'bank_transfer')->exists())->toBeFalse();
});

it('keeps the master behind its permissions', function () {
    pmLogin(['finance.payment-methods.view']);

    $this->get(route('finance.payment-methods.index'))->assertOk()->assertSee('QRIS');
    $this->post(route('finance.payment-methods.store'), ['name' => 'Baru'])->assertForbidden();

    pmLogin();
    $this->get(route('finance.payment-methods.index'))->assertForbidden();
});

// ---- Checkout terminal ---------------------------------------------------------------

it('records the chosen method on a cash sale', function () {
    pmLogin(['transactions.point-of-sale-new.manage']);
    [$product, $warehouse] = pmStockedProduct();

    $this->post(route('transactions.point-of-sale-new.store'), pmSalePayload($product, $warehouse, [
        'payment_type' => 'cash',
        'payment_method_id' => pmMethod('qris')->id,
    ]))->assertSessionHasNoErrors();

    $sale = Sale::firstOrFail();
    expect($sale->payment_method_id)->toBe(pmMethod('qris')->id)
        ->and($sale->paymentMethod->name)->toBe('QRIS');
});

it('uses tunai when a cash sale names no method', function () {
    pmLogin(['transactions.point-of-sale-new.manage']);
    [$product, $warehouse] = pmStockedProduct();

    $this->post(route('transactions.point-of-sale-new.store'), pmSalePayload($product, $warehouse))
        ->assertSessionHasNoErrors();

    expect(Sale::firstOrFail()->payment_method_id)->toBe(pmMethod('cash')->id);
});

it('leaves the method empty on a credit sale, even if one is sent', function () {
    pmLogin(['transactions.point-of-sale-new.manage']);
    [$product, $warehouse] = pmStockedProduct();
    $customer = Customer::create(['code' => 'C-1', 'name' => 'Pelanggan Uji']);

    $this->post(route('transactions.point-of-sale-new.store'), pmSalePayload($product, $warehouse, [
        'payment_type' => 'credit',
        'customer_id' => $customer->id,
        'payment_method_id' => pmMethod('qris')->id,
    ]))->assertSessionHasNoErrors();

    expect(Sale::firstOrFail()->payment_method_id)->toBeNull();
});

it('refuses an inactive or unknown method at the terminal', function () {
    pmLogin(['transactions.point-of-sale-new.manage']);
    [$product, $warehouse] = pmStockedProduct();
    pmMethod('qris')->update(['is_active' => false]);

    $this->post(route('transactions.point-of-sale-new.store'), pmSalePayload($product, $warehouse, [
        'payment_method_id' => pmMethod('qris')->id,
    ]))->assertSessionHasErrors('payment_method_id');

    $this->post(route('transactions.point-of-sale-new.store'), pmSalePayload($product, $warehouse, [
        'payment_method_id' => 999999,
    ]))->assertSessionHasErrors('payment_method_id');

    expect(Sale::count())->toBe(0);
});

it('offers only active methods on the terminal', function () {
    pmLogin(['transactions.point-of-sale-new.view']);
    pmMethod('qris')->update(['is_active' => false]);

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertSee('Tunai')
        ->assertSee('Transfer')
        ->assertDontSee('QRIS');
});

// ---- Payable / receivable payments ---------------------------------------------------

it('records the method on a payable payment and refuses an inactive one for a new payment', function () {
    pmLogin(['transactions.payable-payments.manage']);
    $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Supplier Uji']);
    $warehouse = Warehouse::create(['code' => 'W-'.uniqid(), 'name' => 'Gudang Uji']);
    // A payable payment settles a received purchase invoice.
    $purchase = Purchase::create([
        'invoice_number' => 'PUR-'.uniqid(), 'purchase_date' => now()->format('Y-m-d'), 'total_amount' => 2000,
        'status' => 'received', 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
    ]);
    $payload = fn (int $methodId, string $number) => [
        'payment_number' => $number, 'amount' => 500, 'payment_date' => now()->format('Y-m-d'),
        'payment_method_id' => $methodId, 'supplier_id' => $supplier->id, 'purchase_id' => $purchase->id,
    ];

    $this->post(route('transactions.payable-payments.store'), $payload(pmMethod('qris')->id, 'AP-1'))
        ->assertSessionHasNoErrors();
    expect(ApPayment::firstOrFail()->payment_method_id)->toBe(pmMethod('qris')->id);

    pmMethod('qris')->update(['is_active' => false]);
    $this->post(route('transactions.payable-payments.store'), $payload(pmMethod('qris')->id, 'AP-2'))
        ->assertSessionHasErrors('payment_method_id');
    expect(ApPayment::count())->toBe(1);
});

it('lets a payable payment keep its method after the method is deactivated', function () {
    pmLogin(['transactions.payable-payments.manage']);
    $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Supplier Uji']);
    $payment = ApPayment::create([
        'payment_number' => 'AP-1', 'amount' => 500, 'payment_date' => now(),
        'payment_method_id' => pmMethod('qris')->id, 'supplier_id' => $supplier->id,
    ]);
    pmMethod('qris')->update(['is_active' => false]);

    $this->put(route('transactions.payable-payments.update', $payment), [
        'payment_number' => 'AP-1', 'amount' => 750, 'payment_date' => now()->format('Y-m-d'),
        'payment_method_id' => pmMethod('qris')->id, 'supplier_id' => $supplier->id,
    ])->assertSessionHasNoErrors();

    expect($payment->fresh()->amount)->toEqual(750);

    // ...but it can be switched to an active method, and then not back.
    $this->put(route('transactions.payable-payments.update', $payment), [
        'payment_number' => 'AP-1', 'amount' => 750, 'payment_date' => now()->format('Y-m-d'),
        'payment_method_id' => pmMethod('cash')->id, 'supplier_id' => $supplier->id,
    ])->assertSessionHasNoErrors();
    $this->put(route('transactions.payable-payments.update', $payment), [
        'payment_number' => 'AP-1', 'amount' => 750, 'payment_date' => now()->format('Y-m-d'),
        'payment_method_id' => pmMethod('qris')->id, 'supplier_id' => $supplier->id,
    ])->assertSessionHasErrors('payment_method_id');
});

it('records the method on a receivable payment', function () {
    pmLogin(['transactions.receivable-payments.manage']);
    $customer = Customer::create(['code' => 'C-1', 'name' => 'Pelanggan Uji']);

    $this->post(route('transactions.receivable-payments.store'), [
        'payment_number' => 'AR-1', 'amount' => 500, 'payment_date' => now()->format('Y-m-d'),
        'payment_method_id' => pmMethod('bank_transfer')->id, 'customer_id' => $customer->id,
    ])->assertSessionHasNoErrors();

    expect(ArPayment::firstOrFail()->paymentMethod->name)->toBe('Transfer');
});

it('requires a method on a receivable payment', function () {
    pmLogin(['transactions.receivable-payments.manage']);
    $customer = Customer::create(['code' => 'C-1', 'name' => 'Pelanggan Uji']);

    $this->post(route('transactions.receivable-payments.store'), [
        'payment_number' => 'AR-1', 'amount' => 500, 'payment_date' => now()->format('Y-m-d'),
        'customer_id' => $customer->id,
    ])->assertSessionHasErrors('payment_method_id');
});

// ---- Reports -------------------------------------------------------------------------

it('filters the payable payment report by method, including a deactivated one', function () {
    pmLogin(['reports.payable-payments.view']);
    $supplier = Supplier::create(['code' => 'S-1', 'name' => 'Supplier Uji']);
    foreach (['qris' => 'AP-QRIS-001', 'cash' => 'AP-CASH-002'] as $code => $number) {
        ApPayment::create([
            'payment_number' => $number, 'amount' => 100, 'payment_date' => now(),
            'payment_method_id' => pmMethod($code)->id, 'supplier_id' => $supplier->id,
        ]);
    }
    pmMethod('qris')->update(['is_active' => false]);

    $this->get(route('reports.payable-payments', ['payment_method_id' => pmMethod('qris')->id]))
        ->assertOk()
        ->assertSee('AP-QRIS-001')
        ->assertDontSee('AP-CASH-002');
});

it('filters the receivable payment report by method', function () {
    pmLogin(['reports.receivable-payments.view']);
    $customer = Customer::create(['code' => 'C-1', 'name' => 'Pelanggan Uji']);
    foreach (['bank_transfer' => 'AR-TRF-001', 'cash' => 'AR-CASH-002'] as $code => $number) {
        ArPayment::create([
            'payment_number' => $number, 'amount' => 100, 'payment_date' => now(),
            'payment_method_id' => pmMethod($code)->id, 'customer_id' => $customer->id,
        ]);
    }

    $this->get(route('reports.receivable-payments', ['payment_method_id' => pmMethod('bank_transfer')->id]))
        ->assertOk()
        ->assertSee('AR-TRF-001')
        ->assertDontSee('AR-CASH-002');
});
