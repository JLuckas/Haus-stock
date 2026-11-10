<?php

namespace App\Http\Controllers;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Http\Requests\StoreManualDeductionRequest;
use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Product;
use App\Services\Stock\ManualDeductionService;
use App\Services\Stock\ProductBreakdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManualDeductionController extends Controller
{
    /** Suggestions shown in the reason field (translated when shown); any free text is accepted. */
    public const REASONS = ['Employee meal', 'Waste / spoilage', 'Tasting / quality check', 'Courtesy', 'Stock count correction'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'item' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')],
        ]);

        $deductions = ManualDeduction::query()
            ->with(['inventoryItem', 'product', 'user', 'lines.inventoryItem'])
            // Matches direct item deductions and products whose recipe used the item.
            ->when($filters['item'] ?? null, fn ($q, $itemId) => $q->whereHas('lines', fn ($l) => $l->where('inventory_item_id', $itemId)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('manual-deductions.index', [
            'deductions' => $deductions,
            'inventory' => InventoryItem::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, ProductBreakdownService $breakdowns): View
    {
        $inventory = InventoryItem::query()->orderBy('name')->get();
        $products = Product::query()->orderBy('name')->get();

        return view('manual-deductions.create', [
            'inventory' => $inventory,
            'products' => $products,
            'productBreakdowns' => $breakdowns->forProducts($products),
            'selectedItemId' => $request->integer('item') ?: null,
            'selectedProductId' => $request->integer('product') ?: null,
            'reasons' => array_map(fn (string $reason): string => __($reason), self::REASONS),
        ]);
    }

    public function store(StoreManualDeductionRequest $request, ManualDeductionService $service): RedirectResponse
    {
        $quantity = (float) $request->validated('quantity');
        $reason = $request->validated('reason');
        $userId = $request->user()->id;

        if ($request->isProduct()) {
            try {
                $deduction = $service->deductProduct((int) $request->validated('product_id'), $quantity, $reason, $userId);
            } catch (InvalidRecipeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            return to_route('manual-deductions.index')->with('success', __('Deducted :quantity ":name": :lines.', [
                'quantity' => $deduction->formattedQuantity(),
                'name' => $deduction->product->name,
                'lines' => $deduction->lines->map(fn ($line) => $line->inventoryItem->formatQuantity($line->quantity).' '.$line->inventoryItem->displayName())->join(', '),
            ]));
        }

        $deduction = $service->deduct((int) $request->validated('inventory_item_id'), $quantity, $reason, $userId);
        $item = $deduction->inventoryItem;

        return to_route('manual-deductions.index')->with('success', __('Deducted :quantity of ":name". Stock now: :stock.', [
            'quantity' => $item->formatQuantity($deduction->quantity_deducted),
            'name' => $item->name,
            'stock' => $item->formatQuantity($item->current_stock),
        ]));
    }
}
