<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveStockRequest;
use App\Models\InventoryItem;
use App\Services\Stock\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReceiveStockController extends Controller
{
    public function create(InventoryItem $item): View
    {
        return view('inventory.receive', ['item' => $item]);
    }

    public function store(ReceiveStockRequest $request, InventoryItem $item, StockService $stockService): RedirectResponse
    {
        $stockService->addToItem($item->id, (float) $request->validated('quantity'));

        $item->refresh();

        return to_route('inventory.index')->with('success', __('Received :quantity of ":name". Stock now: :stock.', [
            'quantity' => $item->formatQuantity($request->validated('quantity')),
            'name' => $item->name,
            'stock' => $item->formatQuantity($item->current_stock),
        ]));
    }
}
