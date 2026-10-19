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

Route::resource('categories', CategoryController::class)->except('show');
Route::resource('products', ProductController::class)->except('show');
Route::resource('toppings', ToppingController::class)->except('show');
