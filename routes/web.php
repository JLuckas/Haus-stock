<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\ManualDeductionController;
use App\Http\Controllers\ManualOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitAndLossController;
use App\Http\Controllers\ProfitabilityController;
use App\Http\Controllers\ReceiveStockController;
use App\Http\Controllers\RetryOrderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('inventory', InventoryItemController::class)
        ->except('show')
        ->parameters(['inventory' => 'item']);
    Route::get('inventory/{item}/receive', [ReceiveStockController::class, 'create'])->name('inventory.receive');
    Route::post('inventory/{item}/receive', [ReceiveStockController::class, 'store'])->name('inventory.receive.store');

    Route::get('products/costs', [ProductCostController::class, 'index'])->name('products.costs');
    Route::put('products/costs/target-margin', [ProductCostController::class, 'updateTargetMargin'])->name('products.target-margin');
    Route::resource('products', ProductController::class)->except('show');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [ManualOrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [ManualOrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/retry', RetryOrderController::class)->name('orders.retry');

    Route::get('expenses', fn () => to_route('expenses.index', 'personnel'))->name('expenses');
    Route::get('expenses/{kind}', [ExpenseController::class, 'index'])->whereIn('kind', ['personnel', 'general'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->whereNumber('expense')->name('expenses.edit');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->whereNumber('expense')->name('expenses.destroy');

    Route::get('reports/results', ProfitAndLossController::class)->name('reports.pnl');
    Route::get('reports/profitability', ProfitabilityController::class)->name('reports.profitability');

    Route::resource('manual-deductions', ManualDeductionController::class)->only(['index', 'create', 'store']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
