<?php

namespace App\Http\Controllers;

use App\Enums\InventoryItemType;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(InventoryItemType::class)],
            'low' => ['nullable', 'boolean'],
        ]);

        $items = InventoryItem::query()
            ->search($filters['search'] ?? null)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['low'] ?? false, fn ($q) => $q->belowMinimum())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', ['items' => $items, 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('inventory.create', ['item' => new InventoryItem(['current_stock' => 0, 'minimum_stock' => 0])]);
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        $item = InventoryItem::query()->create($request->inventoryAttributes());

        return to_route('inventory.index')->with('success', __('":name" added to inventory.', ['name' => $item->name]));
    }

    public function edit(InventoryItem $item): View
    {
        return view('inventory.edit', ['item' => $item]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->inventoryAttributes());

        return to_route('inventory.index')->with('success', __('":name" updated.', ['name' => $item->name]));
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $blockers = $item->deletionBlockers();

        if ($blockers !== []) {
            return back()->with('error', __('":name" can\'t be deleted because :reasons. Remove it from those products first.', [
                'name' => $item->name,
                'reasons' => implode(', '.__('and').' ', $blockers),
            ]));
        }

        // Soft delete: past deductions keep showing this item.
        $item->delete();

        return to_route('inventory.index')->with('success', __('":name" deleted. Its past deductions stay in the history.', ['name' => $item->name]));
    }
}
