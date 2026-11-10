<?php

namespace App\Http\Controllers;

use App\Enums\OrderStockState;
use App\Models\Order;
use App\Models\Product;
use App\Services\Saipos\SaiposIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::enum(OrderStockState::class)],
        ]);

        $orders = Order::query()
            ->with('creator')
            ->when($filters['state'] ?? null, fn ($q, $state) => $q->inStockState(OrderStockState::from($state)))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('saipos_order_id', 'like', '%'.trim($search).'%'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('orders.index', ['orders' => $orders, 'filters' => $filters]);
    }

    public function show(Order $order, SaiposIntegrationService $saipos): View
    {
        // Re-read the stored payload the same way the poller does, so the page shows exactly what is deducted.
        $normalized = $saipos->normalizeOrder($order->raw_payload ?? [], $order->saipos_order_id);

        $products = Product::query()
            ->with(['ingredients', 'standaloneItem'])
            ->whereIn('saipos_sku_id', collect($normalized->items)->pluck('skuId')->filter()->unique())
            ->get()
            ->keyBy('saipos_sku_id');

        return view('orders.show', [
            'order' => $order,
            'items' => $normalized->items,
            'products' => $products,
        ]);
    }
}
