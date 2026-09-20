<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/games', [GameController::class, 'store'])->name('games.store');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('/games/{game}/advance-day', [GameController::class, 'advance'])->name('games.advance-day');
    Route::post('/games/{game}/tutorial/complete', [GameController::class, 'completeTutorial'])->name('games.tutorial.complete');
    Route::get('/games/{game}/products', [ProductController::class, 'index'])->name('games.products.index');
    Route::patch('/games/{game}/products/{product}', [ProductController::class, 'update'])->name('games.products.update');
    Route::get('/games/{game}/purchases', [PurchaseController::class, 'index'])->name('games.purchases.index');
    Route::post('/games/{game}/purchase-orders', [PurchaseController::class, 'store'])->name('games.purchase-orders.store');
    Route::post('/games/{game}/purchase-orders/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->name('games.purchase-orders.receive');
    Route::get('/games/{game}/inventory', [InventoryController::class, 'index'])->name('games.inventory.index');
    Route::get('/games/{game}/finance', [FinanceController::class, 'index'])->name('games.finance.index');
    Route::get('/games/{game}/reports', [ReportController::class, 'index'])->name('games.reports.index');
    Route::get('/games/{game}/team', [TeamController::class, 'index'])->name('games.team.index');
    Route::post('/games/{game}/employees', [TeamController::class, 'store'])->name('games.employees.store');
    Route::delete('/games/{game}/employees/{employee}', [TeamController::class, 'terminate'])->name('games.employees.terminate');
    Route::get('/games/{game}/customers', [CustomerController::class, 'index'])->name('games.customers.index');
    Route::get('/games/{game}/customers/{customer}', [CustomerController::class, 'show'])->name('games.customers.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
