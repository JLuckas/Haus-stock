<?php

namespace App\Http\Controllers;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Http\Requests\StoreManualOrderRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\Stock\BillOfMaterials;
use App\Services\Stock\ManualOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** "New order": register a sale by hand, with any changes the customer asked for, and deduct its stock right away. */
class ManualOrderController extends Controller
{
    public function create(BillOfMaterials $billOfMaterials): View
    {
        $products = Product::query()->with(['ingredients', 'components', 'standaloneItem', 'category'])->orderBy('name')->get();
        $items = InventoryItem::query()->with('category')->orderBy('name')->get();

        // What one unit of each product takes from stock (all levels), and the products directly inside it.
        $plans = $products->mapWithKeys(function (Product $product) use ($billOfMaterials): array {
            try {
                return [$product->id => [
                    'items' => (object) $billOfMaterials->perUnit($product),
                    'components' => (object) $product->components->mapWithKeys(fn (Product $c): array => [$c->id => (float) $c->pivot->quantity])->all(),
                ]];
            } catch (InvalidRecipeException $e) {
                return [$product->id => ['error' => $e->getMessage()]];
            }
        });

        return view('orders.create', [
            'products' => $products,
            'orderState' => [
                'products' => $products->mapWithKeys(fn (Product $p): array => [$p->id => [
                    'name' => $p->name, 'price' => (float) $p->price, 'category' => $p->category_id,
                ]]),
                'items' => $items->mapWithKeys(fn (InventoryItem $i): array => [$i->id => [
                    'name' => $i->name, 'unit' => $i->unit_of_measure->value, 'category' => $i->category_id,
                ]]),
                'plans' => $plans,
                'categories' => Category::query()->get(['id', 'name', 'color'])->keyBy('id'),
                'lines' => old('items') ?: [['product_id' => '', 'quantity' => '1']],
                'errors' => session('errors')?->getBag('default')->getMessages() ?? [],
            ],
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
