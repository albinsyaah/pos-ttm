<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\CashFlowController;
use App\Http\Controllers\Finance\ChartOfAccountController;
use App\Http\Controllers\Finance\GeneralLedgerController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\SalesmanController;
use App\Http\Controllers\Inventory\BrandController;
use App\Http\Controllers\Inventory\ItemTypeController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductGroupController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Pricing\PriceSetupController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Transactions\ApPaymentController;
use App\Http\Controllers\Transactions\PointOfSaleController;
use App\Http\Controllers\Transactions\PointOfSaleNewController;
use App\Http\Controllers\Transactions\PurchaseController;
use App\Http\Controllers\Transactions\PurchaseOrderController;
use App\Http\Controllers\Transactions\PurchaseReturnController;
use App\Http\Controllers\Transactions\SaleController;
use App\Http\Controllers\Transactions\SalesOrderController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Language switch: available to guests (e.g. on the login page) and to
// signed-in users alike, so it isn't nested inside the auth group below.
Route::get('/language/{locale}', [LanguageController::class, 'switch'])
    ->whereIn('locale', ['en', 'id'])
    ->name('language.switch');

// Guest-only auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Everything below requires a signed-in, non-disabled account.
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // Per-action permission checks live on the controller itself via
    // HasMiddleware (see CustomerController::middleware()).
    Route::resource('customers', CustomerController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Inventory: brands (Merk), item types (Jenis Barang), product groups
    // (Grup Produk) and products (Barang). Per-action permission checks live
    // on each controller via HasMiddleware (permission:inventory.view /
    // permission:inventory.manage).
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('brands', BrandController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('item-types', ItemTypeController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['item-types' => 'itemType']);

        Route::resource('product-groups', ProductGroupController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['product-groups' => 'productGroup']);

        Route::resource('products', ProductController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

    // Fixed assets. Per-action permission checks live on the controller via
    // HasMiddleware (permission:assets.view / permission:assets.manage).
    Route::resource('assets', AssetController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Setup Harga (price setups). Per-action permission checks live on the
    // controller via HasMiddleware (permission:pricing.view / permission:pricing.manage).
    Route::prefix('pricing')->name('pricing.')->group(function () {
        Route::resource('price-setups', PriceSetupController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['price-setups' => 'priceSetup']);
    });

    // Keuangan (Finance): chart of accounts, cash flow, general ledger.
    // Per-action permission checks live on each controller via HasMiddleware
    // (permission:finance.view / permission:finance.manage).
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::resource('chart-of-accounts', ChartOfAccountController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['chart-of-accounts' => 'account']);

        Route::resource('cash-flows', CashFlowController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['cash-flows' => 'cashFlow']);

        Route::resource('general-ledgers', GeneralLedgerController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['general-ledgers' => 'generalLedger']);
    });

    // Supplier. Per-action permission checks live on the controller via
    // HasMiddleware (permission:suppliers.view / permission:suppliers.manage).
    Route::resource('suppliers', SupplierController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Gudang (Warehouse). Per-action permission checks live on the controller
    // via HasMiddleware (permission:warehouses.view / permission:warehouses.manage).
    Route::resource('warehouses', WarehouseController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Kepegawaian (HR): employees and salesmen. Salesmen are Employee records
    // filtered/forced to position "Salesman" (see Hr\SalesmanController).
    // Per-action permission checks live on each controller via HasMiddleware
    // (permission:hr.view / permission:hr.manage).
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::resource('employees', EmployeeController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('salesmen', SalesmanController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['salesmen' => 'salesman']);
    });

    // Transaksi (Transactions): Account Payable — purchase orders, purchases,
    // purchase returns, and payable (AP) payments. Per-action permission
    // checks live on each controller via HasMiddleware
    // (permission:transactions.view / permission:transactions.manage).
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['purchase-orders' => 'purchaseOrder']);

        Route::resource('purchases', PurchaseController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::resource('purchase-returns', PurchaseReturnController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['purchase-returns' => 'purchaseReturn']);

        Route::resource('payable-payments', ApPaymentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['payable-payments' => 'payablePayment']);

        Route::resource('sales-orders', SalesOrderController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['sales-orders' => 'salesOrder']);

        Route::resource('sales', SaleController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('point-of-sale-new', [PointOfSaleNewController::class, 'index'])->name('point-of-sale-new.index');
        Route::post('point-of-sale-new', [PointOfSaleNewController::class, 'store'])->name('point-of-sale-new.store');

        Route::resource('point-of-sale', PointOfSaleController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['point-of-sale' => 'pointOfSale']);
    });

    // Super Admin only: user accounts + role/permission management.
    Route::prefix('admin')->name('admin.')->middleware('role:Super Admin')->group(function () {
        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        Route::resource('roles', RoleController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
});
