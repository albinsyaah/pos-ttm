<?php

use App\Models\ApPayment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PayableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// TestCase is already applied to tests/Feature by tests/Pest.php; only add the trait.
uses(RefreshDatabase::class);

/*
 * Helper names are prefixed "pb" so they cannot clash with helpers in other
 * test files (Pest loads them all into one process).
 */
function pbLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'pb-'.uniqid(),
        'password' => Hash::make('secret-password'),
        'role' => 'Staff',
        'is_active' => true,
    ]);

    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
        $user->givePermissionTo($name);
    }

    test()->actingAs($user);

    return $user;
}

function pbSupplier(string $code = 'S-1'): Supplier
{
    return Supplier::create(['code' => $code, 'name' => "Supplier {$code}"]);
}

function pbWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(['code' => 'W-PB'], ['name' => 'Gudang PB']);
}

/**
 * A purchase whose due date falls $daysLeft days from today
 * (negative = already overdue). Due date is purchase date + 30 days.
 */
function pbPurchase(Supplier $supplier, float $total, int $daysLeft = 20, string $status = 'received'): Purchase
{
    return Purchase::create([
        'invoice_number' => 'PUR-'.uniqid(),
        'purchase_date' => Carbon::today()->subDays(Purchase::PAYMENT_TERM_DAYS - $daysLeft)->toDateString(),
        'total_amount' => $total,
        'status' => $status,
        'supplier_id' => $supplier->id,
        'warehouse_id' => pbWarehouse()->id,
    ]);
}

function pbPay(Purchase $purchase, float $amount, array $extra = []): array
{
    return $extra + [
        'payment_number' => 'APAY-'.uniqid(),
        'amount' => $amount,
        'payment_date' => now()->format('Y-m-d'),
        'payment_method_id' => PaymentMethod::where('code', 'cash')->firstOrFail()->id,
        'supplier_id' => $purchase->supplier_id,
        'purchase_id' => $purchase->id,
    ];
}

function pbRecordPayment(Purchase $purchase, float $amount, ?int $purchaseId = -1): ApPayment
{
    return ApPayment::create([
        'payment_number' => 'APAY-'.uniqid(),
        'amount' => $amount,
        'payment_date' => now(),
        'payment_method_id' => PaymentMethod::where('code', 'cash')->firstOrFail()->id,
        'supplier_id' => $purchase->supplier_id,
        'purchase_id' => $purchaseId === -1 ? $purchase->id : $purchaseId,
    ]);
}

// ---- Due date -----------------------------------------------------------------------

it('sets the due date to 30 days after the purchase date', function () {
    pbLogin(['transactions.purchases.manage']);
    $supplier = pbSupplier();
    $product = Product::create(['code' => 'P-1', 'name' => 'Pupuk']);

    $this->post(route('transactions.purchases.store'), [
        'invoice_number' => 'PUR-100',
        'purchase_date' => '2026-10-01',
        'status' => 'received',
        'supplier_id' => $supplier->id,
        'warehouse_id' => pbWarehouse()->id,
        'items' => [['product_id' => $product->id, 'qty' => 5, 'price' => 1000]],
    ])->assertSessionHasNoErrors();

    expect(Purchase::firstOrFail()->due_date->toDateString())->toBe('2026-10-31');
});

it('moves the due date when the purchase date is edited', function () {
    pbLogin(['transactions.purchases.manage']);
    $supplier = pbSupplier();
    $product = Product::create(['code' => 'P-1', 'name' => 'Pupuk']);
    $purchase = pbPurchase($supplier, 5000, 20, 'pending');

    $this->put(route('transactions.purchases.update', $purchase), [
        'invoice_number' => $purchase->invoice_number,
        'purchase_date' => '2026-11-10',
        'status' => 'pending',
        'supplier_id' => $supplier->id,
        'warehouse_id' => pbWarehouse()->id,
        'items' => [['product_id' => $product->id, 'qty' => 5, 'price' => 1000]],
    ])->assertSessionHasNoErrors();

    expect($purchase->fresh()->due_date->toDateString())->toBe('2026-12-10');
});

// ---- Balance per invoice ----------------------------------------------------------------

it('lowers an invoice balance by purchase returns and by payments against it', function () {
    $supplier = pbSupplier();
    $purchase = pbPurchase($supplier, 10000);

    PurchaseReturn::create([
        'return_number' => 'RB-1', 'return_date' => now(), 'total_amount' => 1000, 'purchase_id' => $purchase->id,
    ]);
    pbRecordPayment($purchase, 4000);

    $row = app(PayableService::class)->invoices($supplier->id)->get($purchase->id);

    expect($row['net'])->toEqual(9000.0)
        ->and($row['paid'])->toEqual(4000.0)
        ->and($row['outstanding'])->toEqual(5000.0);
});

it('does not treat pending or cancelled purchases as debt', function () {
    $supplier = pbSupplier();
    pbPurchase($supplier, 1000, 20, 'pending');
    pbPurchase($supplier, 1000, 20, 'cancelled');

    expect(app(PayableService::class)->invoices($supplier->id))->toHaveCount(0);
});

it('applies payments that were never tied to an invoice to the oldest invoices first', function () {
    $supplier = pbSupplier();
    $old = pbPurchase($supplier, 3000, -10);
    $new = pbPurchase($supplier, 3000, 20);
    pbRecordPayment($old, 4000, null); // an older, supplier-level payment

    $rows = app(PayableService::class)->invoices($supplier->id);

    expect($rows->get($old->id)['outstanding'])->toEqual(0.0)
        ->and($rows->get($new->id)['outstanding'])->toEqual(2000.0);
});

// ---- Payments per invoice ----------------------------------------------------------------

it('accepts a part payment and then the rest of an invoice', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $purchase = pbPurchase(pbSupplier(), 10000);

    $this->post(route('transactions.payable-payments.store'), pbPay($purchase, 4000))->assertSessionHasNoErrors();
    expect(app(PayableService::class)->outstandingFor($purchase->id))->toEqual(6000.0);

    $this->post(route('transactions.payable-payments.store'), pbPay($purchase, 6000))->assertSessionHasNoErrors();
    expect(app(PayableService::class)->outstandingFor($purchase->id))->toEqual(0.0)
        ->and(ApPayment::count())->toBe(2);
});

it('refuses a payment larger than what is still owed on the invoice', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $purchase = pbPurchase(pbSupplier(), 10000);
    pbRecordPayment($purchase, 7000);

    $this->post(route('transactions.payable-payments.store'), pbPay($purchase, 3000.01))
        ->assertSessionHasErrors('amount');

    expect(ApPayment::count())->toBe(1);
});

it('requires an invoice for a new payment', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $purchase = pbPurchase(pbSupplier(), 10000);
    $payload = pbPay($purchase, 1000);
    unset($payload['purchase_id']);

    $this->post(route('transactions.payable-payments.store'), $payload)->assertSessionHasErrors('purchase_id');
});

it('refuses an invoice that belongs to another supplier or is not yet received', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $other = pbSupplier('S-2');
    $purchase = pbPurchase(pbSupplier(), 10000);
    $pending = pbPurchase($purchase->supplier, 5000, 20, 'pending');

    $this->post(route('transactions.payable-payments.store'), pbPay($purchase, 1000, ['supplier_id' => $other->id]))
        ->assertSessionHasErrors('purchase_id');
    $this->post(route('transactions.payable-payments.store'), pbPay($pending, 1000))
        ->assertSessionHasErrors('purchase_id');

    expect(ApPayment::count())->toBe(0);
});

it('does not count a payment against itself when it is edited', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $purchase = pbPurchase(pbSupplier(), 10000);
    $payment = pbRecordPayment($purchase, 10000);

    $this->put(route('transactions.payable-payments.update', $payment), pbPay($purchase, 8000, ['payment_number' => $payment->payment_number]))
        ->assertSessionHasNoErrors();
    expect($payment->fresh()->amount)->toEqual(8000);

    $this->put(route('transactions.payable-payments.update', $payment), pbPay($purchase, 10000.5, ['payment_number' => $payment->payment_number]))
        ->assertSessionHasErrors('amount');
});

it('lets an older payment without an invoice be edited without picking one', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $purchase = pbPurchase(pbSupplier(), 10000);
    $payment = pbRecordPayment($purchase, 2000, null);

    $payload = pbPay($purchase, 2500, ['payment_number' => $payment->payment_number]);
    unset($payload['purchase_id']);

    $this->put(route('transactions.payable-payments.update', $payment), $payload)->assertSessionHasNoErrors();

    expect($payment->fresh()->amount)->toEqual(2500)
        ->and($payment->fresh()->purchase_id)->toBeNull();
});

it('lists the open invoices of a supplier for the payment form', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $supplier = pbSupplier();
    $open = pbPurchase($supplier, 10000);
    $settled = pbPurchase($supplier, 2000);
    pbRecordPayment($settled, 2000);
    pbPurchase(pbSupplier('S-2'), 999);

    $response = $this->getJson(route('transactions.payable-payments.invoices', ['supplier_id' => $supplier->id]))
        ->assertOk();

    expect(collect($response->json('invoices'))->pluck('id')->all())->toBe([$open->id]);
});

it('keeps the invoice of a payment being edited in the list', function () {
    pbLogin(['transactions.payable-payments.manage']);
    $purchase = pbPurchase(pbSupplier(), 2000);
    $payment = pbRecordPayment($purchase, 2000);

    $without = $this->getJson(route('transactions.payable-payments.invoices', ['supplier_id' => $purchase->supplier_id]));
    $with = $this->getJson(route('transactions.payable-payments.invoices', [
        'supplier_id' => $purchase->supplier_id, 'payment_id' => $payment->id,
    ]));

    expect($without->json('invoices'))->toBe([])
        ->and($with->json('invoices.0.outstanding'))->toEqual(2000);
});

it('keeps the invoice list behind the payment permission', function () {
    pbLogin([]);

    $this->getJson(route('transactions.payable-payments.invoices', ['supplier_id' => pbSupplier()->id]))
        ->assertForbidden();
});

it('refuses to delete or shrink a purchase that already has payments', function () {
    pbLogin(['transactions.purchases.manage']);
    $supplier = pbSupplier();
    $product = Product::create(['code' => 'P-1', 'name' => 'Pupuk']);
    $purchase = pbPurchase($supplier, 5000);
    pbRecordPayment($purchase, 4000);

    $this->delete(route('transactions.purchases.destroy', $purchase))->assertSessionHas('error');
    expect(Purchase::count())->toBe(1);

    // 2 x 1000 = 2000 would no longer cover the 4000 already paid.
    $this->put(route('transactions.purchases.update', $purchase), [
        'invoice_number' => $purchase->invoice_number,
        'purchase_date' => $purchase->purchase_date,
        'status' => 'received',
        'supplier_id' => $supplier->id,
        'warehouse_id' => pbWarehouse()->id,
        'items' => [['product_id' => $product->id, 'qty' => 2, 'price' => 1000]],
    ])->assertSessionHasErrors('items');
    expect($purchase->fresh()->total_amount)->toEqual(5000);
});

// ---- Reminders -------------------------------------------------------------------------

it('starts reminding 7 days before the due date, which is day 23 of 30', function () {
    $supplier = pbSupplier();
    $early = pbPurchase($supplier, 1000, 8);   // day 22
    $soon = pbPurchase($supplier, 1000, 7);    // day 23
    $today = pbPurchase($supplier, 1000, 0);
    $late = pbPurchase($supplier, 1000, -3);

    $reminders = app(PayableService::class)->reminders();

    expect($reminders->pluck('purchase.id')->all())->toBe([$late->id, $today->id, $soon->id])
        ->and($reminders->pluck('state')->all())->toBe(['overdue', 'today', 'soon'])
        ->and($reminders->pluck('purchase.id')->contains($early->id))->toBeFalse();
});

it('stops reminding once the invoice is paid in full', function () {
    $purchase = pbPurchase(pbSupplier(), 5000, 3);
    expect(app(PayableService::class)->reminders())->toHaveCount(1);

    pbRecordPayment($purchase, 2000);
    expect(app(PayableService::class)->reminders()->first()['outstanding'])->toEqual(3000.0);

    pbRecordPayment($purchase, 3000);
    expect(app(PayableService::class)->reminders())->toHaveCount(0);
});

it('does not remind about pending or cancelled purchases', function () {
    $supplier = pbSupplier();
    pbPurchase($supplier, 1000, 2, 'pending');
    pbPurchase($supplier, 1000, 2, 'cancelled');

    expect(app(PayableService::class)->reminders())->toHaveCount(0);
});

// ---- Navbar and pop-up -----------------------------------------------------------------

it('shows reminders in the navbar bell only to users with the notification permission', function () {
    $purchase = pbPurchase(pbSupplier(), 5000, 2);

    pbLogin(['transactions.payable-payments.view', 'notifications.view']);
    $this->get(route('transactions.payable-payments.index'))
        ->assertOk()
        ->assertSee('id="notifMenu"', false)
        ->assertSee($purchase->invoice_number);

    pbLogin(['transactions.payable-payments.view']);
    $this->get(route('transactions.payable-payments.index'))
        ->assertOk()
        ->assertDontSee('id="notifMenu"', false)
        ->assertDontSee($purchase->invoice_number);
});

it('shows an empty bell when nothing is due', function () {
    pbPurchase(pbSupplier(), 5000, 20);
    pbLogin(['transactions.payable-payments.view', 'notifications.view']);

    $this->get(route('transactions.payable-payments.index'))
        ->assertOk()
        ->assertSee(__('app.notifications.empty'));
});

it('shows the pop-up once after login, and only when something is due', function () {
    $user = pbLogin(['transactions.payable-payments.view', 'notifications.view']);
    auth()->logout();
    pbPurchase(pbSupplier(), 5000, 2);

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'secret-password'])
        ->assertRedirect();

    $this->get(route('transactions.payable-payments.index'))->assertSee('id="duePopup"', false);
    $this->get(route('transactions.payable-payments.index'))->assertDontSee('id="duePopup"', false);
});

it('does not show the pop-up when no invoice is due soon', function () {
    $user = pbLogin(['transactions.payable-payments.view', 'notifications.view']);
    auth()->logout();
    pbPurchase(pbSupplier(), 5000, 20);

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'secret-password']);

    $this->get(route('transactions.payable-payments.index'))->assertDontSee('id="duePopup"', false);
});

it('does not show the pop-up to a user without the notification permission', function () {
    $user = pbLogin(['transactions.payable-payments.view']);
    auth()->logout();
    pbPurchase(pbSupplier(), 5000, 2);

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'secret-password']);

    $this->get(route('transactions.payable-payments.index'))->assertDontSee('id="duePopup"', false);
});
