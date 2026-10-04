<?php

namespace Database\Seeders;

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\CashFlow;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GeneralLedger;
use App\Models\InternalMutation;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\NumberingService;
use App\Services\PriceService;
use App\Services\SaleDiscountService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Demo transactions dated relative to TODAY instead of fixed 2024 dates.
 *
 * Window: the last WINDOW_DAYS days, today included. Day by day it plays a small
 * store forward in time, so stock, receivables and payables all stay consistent:
 *
 *   restock PO -> goods received (purchase) -> purchase return -> supplier payment (AP)
 *   POS / sales-page sale (some discounted, some on credit) -> sales return -> customer payment (AR)
 *   warehouse transfers, then general ledger + cash flow for what was created here.
 *
 * Everything goes through the same services the screens use (NumberingService for
 * the document numbers, StockService for the inventory ledger), so the data looks
 * exactly like data entered in the app: 12-digit numbers, ledger rows with a source,
 * due dates, discounts, cashier sales without a warehouse, and so on.
 *
 * It only ADDS data and never touches what is already there (the older 2024 demo
 * data can stay). Stock carries on from the current balances.
 *
 *   php artisan db:seed --class=RecentDataSeeder
 *   php artisan migrate:fresh --seed --seeder=RecentDataSeeder     (clean start)
 *
 * Refuses to run when APP_ENV=production. Running it twice is refused as well
 * (the window would be filled twice); set RECENT_SEED_FORCE=1 to run anyway.
 */
class RecentDataSeeder extends Seeder
{
    /** How many days back the data starts, today included. */
    private const WINDOW_DAYS = 90;

    /** Order in which things scheduled for the same day are handled. */
    private const QUEUE_ORDER = [
        'receive' => 0,
        'sales_return' => 1,
        'purchase_return' => 1,
        'ap_payment' => 2,
        'ar_payment' => 2,
    ];

    private Carbon $today;

    private Carbon $start;

    private StockService $stock;

    private NumberingService $numbering;

    private SaleDiscountService $discounts;

    /** @var array<int, int> */
    private array $warehouses = [];

    /** @var array<int, int> */
    private array $productIds = [];

    /** @var array<int, int> */
    private array $suppliers = [];

    /** @var array<int, int> */
    private array $customers = [];

    /** @var array<int, int> */
    private array $cashiers = [];

    /** @var array<int, int> */
    private array $fieldSales = [];

    /** @var array<int, int> */
    private array $warehouseStaff = [];

    /** @var array<int, string> */
    private array $drivers = [];

    /** @var \Illuminate\Support\Collection<int, PaymentMethod> */
    private $methods;

    /** @var array<int, array<int, array{date: string, amount: float}>> product_id => prices, newest first */
    private array $priceBook = [];

    /** @var array<string, array<int, array{0: string, 1: array}>> 'Y-m-d' => things to do that day */
    private array $queue = [];

    /** @var array<string, array<int, int>> ids of what this run created, for the accounting pass */
    private array $created = ['purchases' => [], 'ap' => [], 'sales' => [], 'ar' => []];

    /** @var array<string, int> */
    private array $counts = [
        'purchase_orders' => 0, 'purchases' => 0, 'purchase_returns' => 0, 'ap_payments' => 0,
        'sales_orders' => 0, 'sales' => 0, 'sales_returns' => 0, 'ar_payments' => 0, 'transfers' => 0,
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('RecentDataSeeder creates demo data and is not run in production.');

            return;
        }

        $this->today = Carbon::today();
        $this->start = $this->today->copy()->subDays(self::WINDOW_DAYS - 1);

        if (! env('RECENT_SEED_FORCE') && Sale::whereDate('sale_date', '>=', $this->start->toDateString())->exists()) {
            $this->command?->warn('There are already sales inside the last '.self::WINDOW_DAYS.' days, nothing was added. '
                .'Use migrate:fresh, or set RECENT_SEED_FORCE=1 to add another batch.');

            return;
        }

        $this->ensureMasterData();

        $this->stock = app(StockService::class);
        $this->numbering = app(NumberingService::class);
        $this->discounts = app(SaleDiscountService::class);

        $this->loadLookups();

        if ($this->warehouses === [] || $this->productIds === [] || $this->suppliers === [] || $this->customers === []) {
            $this->command?->error('Master data is incomplete (warehouses, products, suppliers and customers are needed).');

            return;
        }

        for ($i = 0; $i < self::WINDOW_DAYS; $i++) {
            $day = $this->start->copy()->addDays($i);

            $this->drain($day);
            $this->procurement($i, $day);

            // The first deliveries arrive on day 1, so selling starts on day 2.
            if ($i >= 2) {
                $this->dailySales($i, $day);
            }

            if ($i >= 10 && $i % 7 === 3) {
                $this->transfer($day);
            }
        }

        $this->openOrders();
        $this->postAccounting();

        $this->report();
    }

    // ------------------------------------------------------------------ setup

    /** Run the master-data seeders when the database has none (fresh install). */
    private function ensureMasterData(): void
    {
        if (Product::count() === 0 || Warehouse::count() === 0) {
            $this->call([
                RolePermissionSeeder::class,
                EmployeeSeeder::class,
                WarehouseSeeder::class,
                BrandSeeder::class,
                ItemTypeSeeder::class,
                ProductGroupSeeder::class,
                CustomerSeeder::class,
                SupplierSeeder::class,
                UserSeeder::class,
                ProductSeeder::class,
                PriceSetupSeeder::class,
                AssetSeeder::class,
            ]);
        }

        if (ChartOfAccount::count() === 0) {
            $this->call(ChartOfAccountSeeder::class);
        }

        if (PaymentMethod::count() === 0) {
            $this->call(PaymentMethodSeeder::class);
        }
    }

    private function loadLookups(): void
    {
        $this->warehouses = Warehouse::orderBy('id')->pluck('id')->all();
        $this->productIds = Product::orderBy('id')->pluck('id')->all();
        $this->suppliers = Supplier::orderBy('id')->pluck('id')->all();
        $this->customers = Customer::orderBy('id')->pluck('id')->all();

        $everyone = Employee::orderBy('id')->pluck('id')->all();
        $this->cashiers = Employee::where('position', 'Kasir')->pluck('id')->all() ?: $everyone;
        $this->fieldSales = Employee::where('position', 'Sales Lapangan')->pluck('id')->all() ?: $everyone;
        $this->warehouseStaff = Employee::whereIn('position', ['Kepala Gudang', 'Staff Gudang'])->pluck('id')->all() ?: $everyone;
        $this->drivers = Employee::where('position', 'like', 'Sopir%')->pluck('name')->all();

        $this->methods = PaymentMethod::active()->orderBy('id')->get();

        $this->priceBook = app(PriceService::class)->priceBook($this->productIds, PriceService::RETAIL);
    }

    // ------------------------------------------------------------ daily loop

    /** Do whatever was scheduled for this day (goods arriving, returns, payments). */
    private function drain(Carbon $day): void
    {
        $key = $day->toDateString();
        $items = $this->queue[$key] ?? [];
        unset($this->queue[$key]);

        usort($items, fn (array $a, array $b) => self::QUEUE_ORDER[$a[0]] <=> self::QUEUE_ORDER[$b[0]]);

        foreach ($items as [$type, $payload]) {
            match ($type) {
                'receive' => $this->receive($payload['po_id'], $day),
                'sales_return' => $this->makeSalesReturn($payload['sale_id'], $day),
                'purchase_return' => $this->makePurchaseReturn($payload['purchase_id'], $day),
                'ap_payment' => $this->makeApPayment($payload['purchase_id'], $payload['ratio'], $day),
                'ar_payment' => $this->makeArPayment($payload['sale_id'], $payload['ratio'], $day),
            };
        }
    }

    /** Put something on the calendar. Dates after today are dropped: they have not happened yet. */
    private function schedule(Carbon $date, string $type, array $payload): void
    {
        if ($date->greaterThan($this->today)) {
            return;
        }

        $this->queue[$date->toDateString()][] = [$type, $payload];
    }

    // ------------------------------------------------------------ purchasing

    private function procurement(int $i, Carbon $day): void
    {
        // Day 0: the shelves are filled, split over a few suppliers.
        if ($i === 0) {
            $chunks = array_chunk($this->productIds, max(1, (int) ceil(count($this->productIds) / 4)));

            foreach ($chunks as $index => $chunk) {
                $lines = array_map(fn (int $id) => ['product_id' => $id, 'qty' => mt_rand(120, 300)], $chunk);
                $this->makePurchaseOrder($day, $lines, $this->suppliers[$index % count($this->suppliers)], 1);
            }

            return;
        }

        // After that: a restock about every three days, favouring what is running low.
        if ($i % 3 !== 1) {
            return;
        }

        $totals = $this->stock->totalAvailableMany($this->productIds);
        asort($totals);
        $lowest = array_slice(array_keys($totals), 0, max(6, (int) ceil(count($totals) / 3)));
        shuffle($lowest);

        $lines = array_map(
            fn (int $id) => ['product_id' => $id, 'qty' => mt_rand(80, 250)],
            array_slice($lowest, 0, mt_rand(4, 8))
        );

        $this->makePurchaseOrder($day, $lines, $this->pick($this->suppliers), mt_rand(1, 5));
    }

    /**
     * @param  array<int, array{product_id: int, qty: int}>  $lines
     */
    private function makePurchaseOrder(Carbon $orderDay, array $lines, int $supplierId, int $receiveAfterDays): void
    {
        $receiveDay = $orderDay->copy()->addDays($receiveAfterDays);
        $arrived = $receiveDay->lessThanOrEqualTo($this->today);

        // An order whose goods have not arrived yet is still open.
        $status = $arrived ? 'completed' : (mt_rand(0, 1) ? 'approved' : 'pending');

        $po = new PurchaseOrder([
            'order_date' => $orderDay->toDateString(),
            'status' => $status,
            'supplier_id' => $supplierId,
        ]);

        DB::transaction(function () use ($po, $lines, $orderDay) {
            $this->numbered($po)->save();

            $po->purchaseOrderDetails()->createMany(array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'qty' => $line['qty'],
                // Buying price: roughly 68-80% of the retail price at that time.
                'price' => round($this->retailPrice($line['product_id'], $orderDay) * mt_rand(68, 80) / 100 / 500) * 500,
            ], $lines));
        });

        $this->counts['purchase_orders']++;

        if ($arrived) {
            $this->schedule($receiveDay, 'receive', ['po_id' => $po->id]);
        }
    }

    /** Goods of a purchase order arrive: purchase invoice + stock in. */
    private function receive(int $poId, Carbon $day): void
    {
        $po = PurchaseOrder::with('purchaseOrderDetails')->find($poId);
        if (! $po || $po->purchaseOrderDetails->isEmpty()) {
            return;
        }

        $warehouseId = $this->pick($this->warehouses);
        $moment = $this->at($day, 8, 12);
        $total = $po->purchaseOrderDetails->sum(fn ($d) => $d->qty * $d->price);

        $purchase = new Purchase([
            'purchase_date' => $day->toDateString(),
            'due_date' => $day->copy()->addDays(Purchase::PAYMENT_TERM_DAYS)->toDateString(),
            'total_amount' => $total,
            'status' => 'received',
            'purchase_order_id' => $po->id,
            'supplier_id' => $po->supplier_id,
            'warehouse_id' => $warehouseId,
        ]);

        DB::transaction(function () use ($purchase, $po, $warehouseId, $moment) {
            $this->numbered($purchase)->save();

            $purchase->purchaseDetails()->createMany($po->purchaseOrderDetails->map(fn ($d) => [
                'product_id' => $d->product_id,
                'qty' => $d->qty,
                'price' => $d->price,
            ])->all());

            $this->stock->sync(
                $purchase,
                $warehouseId,
                $po->purchaseOrderDetails->map(fn ($d) => ['product_id' => $d->product_id, 'qty' => $d->qty])->all(),
                'in',
                $purchase->invoice_number,
                $moment
            );
        });

        $this->created['purchases'][] = $purchase->id;
        $this->counts['purchases']++;

        // What happens to the invoice: a return, and how much of it gets paid.
        $age = (int) abs($day->diffInDays($this->today));
        $roll = mt_rand(1, 100);
        $pattern = $age > 35
            ? ($roll <= 75 ? 'full' : ($roll <= 88 ? 'partial' : 'none'))
            : ($roll <= 50 ? 'full' : ($roll <= 65 ? 'partial' : 'none'));

        if (mt_rand(1, 100) <= 8) {
            // Returns are made in the first days; the payment later takes them into account.
            $this->schedule($day->copy()->addDays(mt_rand(1, 3)), 'purchase_return', ['purchase_id' => $purchase->id]);
            $pattern = $pattern === 'full' ? 'partial' : $pattern;
        }

        if ($pattern !== 'none') {
            $this->schedule(
                $day->copy()->addDays(mt_rand(5, 24)),
                'ap_payment',
                ['purchase_id' => $purchase->id, 'ratio' => $pattern === 'full' ? 1.0 : 0.4]
            );
        }
    }

    private function makePurchaseReturn(int $purchaseId, Carbon $day): void
    {
        $purchase = Purchase::with('purchaseDetails')->find($purchaseId);
        if (! $purchase || $purchase->purchaseDetails->isEmpty()) {
            return;
        }

        // A return never exceeds 30% of the invoice, so the partial payment stays below what is still owed.
        $budget = (float) $purchase->total_amount * 0.3;
        $total = 0.0;
        $lines = [];

        foreach ($purchase->purchaseDetails->shuffle()->take(2) as $detail) {
            $qty = min(mt_rand(1, 10), (int) $detail->qty, $this->stock->available($detail->product_id, $purchase->warehouse_id));
            $qty = min($qty, (int) floor(($budget - $total) / max(1, (float) $detail->price)));

            if ($qty < 1) {
                continue;
            }

            $total += $qty * (float) $detail->price;
            $lines[] = ['product_id' => $detail->product_id, 'qty' => $qty, 'reason' => $this->pick([
                'Barang cacat/rusak',
                'Kemasan robek',
                'Salah kirim produk',
                'Kualitas tidak sesuai pesanan',
                'Kadaluarsa',
            ])];
        }

        if ($lines === []) {
            return;
        }

        $return = new PurchaseReturn([
            'return_date' => $day->toDateString(),
            'total_amount' => $total,
            'purchase_id' => $purchase->id,
        ]);

        try {
            DB::transaction(function () use ($return, $lines, $purchase, $day) {
                $this->numbered($return)->save();
                $return->purchaseReturnDetails()->createMany($lines);

                $this->stock->sync(
                    $return,
                    $purchase->warehouse_id,
                    array_map(fn (array $l) => ['product_id' => $l['product_id'], 'qty' => $l['qty']], $lines),
                    'out',
                    $return->return_number,
                    $this->at($day)
                );
            });
        } catch (ValidationException) {
            return;
        }

        $this->counts['purchase_returns']++;
    }

    private function makeApPayment(int $purchaseId, float $ratio, Carbon $day): void
    {
        $purchase = Purchase::find($purchaseId);
        if (! $purchase) {
            return;
        }

        $returned = (float) $purchase->purchaseReturns()->sum('total_amount');
        $amount = round(((float) $purchase->total_amount - $returned) * $ratio);

        if ($amount <= 0) {
            return;
        }

        $payment = new ApPayment([
            'amount' => $amount,
            'payment_date' => $day->toDateString(),
            'payment_method_id' => $this->pickMethod(['bank_transfer' => 60, 'cash' => 40, 'qris' => 0])->id,
            'supplier_id' => $purchase->supplier_id,
            'purchase_id' => $purchase->id,
        ]);

        DB::transaction(fn () => $this->numbered($payment)->save());

        $this->created['ap'][] = $payment->id;
        $this->counts['ap_payments']++;
    }

    // --------------------------------------------------------------- selling

    private function dailySales(int $i, Carbon $day): void
    {
        $perDay = 6 + (int) round(4 * $i / self::WINDOW_DAYS); // a slowly growing shop

        $factor = match ($day->dayOfWeekIso) {
            7 => 0.4,
            6 => 0.8,
            default => 1.0,
        };

        $count = max(1, (int) round($perDay * $factor) + mt_rand(-2, 3));

        for ($n = 0; $n < $count; $n++) {
            $this->makeSale($day);
        }
    }

    private function makeSale(Carbon $day): void
    {
        // Cashier terminal (no warehouse of its own) or the sales page (names a warehouse).
        $source = mt_rand(1, 100) <= 70 ? 'pos' : 'sales';
        $warehouseId = $source === 'sales' ? $this->pick($this->warehouses) : null;

        $lines = $this->saleLines($source, $day, $warehouseId);
        if ($lines === []) {
            return;
        }

        $customerId = null;
        $paymentType = Sale::PAYMENT_CASH;

        if ($source === 'pos') {
            // About a third of walk-in sales are anonymous cash customers.
            if (mt_rand(1, 100) > 35) {
                $customerId = $this->pick($this->customers);
                $paymentType = mt_rand(1, 100) <= 25 ? Sale::PAYMENT_CREDIT : Sale::PAYMENT_CASH;
            }
        } else {
            $customerId = $this->pick($this->customers);
            $paymentType = mt_rand(1, 100) <= 60 ? Sale::PAYMENT_CREDIT : Sale::PAYMENT_CASH;
        }

        $subtotal = array_sum(array_map(fn (array $l) => $l['qty'] * $l['price'], $lines));
        $pricing = $this->discountFor($subtotal);

        $sale = new Sale([
            'sale_date' => $day->toDateString(),
            'total_amount' => $pricing['total_amount'],
            'discount_percent' => $pricing['discount_percent'],
            'discount_amount' => $pricing['discount_amount'],
            'discount_total' => $pricing['discount_total'],
            'source' => $source,
            'payment_type' => $paymentType,
            // Only a sale paid right away has a method; a credit sale is settled later by an AR payment.
            'payment_method_id' => $paymentType === Sale::PAYMENT_CASH ? $this->pickMethod()->id : null,
            'sales_order_id' => null,
            'customer_id' => $customerId,
            'salesman_id' => $this->pick($source === 'pos' ? $this->cashiers : $this->fieldSales),
            'warehouse_id' => $warehouseId,
            'driver_name' => $source === 'sales' && $this->drivers !== [] && mt_rand(1, 100) <= 30 ? $this->pick($this->drivers) : null,
        ]);

        $stockItems = array_map(fn (array $l) => ['product_id' => $l['product_id'], 'qty' => $l['qty']], $lines);
        $moment = $this->at($day);

        try {
            DB::transaction(function () use ($sale, $lines, $stockItems, $source, $warehouseId, $day, $moment) {
                // Some sales-page sales come from an order taken a few days earlier.
                if ($source === 'sales' && mt_rand(1, 100) <= 40) {
                    $orderDay = $day->copy()->subDays(mt_rand(0, 3));
                    if ($orderDay->lessThan($this->start)) {
                        $orderDay = $day->copy();
                    }

                    $sale->sales_order_id = $this->makeSalesOrder($orderDay, 'completed', $lines, $sale->customer_id)->id;
                }

                $this->numbered($sale);
                $sale->public_token = Str::random(32);
                $sale->save();

                $sale->saleDetails()->createMany($lines);

                if ($warehouseId === null) {
                    $this->stock->syncFromAllWarehouses($sale, $stockItems, $sale->invoice_number, $moment);
                } else {
                    $this->stock->sync($sale, $warehouseId, $stockItems, 'out', $sale->invoice_number, $moment);
                }
            });
        } catch (ValidationException) {
            return; // short on stock after all: the sale simply did not happen
        }

        $this->created['sales'][] = $sale->id;
        $this->counts['sales']++;

        $returned = mt_rand(1, 100) <= 5;
        if ($returned) {
            $this->schedule($day->copy()->addDays(mt_rand(1, 3)), 'sales_return', ['sale_id' => $sale->id]);
        }

        // Credit sales: how much of it the customer has paid by now.
        if ($paymentType === Sale::PAYMENT_CREDIT && $customerId !== null) {
            $age = (int) abs($day->diffInDays($this->today));
            $roll = mt_rand(1, 100);
            $pattern = $age > 45
                ? ($roll <= 70 ? 'full' : ($roll <= 85 ? 'partial' : 'none'))
                : ($roll <= 45 ? 'full' : ($roll <= 60 ? 'partial' : 'none'));

            if ($pattern !== 'none') {
                $this->schedule(
                    $day->copy()->addDays(mt_rand(4, 25)),
                    'ar_payment',
                    ['sale_id' => $sale->id, 'ratio' => $pattern === 'full' ? 1.0 : mt_rand(40, 60) / 100]
                );
            }
        }
    }

    /**
     * @return array<int, array{product_id: int, qty: int, price: float}>
     */
    private function saleLines(string $source, Carbon $day, ?int $warehouseId): array
    {
        $candidates = $this->pickProducts($source === 'pos' ? mt_rand(1, 4) : mt_rand(2, 5));

        $available = $warehouseId === null
            ? $this->stock->totalAvailableMany($candidates)
            : $this->stock->availableMany($candidates, $warehouseId);

        $lines = [];
        foreach ($candidates as $productId) {
            $have = $available[$productId] ?? 0;
            if ($have < 1) {
                continue;
            }

            $lines[] = [
                'product_id' => $productId,
                'qty' => min($have, $source === 'pos' ? mt_rand(1, 6) : mt_rand(3, 25)),
                'price' => $this->retailPrice($productId, $day),
            ];
        }

        return $lines;
    }

    /**
     * About one sale in seven gets a discount: 5%, 10% or a fixed Rp 5.000 - 50.000.
     *
     * @return array{discount_percent: float, discount_amount: float, discount_total: float, total_amount: float}
     */
    private function discountFor(float $subtotal): array
    {
        $none = [
            'discount_percent' => 0.0,
            'discount_amount' => 0.0,
            'discount_total' => 0.0,
            'total_amount' => round($subtotal, 2),
        ];

        if ($subtotal < 100000 || mt_rand(1, 100) > 15) {
            return $none;
        }

        $percent = 0.0;
        $amount = 0.0;

        if (mt_rand(0, 1)) {
            $percent = mt_rand(0, 1) ? 5.0 : 10.0;
        } else {
            $amount = (float) (mt_rand(5, 50) * 1000);
        }

        try {
            $result = $this->discounts->calculate($subtotal, $percent, $amount);
        } catch (ValidationException) {
            return $none;
        }

        return [
            'discount_percent' => $result['discount_percent'],
            'discount_amount' => $result['discount_amount'],
            'discount_total' => $result['discount_total'],
            'total_amount' => $result['total_amount'],
        ];
    }

    /**
     * @param  array<int, array{product_id: int, qty: int, price: float}>  $lines
     */
    private function makeSalesOrder(Carbon $orderDay, string $status, array $lines, ?int $customerId): SalesOrder
    {
        $order = new SalesOrder([
            'order_date' => $orderDay->toDateString(),
            'status' => $status,
            'customer_id' => $customerId ?? $this->pick($this->customers),
        ]);

        $this->numbered($order)->save();
        $order->salesOrderDetails()->createMany($lines);

        $this->counts['sales_orders']++;

        return $order;
    }

    private function makeSalesReturn(int $saleId, Carbon $day): void
    {
        $sale = Sale::with('saleDetails')->find($saleId);
        if (! $sale || $sale->saleDetails->isEmpty()) {
            return;
        }

        $lines = [];
        $total = 0.0;

        foreach ($sale->saleDetails->shuffle()->take(2) as $detail) {
            $qty = mt_rand(1, max(1, intdiv((int) $detail->qty + 1, 2)));
            $total += $qty * (float) $detail->price;
            $lines[] = ['product_id' => $detail->product_id, 'qty' => $qty];
        }

        // The customer got the discount, so the refund is worth that much less too.
        $beforeDiscount = $sale->subtotalBeforeDiscount();
        if ($beforeDiscount > 0) {
            $total = round($total * ((float) $sale->total_amount / $beforeDiscount), 2);
        }

        $return = new SalesReturn([
            'return_date' => $day->toDateString(),
            'total_amount' => $total,
            'sale_id' => $sale->id,
        ]);

        try {
            DB::transaction(function () use ($return, $lines, $sale, $day) {
                $this->numbered($return)->save();
                $return->salesReturnDetails()->createMany($lines);

                $moment = $this->at($day);

                // Returned goods go back on the shelf: where the sale took them from.
                if ($sale->warehouse_id === null) {
                    $this->stock->syncReturnToSaleWarehouses($return, $sale, $lines, $return->return_number, $moment);
                } else {
                    $this->stock->sync($return, $sale->warehouse_id, $lines, 'in', $return->return_number, $moment);
                }
            });
        } catch (ValidationException) {
            return;
        }

        $this->counts['sales_returns']++;
    }

    private function makeArPayment(int $saleId, float $ratio, Carbon $day): void
    {
        $sale = Sale::find($saleId);
        if (! $sale || $sale->customer_id === null) {
            return;
        }

        $returned = (float) $sale->salesReturns()->sum('total_amount');
        $amount = round(((float) $sale->total_amount - $returned) * $ratio);

        if ($amount <= 0) {
            return;
        }

        $payment = new ArPayment([
            'amount' => $amount,
            'payment_date' => $day->toDateString(),
            'payment_method_id' => $this->pickMethod(['bank_transfer' => 50, 'cash' => 40, 'qris' => 10])->id,
            'customer_id' => $sale->customer_id,
        ]);

        DB::transaction(fn () => $this->numbered($payment)->save());

        $this->created['ar'][] = $payment->id;
        $this->counts['ar_payments']++;
    }

    /** A few orders taken in the last days that have not been turned into sales yet. */
    private function openOrders(): void
    {
        for ($n = 0; $n < 3; $n++) {
            $orderDay = $this->today->copy()->subDays($n);
            $lines = [];

            foreach ($this->pickProducts(mt_rand(2, 4)) as $productId) {
                $lines[] = [
                    'product_id' => $productId,
                    'qty' => mt_rand(2, 15),
                    'price' => $this->retailPrice($productId, $orderDay),
                ];
            }

            $this->makeSalesOrder($orderDay, $n === 0 ? 'pending' : 'approved', $lines, null);
        }
    }

    // ------------------------------------------------------------- transfers

    private function transfer(Carbon $day): void
    {
        if (count($this->warehouses) < 2) {
            return;
        }

        $from = $this->pick($this->warehouses);
        $to = $this->pick(array_values(array_diff($this->warehouses, [$from])));

        $candidates = $this->pickProducts(mt_rand(1, 4));
        $available = $this->stock->availableMany($candidates, $from);

        $lines = [];
        foreach ($candidates as $productId) {
            $have = $available[$productId] ?? 0;
            if ($have >= 10) {
                $lines[] = ['product_id' => $productId, 'qty' => min($have, mt_rand(5, 30))];
            }
        }

        if ($lines === []) {
            return;
        }

        $roll = mt_rand(1, 100);
        $status = $roll <= 85 ? 'completed' : ($roll <= 93 ? 'approved' : 'pending');

        $mutation = new InternalMutation([
            'type' => 'Transfer Antar Gudang',
            'mutation_date' => $day->toDateString(),
            'status' => $status,
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $to,
            'requested_by' => $this->pick($this->warehouseStaff),
        ]);

        try {
            DB::transaction(function () use ($mutation, $lines, $status, $from, $to, $day) {
                $this->numbered($mutation)->save();

                $mutation->internalMutationDetails()->createMany(array_map(fn (array $l) => $l + [
                    'notes' => 'Distribusi stok antar gudang',
                ], $lines));

                // Stock only moves once the transfer is completed.
                if ($status === 'completed') {
                    $this->stock->syncTransfer($mutation, $from, $to, $lines, $mutation->mutation_number, $this->at($day));
                }
            });
        } catch (ValidationException) {
            return;
        }

        $this->counts['transfers']++;
    }

    // ------------------------------------------------------------ accounting

    /**
     * General ledger and cash flow for what THIS run created (so the older seeders'
     * entries are never doubled), written in bulk.
     */
    private function postAccounting(): void
    {
        $accounts = ChartOfAccount::pluck('id', 'account_code');

        foreach (['1000', '1010', '1100', '1200', '2000', '4000', '5000', '5100', '5200', '5300'] as $code) {
            if (! isset($accounts[$code])) {
                $this->command?->warn("Account {$code} is missing: general ledger and cash flow were skipped.");

                return;
            }
        }

        $now = now();
        $gl = [];
        $cash = [];

        $line = function (string $date, float $debit, float $credit, string $reference, int $account) use (&$gl, $now) {
            $gl[] = [
                'transaction_date' => $date,
                'debit' => $debit,
                'credit' => $credit,
                'reference_number' => $reference,
                'account_id' => $account,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $flow = function (string $date, string $type, float $amount, string $description, int $account) use (&$cash, $now) {
            $cash[] = [
                'transaction_date' => $date,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'account_id' => $account,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $moneyAccount = fn (?PaymentMethod $method) => $method?->is_cash ? $accounts['1000'] : $accounts['1010'];

        // Purchases: debit Inventory, credit Accounts Payable.
        foreach (array_chunk($this->created['purchases'], 500) as $ids) {
            foreach (Purchase::whereIn('id', $ids)->orderBy('id')->get() as $purchase) {
                $date = Carbon::parse($purchase->purchase_date)->toDateString();
                $line($date, $purchase->total_amount, 0, $purchase->invoice_number, $accounts['1200']);
                $line($date, 0, $purchase->total_amount, $purchase->invoice_number, $accounts['2000']);
            }
        }

        // Supplier payments: debit Accounts Payable, credit Cash/Bank; cash going out.
        foreach (array_chunk($this->created['ap'], 500) as $ids) {
            foreach (ApPayment::with('paymentMethod')->whereIn('id', $ids)->orderBy('id')->get() as $payment) {
                $date = Carbon::parse($payment->payment_date)->toDateString();
                $paidFrom = $moneyAccount($payment->paymentMethod);

                $line($date, $payment->amount, 0, $payment->payment_number, $accounts['2000']);
                $line($date, 0, $payment->amount, $payment->payment_number, $paidFrom);
                $flow($date, 'Outflow', $payment->amount, 'Pembayaran utang usaha - '.$payment->payment_number, $paidFrom);
            }
        }

        // Sales: debit Cash/Bank (paid now) or Receivable (credit), credit Revenue; COGS at about 72% of the sale.
        foreach (array_chunk($this->created['sales'], 500) as $ids) {
            foreach (Sale::with('paymentMethod')->whereIn('id', $ids)->orderBy('id')->get() as $sale) {
                $date = Carbon::parse($sale->sale_date)->toDateString();
                $debit = $sale->payment_type === Sale::PAYMENT_CREDIT
                    ? $accounts['1100']
                    : $moneyAccount($sale->paymentMethod);
                $cost = round($sale->total_amount * 0.72);

                $line($date, $sale->total_amount, 0, $sale->invoice_number, $debit);
                $line($date, 0, $sale->total_amount, $sale->invoice_number, $accounts['4000']);
                $line($date, $cost, 0, $sale->invoice_number, $accounts['5000']);
                $line($date, 0, $cost, $sale->invoice_number, $accounts['1200']);
            }
        }

        // Customer payments: debit Cash/Bank, credit Receivable; cash coming in.
        foreach (array_chunk($this->created['ar'], 500) as $ids) {
            foreach (ArPayment::with('paymentMethod')->whereIn('id', $ids)->orderBy('id')->get() as $payment) {
                $date = Carbon::parse($payment->payment_date)->toDateString();
                $receivedInto = $moneyAccount($payment->paymentMethod);

                $line($date, $payment->amount, 0, $payment->payment_number, $receivedInto);
                $line($date, 0, $payment->amount, $payment->payment_number, $accounts['1100']);
                $flow($date, 'Inflow', $payment->amount, 'Penerimaan pembayaran piutang - '.$payment->payment_number, $receivedInto);
            }
        }

        // Monthly operating costs for every month in the window: payroll on the 25th, other costs after it.
        $cursor = $this->start->copy()->startOfMonth();
        while ($cursor->lessThanOrEqualTo($this->today)) {
            $payroll = $cursor->copy()->day(25);

            foreach ([
                [$payroll, 32000000, 'Pembayaran gaji karyawan bulan', $accounts['5100']],
                [$payroll->copy()->addDays(2), mt_rand(3500000, 6000000), 'Beban operasional (listrik, air, ATK) bulan', $accounts['5200']],
                [$payroll->copy()->addDays(4), mt_rand(2000000, 4500000), 'Beban angkut & pengiriman bulan', $accounts['5300']],
            ] as [$date, $amount, $label, $account]) {
                if ($date->betweenIncluded($this->start, $this->today)) {
                    $flow($date->toDateString(), 'Outflow', $amount, $label.' '.$payroll->translatedFormat('F Y'), $account);
                }
            }

            $cursor->addMonthNoOverflow();
        }

        foreach (array_chunk($gl, 500) as $rows) {
            GeneralLedger::insert($rows);
        }

        foreach (array_chunk($cash, 500) as $rows) {
            CashFlow::insert($rows);
        }
    }

    // --------------------------------------------------------------- helpers

    /** Give a new document its automatic number (works whether or not model events are on). */
    private function numbered(Model $model): Model
    {
        $column = $model->autoNumberConfig()['column'];
        $model->setAttribute($column, $this->numbering->generate($model));

        return $model;
    }

    /** Retail price of a product on a date (the price book is newest first). */
    private function retailPrice(int $productId, Carbon $date): float
    {
        $on = $date->toDateString();

        foreach ($this->priceBook[$productId] ?? [] as $row) {
            if ($row['date'] <= $on) {
                return (float) $row['amount'];
            }
        }

        return 50000.0;
    }

    /**
     * A time of day inside opening hours. Never in the future, so a sale rung up
     * "today" does not carry a timestamp later than now.
     */
    private function at(Carbon $day, int $fromHour = 8, int $toHour = 17): Carbon
    {
        $moment = $day->copy()->setTime(mt_rand($fromHour, $toHour), mt_rand(0, 59), mt_rand(0, 59));

        return $moment->greaterThan(now()) ? now()->subMinutes(mt_rand(1, 20)) : $moment;
    }

    /** @template T @param array<int, T> $items @return T */
    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }

    /**
     * Distinct products, the first ones in the catalogue selling more often than the last.
     *
     * @return array<int, int>
     */
    private function pickProducts(int $count): array
    {
        $n = count($this->productIds);
        $picked = [];

        for ($tries = 0; $tries < 60 && count($picked) < min($count, $n); $tries++) {
            $index = (int) floor($n * (mt_rand() / (mt_getrandmax() + 1)) ** 1.5);
            $picked[$this->productIds[$index]] = true;
        }

        return array_keys($picked);
    }

    /**
     * A payment method, chosen by weight per method code (unknown codes weigh 10, 0 means never).
     *
     * @param  array<string, int>  $weights
     */
    private function pickMethod(array $weights = ['cash' => 60, 'qris' => 25, 'bank_transfer' => 15]): PaymentMethod
    {
        $total = $this->methods->sum(fn (PaymentMethod $m) => $weights[$m->code] ?? 10);
        if ($total <= 0) {
            return $this->methods->first();
        }

        $roll = mt_rand(1, $total);
        $running = 0;

        foreach ($this->methods as $method) {
            $running += $weights[$method->code] ?? 10;
            if ($roll <= $running) {
                return $method;
            }
        }

        return $this->methods->first();
    }

    private function report(): void
    {
        $c = $this->counts;

        $this->command?->info(sprintf(
            'Recent data %s - %s: %d PO, %d purchases (%d returns, %d payments), %d sales (%d returns, %d customer payments, %d sales orders), %d transfers.',
            $this->start->format('d M Y'),
            $this->today->format('d M Y'),
            $c['purchase_orders'], $c['purchases'], $c['purchase_returns'], $c['ap_payments'],
            $c['sales'], $c['sales_returns'], $c['ar_payments'], $c['sales_orders'], $c['transfers'],
        ));
    }
}
