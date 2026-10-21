<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ToppingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/welcome');
    Route::view('/welcome', 'welcome')->name('welcome');
    Route::get('/store-logo', [SettingController::class, 'storeLogo'])->name('settings.store-logo');
    Route::get('/store-status', [SettingController::class, 'status'])->name('store.status');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/qris-image', [PosController::class, 'qrisImage'])->name('pos.qris-image');
    Route::get('/toppings/{topping}/image', [ToppingController::class, 'image'])->name('toppings.image');
    Route::get('/products/{product}/image', [ProductController::class, 'image'])->name('products.image');

    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders-store')->name('orders.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('throttle:orders-status')->name('orders.status');
    Route::get('/orders/{order}/struk', [OrderController::class, 'receipt'])->name('orders.receipt');

    Route::middleware(['auth', 'active', 'role:admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', ProductController::class)->except('show');
        Route::resource('toppings', ToppingController::class)->except('show');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);

        Route::get('/settings/qris', [SettingController::class, 'edit'])->name('settings.qris.edit');
        Route::put('/settings/qris', [SettingController::class, 'update'])->name('settings.qris.update');

        Route::get('/settings/store-profile', [SettingController::class, 'editStoreProfile'])->name('settings.store-profile.edit');
        Route::put('/settings/store-profile', [SettingController::class, 'updateStoreProfile'])->name('settings.store-profile.update');
        Route::put('/settings/store-profile/open', [SettingController::class, 'openStore'])->name('settings.store-profile.open');
        Route::put('/settings/store-profile/close', [SettingController::class, 'closeStore'])->name('settings.store-profile.close');
    });
});
