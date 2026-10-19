<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ToppingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pos.index'));

Route::get('/pos', [PosController::class, 'index'])->name('pos.index');

Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
Route::get('/orders/{order}/struk', [OrderController::class, 'receipt'])->name('orders.receipt');

Route::resource('categories', CategoryController::class)->except('show');
Route::resource('products', ProductController::class)->except('show');
Route::resource('toppings', ToppingController::class)->except('show');
