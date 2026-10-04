<?php

use App\Models\ArPayment;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PayableService;
use App\Services\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

/* Helper names start with "pbal" so they cannot clash with other test files. */

function pbalLogin(array $permissions): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'pbal-'.uniqid(),
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

function pbalCreditSale(Customer $customer, float $total, string $date): Sale
{
    return Sale::create([
        'sale_date' => $date,
        'total_amount' => $total,
        'source' => 'sales',
        'payment_type' => Sale::PAYMENT_CREDIT,
        'customer_id' => $customer->id,
        'warehouse_id' => Warehouse::firstOrCreate(['name' => 'Gudang Saldo'])->id,
    ]);
}

function pbalReceive(Customer $customer, float $amount, string $date = '2026-10-03'): ArPayment
{
    return ArPayment::create([
        'payment_date' => $date,
        'amount' => $amount,
        'payment_method_id' => PaymentMethod::where('code', 'cash')->firstOrFail()->id,
        'customer_id' => $customer->id,
    ]);
}

// ---- What a customer owes -----------------------------------------------------------------------

it('works out what a customer owes, nets returns, and pays the oldest invoice first', function () {
    $customer = Customer::create(['name' => 'Toko Saldo']);

    $old = pbalCreditSale($customer, 1000, '2026-09-01');
    $new = pbalCreditSale($customer, 500, '2026-09-20');
    SalesReturn::create(['return_date' => '2026-09-25', 'total_amount' => 100, 'sale_id' => $new->id]);
    pbalReceive($customer, 1200);

    $balance = app(ReceivableService::class)->balances($customer->id)->get($customer->id);

    // Owed: 1000 + (500 - 100) = 1400; paid 1200 goes to the old invoice first (1000), then 200 of the new one.
    expect($balance['total'])->toBe(200.0)
        ->and($balance['invoices'])->toHaveCount(1)
        ->and($balance['invoices'][0])->toMatchArray([
            'id' => $new->id, 'net' => 400.0, 'paid' => 200.0, 'outstanding' => 200.0,
        ]);
});

it('leaves out invoices that are settled, fully returned, cash, or without a customer', function () {
    $customer = Customer::create(['name' => 'Toko Lunas']);

    pbalCreditSale($customer, 300, '2026-09-01');
    $returned = pbalCreditSale($customer, 200, '2026-09-02');
    SalesReturn::create(['return_date' => '2026-09-03', 'total_amount' => 200, 'sale_id' => $returned->id]);
    Sale::create(['sale_date' => '2026-09-04', 'total_amount' => 999, 'source' => 'sales', 'payment_type' => 'cash', 'customer_id' => $customer->id, 'warehouse_id' => Warehouse::first()->id]);
    pbalReceive($customer, 300);

    expect(app(ReceivableService::class)->balances($customer->id)->has($customer->id))->toBeFalse()
        ->and(app(ReceivableService::class)->totalsByCustomer())->toBe([]);
});

it('does not let one customer\'s payment lower another customer\'s balance', function () {
    $a = Customer::create(['name' => 'A']);
    $b = Customer::create(['name' => 'B']);
    pbalCreditSale($a, 700, '2026-09-01');
    pbalCreditSale($b, 400, '2026-09-01');
    pbalReceive($a, 700);
    pbalReceive($b, 100);

    expect(app(ReceivableService::class)->totalsByCustomer())->toBe([$b->id => 300.0]);
});

it('matches the total the dashboard uses', function () {
    $customer = Customer::create(['name' => 'Toko Cocok']);
    pbalCreditSale($customer, 900, '2026-09-01');
    pbalCreditSale($customer, 450, '2026-09-10');
    pbalReceive($customer, 600, '2026-09-15');

    $service = app(ReceivableService::class);

    expect(array_sum($service->totalsByCustomer()))->toBe($service->totalOutstanding('2026-12-31'));
});

it('leaves a payment out of the balance when it is being edited', function () {
    $customer = Customer::create(['name' => 'Toko Ubah']);
    pbalCreditSale($customer, 1000, '2026-09-01');
    $payment = pbalReceive($customer, 400);

    $service = app(ReceivableService::class);

    expect($service->balances($customer->id)->get($customer->id)['total'])->toBe(600.0)
        ->and($service->balances($customer->id, $payment->id)->get($customer->id)['total'])->toBe(1000.0);
});

// ---- The receivable payment page ---------------------------------------------------------------------

it('serves the amount a customer owes to the payment form', function () {
    pbalLogin(['transactions.receivable-payments.manage']);
    $customer = Customer::create(['name' => 'Toko Form']);
    $sale = pbalCreditSale($customer, 800, '2026-09-01');
    pbalReceive($customer, 300);

    $response = $this->getJson(route('transactions.receivable-payments.outstanding', ['customer_id' => $customer->id]))->assertOk();

    expect($response->json('total'))->toEqual(500)
        ->and($response->json('invoices'))->toHaveCount(1)
        ->and($response->json('invoices.0.invoice_number'))->toBe($sale->invoice_number)
        ->and($response->json('invoices.0.outstanding'))->toEqual(500);
});

it('says a customer with no credit sales owes nothing', function () {
    pbalLogin(['transactions.receivable-payments.manage']);
    $customer = Customer::create(['name' => 'Toko Kosong']);

    $this->getJson(route('transactions.receivable-payments.outstanding', ['customer_id' => $customer->id]))
        ->assertOk()
        ->assertJson(['total' => 0, 'invoices' => []]);
});

it('requires a customer and the page permission for the amount owed', function () {
    pbalLogin(['transactions.receivable-payments.manage']);
    $this->getJson(route('transactions.receivable-payments.outstanding'))->assertUnprocessable();

    pbalLogin(['customers.view']);
    $customer = Customer::create(['name' => 'Toko Izin']);
    $this->getJson(route('transactions.receivable-payments.outstanding', ['customer_id' => $customer->id]))->assertForbidden();
});

it('shows what each customer owes next to the name in the payment form', function () {
    pbalLogin(['transactions.receivable-payments.view']);
    $owing = Customer::create(['name' => 'Toko Berhutang']);
    Customer::create(['name' => 'Toko Bersih']);
    pbalCreditSale($owing, 1250.5, '2026-09-01');

    $this->get(route('transactions.receivable-payments.index'))
        ->assertOk()
        ->assertSee('Toko Berhutang — Piutang Rp1,250.50', false)
        ->assertSee('Toko Bersih — Tidak ada piutang', false)
        ->assertSee('id="balancePanel"', false)
        ->assertSee('js/payment-balance.js', false);
});

it('still saves receivable payments as before', function () {
    pbalLogin(['transactions.receivable-payments.view', 'transactions.receivable-payments.manage']);
    $customer = Customer::create(['name' => 'Toko Simpan']);

    $this->post(route('transactions.receivable-payments.store'), [
        'amount' => 250,
        'payment_date' => '2026-10-03',
        'payment_method_id' => PaymentMethod::where('code', 'cash')->firstOrFail()->id,
        'customer_id' => $customer->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(ArPayment::where('customer_id', $customer->id)->sum('amount'))->toEqual(250);
});

// ---- Payable payment page -------------------------------------------------------------------------------

function pbalPurchase(Supplier $supplier, float $total, string $status = 'received'): Purchase
{
    return Purchase::create([
        'purchase_date' => now()->subDays(5)->toDateString(),
        'total_amount' => $total,
        'status' => $status,
        'supplier_id' => $supplier->id,
        'warehouse_id' => Warehouse::firstOrCreate(['name' => 'Gudang Hutang'])->id,
    ]);
}

it('works out what is owed to each supplier from the open invoices', function () {
    $a = Supplier::create(['name' => 'CV A']);
    $b = Supplier::create(['name' => 'CV B']);

    $one = pbalPurchase($a, 1000);
    pbalPurchase($a, 500);
    pbalPurchase($a, 9999, 'pending');   // not received: not owed yet
    pbalPurchase($b, 300);
    PurchaseReturn::create(['return_date' => now()->toDateString(), 'total_amount' => 100, 'purchase_id' => $one->id]);

    $totals = app(PayableService::class)->totalsBySupplier();

    expect($totals[$a->id])->toBe(1400.0)   // 1000 - 100 returned + 500
        ->and($totals[$b->id])->toBe(300.0);
});

it('shows what is owed to each supplier next to the name in the payment form', function () {
    pbalLogin(['transactions.payable-payments.view']);
    $owed = Supplier::create(['name' => 'CV Ditagih']);
    Supplier::create(['name' => 'CV Bersih']);
    pbalPurchase($owed, 2000.25);

    $this->get(route('transactions.payable-payments.index'))
        ->assertOk()
        ->assertSee('CV Ditagih — Hutang Rp2,000.25', false)
        ->assertSee('CV Bersih — Tidak ada hutang', false)
        ->assertSee('id="balancePanel"', false)
        ->assertSee('js/payment-balance.js', false);
});
