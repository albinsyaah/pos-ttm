<?php

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\PriceHistory;
use App\Models\PriceSetup;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesInsightService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// Teks halaman diperiksa dalam bahasa Indonesia; locale bawaan aplikasi 'en'.
beforeEach(fn () => config(['app.locale' => 'id']));

function insLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'ins-'.uniqid(),
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

function insWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(['code' => 'W-INS'], ['name' => 'Gudang Induk']);
}

function insProduct(string $name = 'Pupuk Urea'): Product
{
    return Product::create(['code' => 'P-'.uniqid(), 'name' => $name]);
}

function insMethod(string $code): PaymentMethod
{
    return PaymentMethod::where('code', $code)->firstOrFail();
}

/** One sale with the given lines: [[product, qty, price], ...]. */
function insSale(array $lines, array $attributes = []): Sale
{
    $total = collect($lines)->sum(fn ($l) => $l[1] * $l[2]);

    $sale = Sale::create($attributes + [
        'invoice_number' => 'INV-'.uniqid(),
        'sale_date' => Carbon::today()->toDateString(),
        'total_amount' => $total,
        'source' => 'pos',
        'payment_type' => Sale::PAYMENT_CASH,
        'payment_method_id' => insMethod('cash')->id,
        'warehouse_id' => insWarehouse()->id,
    ]);

    foreach ($lines as [$product, $qty, $price]) {
        $sale->saleDetails()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => $price]);
    }

    return $sale;
}

/** A received purchase of one product at a price, on a date. */
function insPurchase(Product $product, float $price, string $date, string $status = 'received'): Purchase
{
    $supplier = Supplier::firstOrCreate(['code' => 'S-INS'], ['name' => 'Supplier Ins']);

    $purchase = Purchase::create([
        'invoice_number' => 'PUR-'.uniqid(),
        'purchase_date' => $date,
        'total_amount' => $price * 10,
        'status' => $status,
        'supplier_id' => $supplier->id,
        'warehouse_id' => insWarehouse()->id,
    ]);
    $purchase->purchaseDetails()->create(['product_id' => $product->id, 'qty' => 10, 'price' => $price]);

    return $purchase;
}

/** Create a price setup and log it, the way the price screen does. */
function insPrice(Product $product, float $amount, string $date, string $category = 'Retail'): PriceSetup
{
    $setup = PriceSetup::create([
        'product_id' => $product->id, 'price_category' => $category, 'amount' => $amount, 'effective_date' => $date,
    ]);
    PriceHistory::record(PriceHistory::CREATED, null, $setup);

    return $setup;
}

/** Change a price setup and log it. */
function insReprice(PriceSetup $setup, float $amount, ?string $date = null): PriceSetup
{
    $before = clone $setup;
    $setup->update(['amount' => $amount] + ($date ? ['effective_date' => $date] : []));
    PriceHistory::record(PriceHistory::UPDATED, $before, $setup->fresh());

    return $setup;
}

// ---- Date ranges -------------------------------------------------------------------------

it('works out the day, week and month ranges', function () {
    $wednesday = Carbon::parse('2026-10-07'); // week: Mon 5 Oct to Sun 11 Oct

    expect(SalesInsightService::bounds('day', $wednesday))->toBe(['2026-10-07', '2026-10-07'])
        ->and(SalesInsightService::bounds('week', $wednesday))->toBe(['2026-10-05', '2026-10-11'])
        ->and(SalesInsightService::bounds('month', $wednesday))->toBe(['2026-10-01', '2026-10-31'])
        ->and(SalesInsightService::normalizeRange('year'))->toBe('day')
        ->and(SalesInsightService::normalizeRange(null))->toBe('day')
        ->and(SalesInsightService::normalizeRange('week'))->toBe('week');
});

// ---- Cost of free items ------------------------------------------------------------------

it('costs a free item at the last purchase price on or before the sale date', function () {
    $urea = insProduct();
    insPurchase($urea, 4000, '2026-09-01');
    insPurchase($urea, 4500, '2026-09-20');
    insPurchase($urea, 9999, '2026-10-20');
    insPurchase($urea, 7777, '2026-09-25', 'pending');

    $service = app(SalesInsightService::class);
    $book = $service->purchaseCostBook([$urea->id]);

    expect($service->costOn($book, $urea->id, '2026-09-10'))->toBe(4000.0)
        ->and($service->costOn($book, $urea->id, '2026-09-20'))->toBe(4500.0)
        ->and($service->costOn($book, $urea->id, '2026-10-05'))->toBe(4500.0)
        // Sold before the first purchase: the earliest known price.
        ->and($service->costOn($book, $urea->id, '2026-08-01'))->toBe(4000.0);
});

it('has no cost for a product that was never bought', function () {
    $service = app(SalesInsightService::class);
    $never = insProduct('Tanpa Pembelian');

    expect($service->costOn($service->purchaseCostBook([$never->id]), $never->id, '2026-10-01'))->toBeNull();
});

it('adds the free-goods loss to the sales summary report', function () {
    insLogin(['reports.sales-summary.view']);
    $urea = insProduct('Pupuk Urea');
    $npk = insProduct('Pupuk NPK');
    insPurchase($urea, 4000, '2026-09-01');

    $sale = insSale([[$urea, 5, 5000], [$urea, 2, 0], [$npk, 1, 0]], ['sale_date' => '2026-10-01']);

    $this->get(route('reports.sales-summary'))
        ->assertOk()
        ->assertViewHas('freeLoss', function ($loss) use ($sale, $urea, $npk) {
            return $loss['total'] === 8000.0                       // 2 x 4000, NPK was never bought
                && $loss['by_sale'][$sale->id] === 8000.0
                && count($loss['by_product']) === 2
                && $loss['by_product'][0]['product']->id === $urea->id
                && $loss['by_product'][1]['unknown_cost'] === true;
        })
        ->assertSee('Rp 8.000')
        ->assertSee('Rincian Barang Gratis')
        ->assertSee('belum ada harga beli');
});

it('only counts the free-goods loss of the sales the filters keep', function () {
    insLogin(['reports.sales-summary.view']);
    $urea = insProduct();
    insPurchase($urea, 4000, '2026-08-01');

    insSale([[$urea, 1, 0]], ['sale_date' => '2026-09-01']);
    insSale([[$urea, 3, 0]], ['sale_date' => '2026-10-01']);

    $this->get(route('reports.sales-summary', ['date_from' => '2026-10-01']))
        ->assertOk()
        ->assertViewHas('freeLoss', fn ($loss) => $loss['total'] === 12000.0);
});

it('shows no free-goods section when nothing was given away', function () {
    insLogin(['reports.sales-summary.view']);
    insSale([[insProduct(), 1, 5000]]);

    $this->get(route('reports.sales-summary'))
        ->assertOk()
        ->assertViewHas('freeLoss', fn ($loss) => $loss['total'] === 0.0)
        ->assertDontSee('Rincian Barang Gratis');
});

// ---- Income per payment method -----------------------------------------------------------

it('adds cash sales and receivable payments per method, and keeps supplier payments apart', function () {
    $service = app(SalesInsightService::class);
    $urea = insProduct();
    $customer = Customer::create(['code' => 'C-INS', 'name' => 'Toko Subur']);
    $supplier = Supplier::create(['code' => 'S-INS', 'name' => 'Supplier Ins']);

    insSale([[$urea, 1, 10000]], ['payment_method_id' => insMethod('cash')->id]);
    insSale([[$urea, 1, 20000]], ['payment_method_id' => insMethod('qris')->id]);
    insSale([[$urea, 1, 30000]], ['payment_type' => Sale::PAYMENT_CREDIT, 'payment_method_id' => null, 'customer_id' => $customer->id]);

    ArPayment::create([
        'payment_number' => 'AR-1', 'amount' => 5000, 'payment_date' => Carbon::today()->toDateString(),
        'payment_method_id' => insMethod('bank_transfer')->id, 'customer_id' => $customer->id,
    ]);
    ApPayment::create([
        'payment_number' => 'AP-1', 'amount' => 7000, 'payment_date' => Carbon::today()->toDateString(),
        'payment_method_id' => insMethod('cash')->id, 'supplier_id' => $supplier->id,
    ]);

    $byName = collect($service->incomeByMethod())->keyBy('name');

    expect($byName['Tunai']['cash_sales'])->toBe(10000.0)
        ->and($byName['Tunai']['income'])->toBe(10000.0)
        ->and($byName['Tunai']['ap_paid'])->toBe(7000.0)
        ->and($byName['QRIS']['income'])->toBe(20000.0)
        ->and($byName['Transfer']['ar_received'])->toBe(5000.0)
        ->and($byName['Transfer']['income'])->toBe(5000.0)
        // The credit sale (30.000) is not income until it is paid.
        ->and(collect($service->incomeByMethod())->sum('income'))->toBe(35000.0);
});

it('counts a cash sale saved without a method as the default cash method', function () {
    insSale([[insProduct(), 1, 12000]], ['payment_method_id' => null]);

    $rows = collect(app(SalesInsightService::class)->incomeByMethod())->keyBy('name');

    expect($rows['Tunai']['cash_sales'])->toBe(12000.0)
        ->and($rows['Tunai']['sales_count'])->toBe(1);
});

it('limits the income to the date range', function () {
    $urea = insProduct();
    insSale([[$urea, 1, 1000]], ['sale_date' => '2026-09-30']);
    insSale([[$urea, 1, 2000]], ['sale_date' => '2026-10-01']);

    $rows = collect(app(SalesInsightService::class)->incomeByMethod('2026-10-01', '2026-10-31'))->keyBy('name');

    expect($rows['Tunai']['income'])->toBe(2000.0);
});

it('hides a retired method that has no movement but lists an active one at zero', function () {
    PaymentMethod::create(['code' => 'giro', 'name' => 'Giro', 'is_cash' => false, 'is_active' => false]);

    $names = collect(app(SalesInsightService::class)->incomeByMethod())->pluck('name')->all();

    expect($names)->toContain('Tunai', 'Transfer', 'QRIS')->not->toContain('Giro');
});

it('opens the payment method report only with its permission', function () {
    insLogin();
    $this->get(route('reports.payment-methods'))->assertForbidden();

    insLogin(['reports.payment-methods.view']);
    insSale([[insProduct(), 1, 15000]]);

    $this->get(route('reports.payment-methods'))
        ->assertOk()
        ->assertSee('Laporan Metode Pembayaran')
        ->assertSee('Rp 15.000')
        ->assertViewHas('totals', fn ($totals) => $totals['income'] === 15000.0);
});

it('filters the payment method report to one method', function () {
    insLogin(['reports.payment-methods.view']);
    $urea = insProduct();
    insSale([[$urea, 1, 1000]], ['payment_method_id' => insMethod('cash')->id]);
    insSale([[$urea, 1, 2000]], ['payment_method_id' => insMethod('qris')->id]);

    $this->get(route('reports.payment-methods', ['payment_method_id' => insMethod('qris')->id]))
        ->assertOk()
        ->assertViewHas('rows', fn ($rows) => count($rows) === 1 && $rows[0]['name'] === 'QRIS');
});

// ---- Dashboard ---------------------------------------------------------------------------

it('shows day, week and month sales on the dashboard', function () {
    insLogin(['dashboard.view']);
    $urea = insProduct();

    insSale([[$urea, 1, 1000]]);                                                  // today
    insSale([[$urea, 1, 2000]], ['sale_date' => Carbon::today()->subDays(8)->toDateString()]);  // before this week
    insSale([[$urea, 1, 4000]], ['sale_date' => Carbon::today()->subDays(40)->toDateString()]); // before this month

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('todaySales', fn ($v) => (float) $v === 1000.0)
        ->assertViewHas('weeklySales', fn ($v) => (float) $v === 1000.0)
        ->assertSee('id="cardDaySales"', false)
        ->assertSee('id="cardWeekSales"', false)
        ->assertSee('id="cardMonthSales"', false);
});

it('does not mix other years into the month figure', function () {
    insLogin(['dashboard.view']);
    $urea = insProduct();

    insSale([[$urea, 1, 1000]]);
    insSale([[$urea, 1, 9000]], ['sale_date' => Carbon::today()->subYear()->toDateString()]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('monthSales', fn ($v) => (float) $v === 1000.0);
});

it('lists the ten best sellers of the chosen range, free items not counted', function () {
    insLogin(['dashboard.view']);

    $products = collect(range(1, 12))->map(fn ($i) => insProduct('Barang '.str_pad($i, 2, '0', STR_PAD_LEFT)));
    foreach ($products as $i => $product) {
        insSale([[$product, $i + 1, 1000]]);   // Barang 12 sold the most
    }
    insSale([[$products[0], 500, 0]]);          // a pile of free items must not make it a best seller

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('topProducts', function ($top) {
            return count($top) === 10
                && $top[0]['product']->name === 'Barang 12'
                && $top[0]['sold'] === 12
                && ! collect($top)->contains(fn ($row) => $row['product']->name === 'Barang 01');
        })
        ->assertSee('10 Produk Terlaris');
});

it('switches the best sellers and income by range, and ignores an unknown range', function () {
    insLogin(['dashboard.view']);
    $urea = insProduct('Pupuk Urea');
    $npk = insProduct('Pupuk NPK');

    insSale([[$urea, 1, 1000]]);
    insSale([[$npk, 1, 5000]], ['sale_date' => Carbon::today()->subDays(40)->toDateString()]);

    $this->get(route('dashboard', ['range' => 'day']))
        ->assertViewHas('range', 'day')
        ->assertViewHas('topProducts', fn ($top) => count($top) === 1 && $top[0]['product']->name === 'Pupuk Urea');

    $this->get(route('dashboard', ['range' => 'month']))
        ->assertViewHas('range', 'month')
        ->assertViewHas('topProducts', fn ($top) => count($top) === 1);

    $this->get(route('dashboard', ['range' => 'forever']))
        ->assertOk()
        ->assertViewHas('range', 'day');
});

it('shows the income per payment method on the dashboard', function () {
    insLogin(['dashboard.view']);
    $urea = insProduct();
    insSale([[$urea, 1, 10000]], ['payment_method_id' => insMethod('cash')->id]);
    insSale([[$urea, 1, 25000]], ['payment_method_id' => insMethod('qris')->id]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Penghasilan per Metode Pembayaran')
        ->assertSee('Rp 25.000')
        ->assertSee('Rp 35.000')
        ->assertViewHas('incomeByMethod', fn ($rows) => collect($rows)->firstWhere('name', 'QRIS')['income'] === 25000.0);
});

// ---- Salesman report ---------------------------------------------------------------------

it('lists what each salesman sold, with free quantity apart', function () {
    insLogin(['reports.salesman.view']);
    $urea = insProduct('Pupuk Urea');
    $npk = insProduct('Pupuk NPK');
    $budi = Employee::create(['code' => 'E-1', 'name' => 'Budi', 'position' => 'Salesman']);
    $sari = Employee::create(['code' => 'E-2', 'name' => 'Sari', 'position' => 'Salesman']);

    insSale([[$urea, 3, 5000], [$urea, 1, 0], [$npk, 2, 2000]], ['salesman_id' => $budi->id]);
    insSale([[$urea, 2, 5000]], ['salesman_id' => $budi->id]);
    insSale([[$npk, 4, 2000]], ['salesman_id' => $sari->id]);
    insSale([[$npk, 9, 2000]]); // no salesman: not in this report

    $this->get(route('reports.salesman'))
        ->assertOk()
        ->assertSee('Laporan Salesman')
        ->assertViewHas('groups', function ($groups) use ($budi) {
            $b = $groups->firstWhere('salesman.id', $budi->id);
            $urea = $b['rows']->first(fn ($r) => $r['product']->name === 'Pupuk Urea');

            return $groups->count() === 2
                && $b['sales_count'] === 2
                && $urea['paid_qty'] === 5
                && $urea['free_qty'] === 1
                && $urea['revenue'] === 25000.0
                && $b['revenue'] === 29000.0;
        })
        ->assertViewHas('grandTotal', fn ($t) => (float) $t === 37000.0);
});

it('filters the salesman report by salesman, product and date', function () {
    insLogin(['reports.salesman.view']);
    $urea = insProduct('Pupuk Urea');
    $npk = insProduct('Pupuk NPK');
    $budi = Employee::create(['code' => 'E-1', 'name' => 'Budi', 'position' => 'Salesman']);
    $sari = Employee::create(['code' => 'E-2', 'name' => 'Sari', 'position' => 'Salesman']);

    insSale([[$urea, 1, 5000]], ['salesman_id' => $budi->id, 'sale_date' => '2026-09-01']);
    insSale([[$npk, 1, 2000]], ['salesman_id' => $budi->id, 'sale_date' => '2026-10-01']);
    insSale([[$npk, 1, 2000]], ['salesman_id' => $sari->id, 'sale_date' => '2026-10-01']);

    $this->get(route('reports.salesman', ['salesman_id' => $budi->id]))
        ->assertViewHas('groups', fn ($g) => $g->count() === 1 && $g[0]['salesman']->id === $budi->id);

    $this->get(route('reports.salesman', ['q' => 'urea']))
        ->assertViewHas('grandTotal', fn ($t) => (float) $t === 5000.0);

    $this->get(route('reports.salesman', ['date_from' => '2026-10-01']))
        ->assertViewHas('grandTotal', fn ($t) => (float) $t === 4000.0);
});

it('keeps the salesman report behind its permission', function () {
    insLogin();
    $this->get(route('reports.salesman'))->assertForbidden();
});

// ---- Sales by product, following the price -----------------------------------------------

it('shows one row per product and selling price so a price change is visible', function () {
    insLogin(['reports.sales-by-product.view']);
    $urea = insProduct('Pupuk Urea');

    insSale([[$urea, 10, 5000]], ['sale_date' => '2026-09-01']);
    insSale([[$urea, 4, 5000]], ['sale_date' => '2026-09-10']);
    insSale([[$urea, 6, 5500]], ['sale_date' => '2026-10-01']);
    insSale([[$urea, 2, 0]], ['sale_date' => '2026-10-01']);

    $this->get(route('reports.sales-by-product'))
        ->assertOk()
        ->assertSee('Laporan Penjualan per Barang')
        ->assertSee('harga berubah')
        ->assertViewHas('rows', function ($rows) {
            $rows = collect($rows->items());
            $at5000 = $rows->first(fn ($r) => (float) $r->unit_price === 5000.0);
            $at5500 = $rows->first(fn ($r) => (float) $r->unit_price === 5500.0);
            $free = $rows->first(fn ($r) => (float) $r->unit_price === 0.0);

            return $rows->count() === 3
                && (int) $at5000->qty === 14 && (float) $at5000->revenue === 70000.0 && (int) $at5000->sales_count === 2
                && (int) $at5500->qty === 6 && (float) $at5500->revenue === 33000.0
                && (int) $free->qty === 2 && (float) $free->revenue === 0.0;
        })
        ->assertViewHas('totalRevenue', fn ($t) => (float) $t === 103000.0)
        ->assertViewHas('totalQty', 22);
});

it('does not flag a price change when the product sold at one price only', function () {
    insLogin(['reports.sales-by-product.view']);
    insSale([[insProduct('Pupuk Urea'), 5, 5000]]);
    insSale([[insProduct('Pupuk NPK'), 5, 2000]]);

    $this->get(route('reports.sales-by-product'))
        ->assertOk()
        ->assertViewHas('priceChanged', fn ($ids) => $ids === [])
        ->assertDontSee('harga berubah');
});

it('filters sales by product on name, date and warehouse', function () {
    insLogin(['reports.sales-by-product.view']);
    $urea = insProduct('Pupuk Urea');
    $npk = insProduct('Pupuk NPK');
    $other = Warehouse::create(['code' => 'W-2', 'name' => 'Gudang Dua']);

    insSale([[$urea, 1, 5000]], ['sale_date' => '2026-09-01']);
    insSale([[$npk, 1, 2000]], ['sale_date' => '2026-10-01']);
    insSale([[$npk, 1, 2000]], ['sale_date' => '2026-10-01', 'warehouse_id' => $other->id]);

    $this->get(route('reports.sales-by-product', ['q' => 'urea']))
        ->assertViewHas('totalRevenue', fn ($t) => (float) $t === 5000.0);

    $this->get(route('reports.sales-by-product', ['date_from' => '2026-10-01']))
        ->assertViewHas('totalRevenue', fn ($t) => (float) $t === 4000.0);

    $this->get(route('reports.sales-by-product', ['warehouse_id' => $other->id]))
        ->assertViewHas('totalRevenue', fn ($t) => (float) $t === 2000.0);
});

it('keeps the sales by product report behind its permission', function () {
    insLogin();
    $this->get(route('reports.sales-by-product'))->assertForbidden();
});

// ---- Inquiry -----------------------------------------------------------------------------

it('shows stock in units, packs and boxes with an availability label', function () {
    insLogin(['inquiry.view']);
    $warehouse = insWarehouse();

    $product = Product::create([
        'code' => 'P-INQ', 'name' => 'Pupuk Pak', 'unit_name' => 'pcs',
        'pack_name' => 'pack', 'pack_qty' => 10, 'box_name' => 'box', 'box_qty' => 100,
    ]);
    $empty = Product::create(['code' => 'P-EMP', 'name' => 'Pupuk Kosong']);

    app(StockService::class)->increase($product->id, $warehouse->id, 235, 'SEED-INQ');
    app(StockService::class)->increase($empty->id, $warehouse->id, 3, 'SEED-EMP');
    app(StockService::class)->decrease($empty->id, $warehouse->id, 3, 'SELL-EMP');

    $this->get(route('inquiry.index'))
        ->assertOk()
        ->assertSee('2 box 3 pack 5 pcs')
        ->assertSee('data-availability="available"', false)
        ->assertSee('data-availability="out"', false);
});

it('shows the current price with its effective date, and ignores a future price', function () {
    insLogin(['inquiry.view']);
    $urea = insProduct('Pupuk Urea');

    insPrice($urea, 5000, '2026-09-01');
    insPrice($urea, 9999, Carbon::today()->addMonth()->toDateString());

    // The future price is still in the change log below, but it must not be the current price.
    $this->get(route('inquiry.index'))
        ->assertOk()
        ->assertSee('Rp 5.000')
        ->assertSee('(berlaku 01 Sep 2026)')
        ->assertViewHas('pricesByProduct', function ($prices) use ($urea) {
            $amounts = collect($prices[$urea->id] ?? [])->map(fn ($p) => (float) $p->amount)->all();

            return $amounts === [5000.0];
        });
});

it('shows who changed a price, when, and from what to what', function () {
    $user = insLogin(['inquiry.view']);
    $urea = insProduct('Pupuk Urea');
    $other = insProduct('Pupuk NPK');

    $setup = insPrice($urea, 5000, '2026-09-01');
    insReprice($setup, 5500, '2026-10-01');

    insPrice($other, 2000, '2026-09-01');

    $response = $this->get(route('inquiry.index', ['q' => 'urea']));

    $response->assertOk()
        ->assertSee('data-history-row="'.$urea->id.'"', false)
        ->assertSee('Riwayat harga')
        ->assertSee('Rp 5.000 &rarr;', false)
        ->assertSee('Rp 5.500')
        ->assertSee($user->username)
        ->assertViewHas('historyByProduct', function ($history) use ($urea, $other) {
            return $history[$urea->id]->count() === 2 && ! isset($history[$other->id]);
        });
});

it('says so when a product has no recorded price change', function () {
    insLogin(['inquiry.view']);
    insProduct('Pupuk Urea');

    $this->get(route('inquiry.index'))
        ->assertOk()
        ->assertSee('Belum ada perubahan harga yang tercatat');
});

it('keeps only the latest ten price changes per product', function () {
    insLogin(['inquiry.view']);
    $urea = insProduct('Pupuk Urea');
    $setup = insPrice($urea, 1000, '2026-09-01');

    foreach (range(1, 14) as $i) {
        insReprice($setup, 1000 + $i * 10);
    }

    $this->get(route('inquiry.index'))
        ->assertViewHas('historyByProduct', fn ($history) => $history[$urea->id]->count() === 10);
});

// ---- Permissions and menu ----------------------------------------------------------------

it('creates the permissions of the new reports', function () {
    foreach (['reports.sales-by-product.view', 'reports.salesman.view', 'reports.payment-methods.view'] as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
});

it('shows the new report links only to users who may open them', function () {
    insLogin(['dashboard.view', 'reports.salesman.view']);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('reports.salesman'), false)
        ->assertDontSee(route('reports.payment-methods'), false)
        ->assertDontSee(route('reports.sales-by-product'), false);
});
