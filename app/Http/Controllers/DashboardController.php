<?php

namespace App\Http\Controllers;

use App\Enums\OrderSource;
use App\Enums\OrderStockState;
use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Order;
use App\Models\Product;
use App\Services\Finance\ProfitAndLossService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ProfitAndLossService $pnl): View
    {
        return view('dashboard', [
            // Money at a glance: today and the month so far (same numbers as Finance → Results).
            'today' => $pnl->report(today(), today()),
            'month' => $pnl->report(today()->startOfMonth(), today()),
            // Most critical first: largest shortfall against the minimum.
            'lowStockItems' => InventoryItem::query()
                ->belowMinimum()
                ->orderByRaw('(current_stock - minimum_stock) ASC')
                ->limit(10)
                ->get(),
            'lowStockCount' => InventoryItem::query()->belowMinimum()->count(),
            'inventoryCount' => InventoryItem::query()->count(),
            'processedToday' => Order::query()->inStockState(OrderStockState::Processed)
                ->where('stock_processed_at', '>=', today())->count(),
            'failedCount' => Order::query()->inStockState(OrderStockState::Failed)->count(),
            'failedOrders' => Order::query()->inStockState(OrderStockState::Failed)->latest()->limit(5)->get(),
            'deductionsToday' => ManualDeduction::query()->where('created_at', '>=', today())->count(),
            'recentDeductions' => ManualDeduction::query()->with(['inventoryItem', 'product', 'user'])->latest()->limit(5)->get(),
            'unmappedProducts' => Product::query()->unmapped()->orderBy('name')->get(),
            // Rough poller health signal: when did the last order arrive?
            'lastOrderAt' => Order::query()->where('source', OrderSource::Saipos)->max('created_at'),
        ]);
    }
}
