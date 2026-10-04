<?php

use App\Models\Asset;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InternalMutation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

function nmLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'nm-'.uniqid(),
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

function nmCustomer(string $name = 'Toko Tani'): Customer
{
    return Customer::create(['name' => $name]);
}

function nmSalesOrder(Customer $customer, string $date = '2026-10-03', array $extra = []): SalesOrder
{
    return SalesOrder::create($extra + [
        'order_date' => $date,
        'status' => 'pending',
        'customer_id' => $customer->id,
    ]);
}

// ---- Document numbers: digits only ---------------------------------------------------------

it('numbers a document with type, date and a running number, digits only', function () {
    $customer = nmCustomer();

    $first = nmSalesOrder($customer, '2026-10-03');
    $second = nmSalesOrder($customer, '2026-10-03');

    // 13 = sales order, 261003 = 3 Oct 2026, then 0001, 0002.
    expect($first->so_number)->toBe('132610030001')
        ->and($second->so_number)->toBe('132610030002')
        ->and($first->so_number)->toMatch('/^\d{12}$/');
});

it('starts again at 0001 on a new day', function () {
    $customer = nmCustomer();

    nmSalesOrder($customer, '2026-10-03');
    $nextDay = nmSalesOrder($customer, '2026-10-04');

    expect($nextDay->so_number)->toBe('132610040001');
});

it('gives each kind of mutation its own type digits', function () {
    $make = fn (string $type) => InternalMutation::create([
        'type' => $type,
        'mutation_date' => '2026-10-03',
        'status' => 'pending',
    ])->mutation_number;

    expect($make('Transfer Antar Gudang'))->toBe('412610030001')
        ->and($make('Internal Receipt'))->toBe('422610030001')
        ->and($make('Internal Expenditure'))->toBe('432610030001')
        ->and($make('Deviation'))->toBe('442610030001')
        ->and($make('Item Request'))->toBe('452610030001')
        ->and($make('Deviation'))->toBe('442610030002');
});

it('keeps a number that is supplied and never hands out the same one twice', function () {
    $customer = nmCustomer();

    $typed = nmSalesOrder($customer, '2026-10-03', ['so_number' => '132610030001']);
    $generated = nmSalesOrder($customer, '2026-10-03');

    expect($typed->so_number)->toBe('132610030001')
        ->and($generated->so_number)->toBe('132610030002');
});

it('keeps the number of an existing document when an update leaves it blank', function () {
    $order = nmSalesOrder(nmCustomer());
    $number = $order->so_number;

    $order->update(['so_number' => null, 'status' => 'done']);

    expect($order->fresh()->so_number)->toBe($number)
        ->and($order->fresh()->status)->toBe('done');
});

it('leaves no gap when the save is rolled back', function () {
    $customer = nmCustomer();

    try {
        DB::transaction(function () use ($customer) {
            nmSalesOrder($customer, '2026-10-03');

            throw new RuntimeException('save failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(nmSalesOrder($customer, '2026-10-03')->so_number)->toBe('132610030001');
});

// ---- Master data codes ------------------------------------------------------------------------

it('gives master data a running, zero padded code', function () {
    expect(Customer::create(['name' => 'A'])->code)->toBe('00001')
        ->and(Customer::create(['name' => 'B'])->code)->toBe('00002')
        ->and(Product::create(['name' => 'Pupuk'])->code)->toBe('000001')
        ->and(Warehouse::create(['name' => 'Gudang 1'])->code)->toBe('001')
        ->and(Supplier::create(['name' => 'CV Subur'])->code)->toBe('0001')
        ->and(Asset::create(['name' => 'Timbangan', 'purchase_date' => '2026-10-01', 'value' => 1000])->asset_code)->toBe('0001');
});

it('shares one series between employees and salesmen', function () {
    $employee = Employee::create(['name' => 'Budi', 'position' => 'Admin']);
    $salesman = Employee::create(['name' => 'Sari', 'position' => 'Salesman']);

    expect($employee->code)->toBe('0001')->and($salesman->code)->toBe('0002');
});

it('starts after the highest numeric code that already exists and ignores other codes', function () {
    Supplier::create(['code' => 'SUP-A', 'name' => 'Lama']);
    Supplier::create(['code' => '120', 'name' => 'Angka']);

    expect(Supplier::create(['name' => 'Baru'])->code)->toBe('0121')
        ->and(Supplier::create(['name' => 'Baru 2'])->code)->toBe('0122');
});

// ---- Through the pages ------------------------------------------------------------------------

it('creates a customer with an automatic code and keeps the code when it is edited', function () {
    nmLogin(['customers.view', 'customers.manage']);

    $this->post(route('customers.store'), ['name' => 'Toko Subur'])->assertRedirect();

    $customer = Customer::where('name', 'Toko Subur')->firstOrFail();
    expect($customer->code)->toBe('00001');

    $this->put(route('customers.update', $customer), ['code' => '', 'name' => 'Toko Subur Jaya'])->assertRedirect();

    expect($customer->fresh()->code)->toBe('00001')
        ->and($customer->fresh()->name)->toBe('Toko Subur Jaya');
});

it('shows the code field as read only on the customer form', function () {
    nmLogin(['customers.view']);

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSee('id="code" name="code" type="text" readonly', false);
});

it('numbers a cashier sale automatically and uses that number in the stock ledger', function () {
    nmLogin(['transactions.point-of-sale-new.view', 'transactions.point-of-sale-new.manage']);

    $warehouse = Warehouse::create(['name' => 'Gudang Kasir']);
    $product = Product::create(['name' => 'Pupuk Kasir']);
    app(StockService::class)->increase($product->id, $warehouse->id, 20, 'SEED-NM');

    $payload = fn () => [
        'sale_date' => '2026-10-01',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 1500]],
    ];

    // A refused save also redirects, so check the session: if the sale was
    // refused, the failure now names the validation message that did it.
    $this->post(route('transactions.point-of-sale-new.store'), $payload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    $this->post(route('transactions.point-of-sale-new.store'), $payload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $numbers = Sale::orderBy('id')->pluck('invoice_number')->all();

    expect($numbers)->toBe(['112610010001', '112610010002'])
        ->and(DB::table('inventory_ledgers')->where('reference_number', '112610010001')->exists())->toBeTrue();
});

it('shows the invoice number field as read only on the cashier terminal', function () {
    nmLogin(['transactions.point-of-sale-new.view']);

    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertSee('id="invoice_number" name="invoice_number" type="text" readonly', false);
});
