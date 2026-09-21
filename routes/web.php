<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminMessageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{inboxMessage}', [InboxController::class, 'show'])->name('inbox.show');
    Route::delete('/inbox/{inboxMessage}', [InboxController::class, 'destroy'])->name('inbox.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/games', [GameController::class, 'store'])->name('games.store');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('/games/{game}/advance-day', [GameController::class, 'advance'])->name('games.advance-day');
    Route::post('/games/{game}/tutorial/complete', [GameController::class, 'completeTutorial'])->name('games.tutorial.complete');
    Route::get('/games/{game}/products', [ProductController::class, 'index'])->name('games.products.index');
    Route::get('/games/{game}/sales/{customerOrder}', [SalesOrderController::class, 'show'])->name('games.sales.show');
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

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'overview'])->name('overview');
    Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [AdminMessageController::class, 'store'])->name('messages.store');
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::get('/games', [AdminController::class, 'games'])->name('games.index');
    Route::patch('/games/{game}', [AdminController::class, 'updateGame'])->name('games.update');
    Route::delete('/games/{game}', [AdminController::class, 'destroyGame'])->name('games.destroy');
    Route::get('/population', [AdminController::class, 'population'])->name('population.index');
    Route::post('/population', [AdminController::class, 'storePopulation'])->name('population.store');
    Route::patch('/population/{populationNpc}', [AdminController::class, 'updatePopulation'])->name('population.update');
    Route::delete('/population/{populationNpc}', [AdminController::class, 'destroyPopulation'])->name('population.destroy');
    Route::get('/products', [AdminController::class, 'products'])->name('products.index');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('products.store');
    Route::patch('/products/{productTemplate}', [AdminController::class, 'updateProduct'])->name('products.update');
    Route::delete('/products/{productTemplate}', [AdminController::class, 'destroyProduct'])->name('products.destroy');
    Route::get('/job-roles', [AdminController::class, 'jobRoles'])->name('job-roles.index');
    Route::post('/job-roles', [AdminController::class, 'storeJobRole'])->name('job-roles.store');
    Route::patch('/job-roles/{jobRole}', [AdminController::class, 'updateJobRole'])->name('job-roles.update');
    Route::delete('/job-roles/{jobRole}', [AdminController::class, 'destroyJobRole'])->name('job-roles.destroy');
});
