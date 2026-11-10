<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualOrderRequest;
use App\Models\Product;
use App\Services\Stock\ManualOrderService;
use App\Services\Stock\ProductBreakdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** "New order": register a sale by hand and deduct its stock right away. */
class ManualOrderController extends Controller
{
    public function create(ProductBreakdownService $breakdowns): View
    {
        $products = Product::query()->orderBy('name')->get();

        return view('orders.create', [
            'products' => $products,
            'breakdowns' => $breakdowns->forProducts($products),
        ]);
    }

    public function store(StoreManualOrderRequest $request, ManualOrderService $orders): RedirectResponse
    {
        $order = $orders->create(
            $request->validated('items'),
            $request->validated('reference'),
            $request->validated('notes'),
            $request->user()->id,
        );

        $replace = ['order' => $order->saipos_order_id];

        return $order->is_stock_processed
            ? to_route('orders.show', $order)->with('success', __('Order :order registered and stock deducted.', $replace))
            : to_route('orders.show', $order)->with('error', __('Order :order was registered, but its stock was not deducted. See the problem below.', $replace));
    }
}
