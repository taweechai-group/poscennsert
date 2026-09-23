<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// ---------- Auth (PIN) ----------
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // ---------- POS (แคชเชียร์) ----------
    Route::middleware('role:cashier,admin')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/pos/sales', [PosController::class, 'sales'])->name('pos.sales');
    });

    // ---------- เบิกสินค้า ----------
    Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
    Route::post('/requisitions', [RequisitionController::class, 'store'])
        ->middleware('role:cashier,admin')->name('requisitions.store');
    Route::post('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
        ->middleware('role:warehouse,admin')->name('requisitions.approve');
    Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
        ->middleware('role:warehouse,admin')->name('requisitions.reject');

    // ---------- คลังกลาง ----------
    Route::middleware('role:warehouse,admin')->group(function () {
        Route::get('/warehouse', [WarehouseController::class, 'index'])->name('warehouse.index');
        Route::post('/warehouse/receive', [WarehouseController::class, 'receive'])->name('warehouse.receive');
        Route::post('/warehouse/adjust', [WarehouseController::class, 'adjust'])->name('warehouse.adjust');
        Route::get('/warehouse/movements', [WarehouseController::class, 'movements'])->name('warehouse.movements');
    });

    // ---------- เชียร์เบียร์ / เครดิต ----------
    Route::get('/sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::post('/sellers', [SellerController::class, 'store'])->name('sellers.store');
    Route::get('/sellers/{seller}', [SellerController::class, 'show'])->name('sellers.show');
    Route::put('/sellers/{seller}', [SellerController::class, 'update'])->name('sellers.update');
    Route::post('/sellers/{seller}/pay', [SellerController::class, 'pay'])->name('sellers.pay');

    // ---------- Admin ----------
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/products', [AdminController::class, 'products'])->name('products');
        Route::post('/products/{product?}', [AdminController::class, 'saveProduct'])->name('products.save');
        Route::get('/stations', [AdminController::class, 'stations'])->name('stations');
        Route::post('/stations', [AdminController::class, 'saveStation'])->name('stations.save');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'saveUser'])->name('users.save');
        Route::get('/report', [AdminController::class, 'report'])->name('report');
    });
});
