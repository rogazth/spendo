<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TagController;
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

    Route::resource('accounts', AccountController::class)->except(['create', 'edit', 'show']);
    Route::patch('accounts/{account}/make-default', [AccountController::class, 'makeDefault'])
        ->name('accounts.make-default');
    Route::resource('categories', CategoryController::class)->except(['create', 'edit']);
    Route::resource('budgets', BudgetController::class)->only(['index', 'store', 'show']);
    Route::resource('transactions', TransactionController::class)->except(['show', 'create', 'edit']);
    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/settings.php';
