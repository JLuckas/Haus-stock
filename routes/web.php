<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseSeriesController;
use App\Http\Controllers\ExpenseTagController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPurchaseController;
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
    // Supplier purchases ({purchase} is an expense of kind "supplier") and the suppliers themselves.
    Route::bind('purchase', fn (string $id) => \App\Models\Expense::query()->where('kind', \App\Enums\ExpenseKind::Supplier)->findOrFail($id));
    Route::post('expenses/purchases', [SupplierPurchaseController::class, 'store'])->name('expenses.purchases.store');
    Route::get('expenses/purchases/{purchase}/edit', [SupplierPurchaseController::class, 'edit'])->whereNumber('purchase')->name('expenses.purchases.edit');
    Route::put('expenses/purchases/{purchase}', [SupplierPurchaseController::class, 'update'])->whereNumber('purchase')->name('expenses.purchases.update');
    Route::post('expenses/purchases/{purchase}/receive', [SupplierPurchaseController::class, 'receive'])->whereNumber('purchase')->name('expenses.purchases.receive');
    Route::post('expenses/purchases/{purchase}/unreceive', [SupplierPurchaseController::class, 'unreceive'])->whereNumber('purchase')->name('expenses.purchases.unreceive');
    Route::delete('expenses/purchases/{purchase}', [SupplierPurchaseController::class, 'destroy'])->whereNumber('purchase')->name('expenses.purchases.destroy');
    Route::get('expenses/suppliers', [SupplierController::class, 'index'])->name('expenses.suppliers.index');
    Route::post('expenses/suppliers', [SupplierController::class, 'store'])->name('expenses.suppliers.store');
    Route::put('expenses/suppliers/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('expenses.suppliers.update');
    Route::delete('expenses/suppliers/{supplier}', [SupplierController::class, 'destroy'])->whereNumber('supplier')->name('expenses.suppliers.destroy');
    Route::get('expenses/recurring', [ExpenseSeriesController::class, 'index'])->name('expenses.series.index');
    Route::delete('expenses/recurring/{series}', [ExpenseSeriesController::class, 'destroy'])->whereNumber('series')->name('expenses.series.destroy');
    Route::get('expenses/tags', [ExpenseTagController::class, 'index'])->name('expenses.tags.index');
    Route::post('expenses/tags', [ExpenseTagController::class, 'store'])->name('expenses.tags.store');
    Route::put('expenses/tags/{tag}', [ExpenseTagController::class, 'update'])->whereNumber('tag')->name('expenses.tags.update');
    Route::delete('expenses/tags/{tag}', [ExpenseTagController::class, 'destroy'])->whereNumber('tag')->name('expenses.tags.destroy');
    Route::get('expenses/{kind}', [ExpenseController::class, 'index'])->whereIn('kind', \App\Enums\ExpenseKind::values())->name('expenses.index');
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
