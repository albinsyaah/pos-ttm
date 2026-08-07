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
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Pricing\PriceSetupController;
use App\Http\Controllers\Reports\PurchaseOrderReportController;
use App\Http\Controllers\Reports\PurchaseReportController;
use App\Http\Controllers\Reports\PurchaseReturnReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Transactions\ApPaymentController;
use App\Http\Controllers\Transactions\ArPaymentController;
use App\Http\Controllers\Transactions\CashManagementController;
use App\Http\Controllers\Transactions\DeviationController;
use App\Http\Controllers\Transactions\GeneralLedgerController as TransactionsGeneralLedgerController;
use App\Http\Controllers\Transactions\InternalExpenditureController;
use App\Http\Controllers\Transactions\InternalReceiptController;
use App\Http\Controllers\Transactions\ItemRequestController;
use App\Http\Controllers\Transactions\PointOfSaleController;
use App\Http\Controllers\Transactions\PointOfSaleNewController;
use App\Http\Controllers\Transactions\PurchaseController;
use App\Http\Controllers\Transactions\PurchaseOrderController;
use App\Http\Controllers\Transactions\PurchaseReturnController;
use App\Http\Controllers\Transactions\SaleController;
use App\Http\Controllers\Transactions\SalesOrderController;
use App\Http\Controllers\Transactions\SalesReturnController;
use App\Http\Controllers\Transactions\SalesSpgController;
use App\Http\Controllers\Transactions\WarehouseTransferController;
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

    // Inquiry: read-only product stock/price search tool. Permission check
    // lives on the route itself (single action, no HasMiddleware needed),
    // matching the dashboard route above.
    Route::get('/inquiry', [InquiryController::class, 'index'])
        ->middleware('permission:inquiry.view')
        ->name('inquiry.index');

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

        Route::resource('sales-returns', SalesReturnController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['sales-returns' => 'salesReturn']);

        Route::resource('sales-spg', SalesSpgController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['sales-spg' => 'salesSpg']);

        Route::resource('receivable-payments', ArPaymentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['receivable-payments' => 'receivablePayment']);

        // Internal Mutation: item requests, internal expenditures, internal
        // receipts, warehouse transfers and deviations are all InternalMutation
        // records, filtered/forced to a fixed "type" (see Transactions\ItemRequestController /
        // InternalExpenditureController / InternalReceiptController /
        // WarehouseTransferController / DeviationController, matching the
        // pattern used by Hr\SalesmanController).
        Route::resource('item-requests', ItemRequestController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['item-requests' => 'itemRequest']);

        Route::resource('internal-expenditures', InternalExpenditureController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['internal-expenditures' => 'internalExpenditure']);

        Route::resource('internal-receipts', InternalReceiptController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['internal-receipts' => 'internalReceipt']);

        Route::resource('warehouse-transfers', WarehouseTransferController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['warehouse-transfers' => 'warehouseTransfer']);

        Route::resource('deviations', DeviationController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['deviations' => 'deviation']);

        // Cash Management and General Ledger transaction entry pages. These
        // reuse the CashFlow/GeneralLedger models (see Finance module) but
        // are exposed here as day-to-day transaction entry screens.
        Route::resource('cash-management', CashManagementController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['cash-management' => 'cashManagement']);

        Route::resource('general-ledger', TransactionsGeneralLedgerController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['general-ledger' => 'generalLedger']);
    });

    // Report: read-only, filterable reports built on top of the same
    // migrations/models the transaction modules use. Permission check lives
    // on the route itself (single action, no HasMiddleware needed), matching
    // the pattern used by InquiryController above.
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('purchase-orders', [PurchaseOrderReportController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('purchase-orders');

        Route::get('purchases', [PurchaseReportController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('purchases');

        Route::get('purchase-returns', [PurchaseReturnReportController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('purchase-returns');
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
