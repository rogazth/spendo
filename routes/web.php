<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('accounts', AccountController::class)->except(['create', 'edit']);
    Route::patch('accounts/{account}/make-default', [AccountController::class, 'makeDefault'])
        ->name('accounts.make-default');
    Route::resource('payment-methods', PaymentMethodController::class)->except(['create', 'edit']);
    Route::patch('payment-methods/{payment_method}/toggle-active', [PaymentMethodController::class, 'toggleActive'])
        ->name('payment-methods.toggle-active');
    Route::patch('payment-methods/{payment_method}/make-default', [PaymentMethodController::class, 'makeDefault'])
        ->name('payment-methods.make-default');
    Route::resource('categories', CategoryController::class)->except(['create', 'edit']);
    Route::resource('transactions', TransactionController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/settings.php';
