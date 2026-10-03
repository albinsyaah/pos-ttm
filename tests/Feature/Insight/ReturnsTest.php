<?php

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PayableService;
use App\Services\ReceivableService;
use App\Services\SalesInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

function rtnLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'rtn-'.uniqid(),
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

function rtnProduct(string $name = 'Pupuk Urea'): Product
{
    return Product::create(['code' => 'P-'.uniqid(), 'name' => $name]);
}

function rtnWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(['code' => 'W-RTN'], ['name' => 'Gudang Induk']);
}

/** A sale with lines [[product, qty, price], ...]. Cash by default, today. */
function rtnSale(array $lines, array $attributes = []): Sale
{
    $sale = Sale::create($attributes + [
        'invoice_number' => 'INV-'.uniqid(),
        'sale_date' => Carbon::today()->toDateString(),
        'total_amount' => collect($lines)->sum(fn ($l) => $l[1] * $l[2]),
        'source' => 'pos',
        'payment_type' => Sale::PAYMENT_CASH,
        'payment_method_id' => PaymentMethod::where('code', 'cash')->value('id'),
        'warehouse_id' => rtnWarehouse()->id,
    ]);

    foreach ($lines as [$product, $qty, $price]) {
        $sale->saleDetails()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => $price]);
    }

    return $sale;
}

/** A return against a sale: value in rupiah and the returned lines [[product, qty], ...]. */
function rtnReturn(Sale $sale, float $amount, array $lines = [], ?string $date = null): SalesReturn
{
    $return = SalesReturn::create([
        'return_number' => 'RET-'.uniqid(),
        'return_date' => $date ?? Carbon::today()->toDateString(),
        'total_amount' => $amount,
        'sale_id' => $sale->id,
    ]);

    foreach ($lines as [$product, $qty]) {
        $return->salesReturnDetails()->create(['product_id' => $product->id, 'qty' => $qty]);
    }

    return $return;
}

function rtnCustomer(string $name = 'Toko Subur'): Customer
{
    return Customer::create(['code' => 'C-'.uniqid(), 'name' => $name]);
}

function rtnPurchase(float $total, string $status = 'received', ?string $date = null): Purchase
{
    $supplier = Supplier::firstOrCreate(['code' => 'S-RTN'], ['name' => 'Supplier Rtn']);
    $product = rtnProduct('Beli '.uniqid());

    $purchase = Purchase::create([
        'invoice_number' => 'PUR-'.uniqid(),
        'purchase_date' => $date ?? Carbon::today()->toDateString(),
        'total_amount' => $total,
        'status' => $status,
        'supplier_id' => $supplier->id,
        'warehouse_id' => rtnWarehouse()->id,
    ]);
    $purchase->purchaseDetails()->create(['product_id' => $product->id, 'qty' => 10, 'price' => $total / 10]);

    return $purchase;
}

// ---- Dashboard sales cards ---------------------------------------------------------------

it('subtracts sales returns from the dashboard sales, on the day the return was made', function () {
    rtnLogin(['dashboard.view']);
    $urea = rtnProduct();

    $sale = rtnSale([[$urea, 10, 1000]]);                    // 10.000 today
    rtnReturn($sale, 3000, [[$urea, 3]]);                    // returned today

    $service = app(SalesInsightService::class);
    $today = Carbon::today()->toDateString();

    expect($service->salesTotal($today, $today))->toMatchArray([
        'count' => 1, 'gross' => 10000.0, 'returns' => 3000.0, 'total' => 7000.0,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('todaySales', fn ($v) => (float) $v === 7000.0)
        ->assertViewHas('weeklySales', fn ($v) => (float) $v === 7000.0)
        ->assertViewHas('monthSales', fn ($v) => (float) $v === 7000.0);
});

it('counts a return on its own day, not on the day of the original sale', function () {
    $urea = rtnProduct();
    $oldSale = rtnSale([[$urea, 10, 1000]], ['sale_date' => Carbon::today()->subDays(40)->toDateString()]);
    rtnReturn($oldSale, 4000, [[$urea, 4]]);                 // today, for a sale 40 days ago

    $service = app(SalesInsightService::class);
    $today = Carbon::today()->toDateString();

    expect($service->salesTotal($today, $today)['total'])->toBe(-4000.0);
});

it('does not let an old return reduce a day it was not made on', function () {
    $urea = rtnProduct();
    $sale = rtnSale([[$urea, 10, 1000]]);
    rtnReturn($sale, 3000, [[$urea, 3]], Carbon::today()->subDays(8)->toDateString());

    $today = Carbon::today()->toDateString();

    expect(app(SalesInsightService::class)->salesTotal($today, $today)['total'])->toBe(10000.0);
});

it('nets the best sellers by the units returned, and ignores free lines', function () {
    $urea = rtnProduct('Pupuk Urea');
    $npk = rtnProduct('Pupuk NPK');
    $gone = rtnProduct('Pupuk Habis');

    $sale = rtnSale([[$urea, 10, 1000], [$npk, 8, 1000], [$gone, 5, 1000], [$npk, 2, 0]]);
    rtnReturn($sale, 4000, [[$urea, 4]]);        // urea 10 -> 6
    rtnReturn($sale, 5000, [[$gone, 5]]);        // fully returned: drops out
    rtnReturn($sale, 0, [[$npk, 2]]);            // npk 8 paid -> 6 (the return goes to the paid line)

    $top = app(SalesInsightService::class)->topProducts(Carbon::today()->toDateString(), Carbon::today()->toDateString());
    $byName = collect($top)->mapWithKeys(fn ($row) => [$row['product']->name => $row]);

    expect($byName->keys()->all())->toBe(['Pupuk Urea', 'Pupuk NPK'])
        ->and($byName['Pupuk Urea']['sold'])->toBe(6)
        ->and($byName['Pupuk Urea']['revenue'])->toBe(6000.0)
        ->and($byName['Pupuk NPK']['sold'])->toBe(6);
});

it('puts returns into the weekly chart on the day they were made', function () {
    rtnLogin(['dashboard.view']);
    $urea = rtnProduct();
    $sale = rtnSale([[$urea, 10, 1000]]);
    rtnReturn($sale, 2500, [[$urea, 2]]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('days', function ($days) {
            $today = Carbon::today()->format('D');

            return collect($days)->firstWhere('label', $today)['this'] === 7500.0;
        });
});

it('nets the top customers by the returns on their invoices', function () {
    rtnLogin(['dashboard.view']);
    $urea = rtnProduct();
    $customer = rtnCustomer('Toko Subur');
    $sale = rtnSale([[$urea, 10, 1000]], ['customer_id' => $customer->id]);
    rtnReturn($sale, 4000, [[$urea, 4]]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('topCustomers', fn ($rows) => (float) $rows[0]->total === 6000.0);
});

// ---- Income by payment method ------------------------------------------------------------

it('refunds a cash sale return from the method the sale was paid with', function () {
    $urea = rtnProduct();
    $cash = PaymentMethod::where('code', 'cash')->value('id');
    $qris = PaymentMethod::where('code', 'qris')->value('id');

    $cashSale = rtnSale([[$urea, 10, 1000]], ['payment_method_id' => $cash]);
    rtnSale([[$urea, 20, 1000]], ['payment_method_id' => $qris]);
    rtnReturn($cashSale, 3000, [[$urea, 3]]);

    $rows = collect(app(SalesInsightService::class)->incomeByMethod())->keyBy('name');

    expect($rows['Tunai']['cash_sales'])->toBe(10000.0)
        ->and($rows['Tunai']['returns'])->toBe(3000.0)
        ->and($rows['Tunai']['income'])->toBe(7000.0)
        ->and($rows['QRIS']['income'])->toBe(20000.0);
});

it('does not take money out of a method for a return of a credit sale', function () {
    $urea = rtnProduct();
    $customer = rtnCustomer();
    $creditSale = rtnSale([[$urea, 10, 1000]], [
        'payment_type' => Sale::PAYMENT_CREDIT, 'payment_method_id' => null, 'customer_id' => $customer->id,
    ]);
    rtnReturn($creditSale, 3000, [[$urea, 3]]);

    $rows = collect(app(SalesInsightService::class)->incomeByMethod());

    expect($rows->sum('income'))->toBe(0.0)
        ->and($rows->sum('returns'))->toBe(0.0);
});

it('shows the cash refunds column in the payment method report', function () {
    rtnLogin(['reports.payment-methods.view']);
    $urea = rtnProduct();
    $sale = rtnSale([[$urea, 10, 1000]]);
    rtnReturn($sale, 3000, [[$urea, 3]]);

    $this->get(route('reports.payment-methods'))
        ->assertOk()
        ->assertSee('Retur Tunai')
        ->assertViewHas('totals', fn ($t) => $t['income'] === 7000.0 && $t['returns'] === 3000.0);
});

// ---- Sales reports ------------------------------------------------------------------------

it('nets the sales summary report and shows the returns per invoice', function () {
    rtnLogin(['reports.sales-summary.view']);
    $urea = rtnProduct();

    $sale = rtnSale([[$urea, 10, 1000]], ['source' => 'pos']);
    rtnSale([[$urea, 5, 1000]], ['source' => 'spg']);
    rtnReturn($sale, 3000, [[$urea, 3]]);

    $this->get(route('reports.sales-summary'))
        ->assertOk()
        ->assertSee('Retur Penjualan')
        ->assertViewHas('grossAmount', fn ($v) => $v === 15000.0)
        ->assertViewHas('returnsTotal', fn ($v) => $v === 3000.0)
        ->assertViewHas('totalAmount', fn ($v) => $v === 12000.0)
        ->assertViewHas('bySource', fn ($rows) => (float) $rows['pos']->total_amount === 7000.0 && (float) $rows['spg']->total_amount === 5000.0)
        ->assertViewHas('sales', fn ($page) => (float) $page->getCollection()->firstWhere('id', $sale->id)->returned_total === 3000.0);
});

it('nets the sales report only over the invoices the filters keep', function () {
    rtnLogin(['reports.sales.view']);
    $urea = rtnProduct();

    $old = rtnSale([[$urea, 10, 1000]], ['source' => 'sales', 'sale_date' => '2026-09-01']);
    $new = rtnSale([[$urea, 10, 1000]], ['source' => 'sales', 'sale_date' => '2026-10-01']);
    rtnReturn($old, 1000, [[$urea, 1]]);
    rtnReturn($new, 2000, [[$urea, 2]]);

    $this->get(route('reports.sales', ['date_from' => '2026-10-01']))
        ->assertOk()
        ->assertViewHas('returnsTotal', fn ($v) => $v === 2000.0)
        ->assertViewHas('totalAmount', fn ($v) => $v === 8000.0);
});

it('nets the sales by product report by the paid line the return came from', function () {
    rtnLogin(['reports.sales-by-product.view']);
    $urea = rtnProduct('Pupuk Urea');

    $sale = rtnSale([[$urea, 10, 5000], [$urea, 2, 0]]);
    rtnReturn($sale, 15000, [[$urea, 3]]);

    $this->get(route('reports.sales-by-product'))
        ->assertOk()
        ->assertViewHas('rows', function ($rows) {
            $rows = collect($rows->items());
            $paid = $rows->first(fn ($r) => (float) $r->unit_price === 5000.0);
            $free = $rows->first(fn ($r) => (float) $r->unit_price === 0.0);

            return (int) $paid->qty === 7 && (int) $paid->returned_qty === 3 && (float) $paid->revenue === 35000.0
                && (int) $free->qty === 2 && (int) $free->returned_qty === 0;
        })
        ->assertViewHas('totalQty', 9)
        ->assertViewHas('totalRevenue', fn ($v) => (float) $v === 35000.0);
});

it('nets the salesman report and shows the returned quantity', function () {
    rtnLogin(['reports.salesman.view']);
    $urea = rtnProduct('Pupuk Urea');
    $budi = Employee::create(['code' => 'E-RTN', 'name' => 'Budi', 'position' => 'Salesman']);

    $sale = rtnSale([[$urea, 10, 5000]], ['salesman_id' => $budi->id]);
    rtnReturn($sale, 10000, [[$urea, 2]]);

    $this->get(route('reports.salesman'))
        ->assertOk()
        ->assertViewHas('groups', function ($groups) {
            $row = $groups[0]['rows'][0];

            return $row['paid_qty'] === 8 && $row['returned_qty'] === 2 && $row['revenue'] === 40000.0
                && $groups[0]['revenue'] === 40000.0;
        });
});

// ---- Payables and receivables on the dashboard --------------------------------------------

it('shows on the dashboard the same payables as the per-invoice figures', function () {
    rtnLogin(['dashboard.view']);

    $first = rtnPurchase(100000);
    $second = rtnPurchase(50000);
    rtnPurchase(70000, 'pending');                    // not payable yet
    rtnPurchase(30000, 'cancelled');                  // never payable

    PurchaseReturn::create([
        'return_number' => 'PR-'.uniqid(), 'return_date' => Carbon::today()->toDateString(),
        'total_amount' => 20000, 'purchase_id' => $first->id,
    ]);
    ApPayment::create([
        'payment_number' => 'AP-'.uniqid(), 'amount' => 30000, 'payment_date' => Carbon::today()->toDateString(),
        'payment_method_id' => PaymentMethod::where('code', 'cash')->value('id'),
        'supplier_id' => $first->supplier_id, 'purchase_id' => $first->id,
    ]);

    // 100.000 - 20.000 returned - 30.000 paid = 50.000, plus the second invoice 50.000.
    expect(app(PayableService::class)->totalOutstanding())->toBe(100000.0);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('payablesOutstanding', fn ($v) => (float) $v === 100000.0);
});

it('shows on the dashboard the same receivables as the aging report: net of returns and payments', function () {
    rtnLogin(['dashboard.view']);
    $urea = rtnProduct();
    $subur = rtnCustomer('Toko Subur');
    $makmur = rtnCustomer('Toko Makmur');

    $a = rtnSale([[$urea, 10, 10000]], ['payment_type' => Sale::PAYMENT_CREDIT, 'payment_method_id' => null, 'customer_id' => $subur->id]);
    rtnSale([[$urea, 5, 10000]], ['payment_type' => Sale::PAYMENT_CREDIT, 'payment_method_id' => null, 'customer_id' => $makmur->id]);
    rtnSale([[$urea, 9, 10000]]);                                       // cash sale: never a receivable
    rtnReturn($a, 20000, [[$urea, 2]]);

    foreach ([[$subur, 30000], [$makmur, 80000]] as [$customer, $amount]) {      // Makmur paid more than it owes
        ArPayment::create([
            'payment_number' => 'AR-'.uniqid(), 'amount' => $amount, 'payment_date' => Carbon::today()->toDateString(),
            'payment_method_id' => PaymentMethod::where('code', 'cash')->value('id'), 'customer_id' => $customer->id,
        ]);
    }

    // Subur: 100.000 - 20.000 - 30.000 = 50.000. Makmur: 50.000 - 80.000 -> 0, not -30.000.
    expect(app(ReceivableService::class)->totalOutstanding())->toBe(50000.0);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('receivablesOutstanding', fn ($v) => (float) $v === 50000.0);
});
