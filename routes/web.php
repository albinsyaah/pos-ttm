<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\BrandController;
use App\Http\Controllers\Inventory\ItemTypeController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductGroupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

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
