<?php

namespace App\Services\Finance;

use App\Enums\ExpenseKind;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\Tag;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Purchases from suppliers. A purchase is an expense of kind "supplier" with item lines.
 * Activating it (receive) adds every line to stock, once; it can be undone (unreceive) to fix
 * the lines. Quantities are typed in kg / L / un and stored in the item's unit (g / ml / un).
 *
 * E.g. 60 un "Pão brioche" for R$ 54,00 → +60 un in stock, cost R$ 0,90/un.
 */
class SupplierPurchaseService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Creates or updates a purchase. Lines can only change while it is not received.
     *
     * @param  array{supplier_id: int|string, spent_on: string, description?: ?string, notes?: ?string,
     *     tags?: list<string>, items: list<array{inventory_item_id: int|string, quantity: numeric-string|float, total: numeric-string|float}>}  $data
     *     items[].quantity is in kg / L / un (the purchase unit).
     */
    public function save(Expense $purchase, array $data, ?int $userId = null, bool $receive = false, bool $updateCosts = true): Expense
    {
        return DB::transaction(function () use ($purchase, $data, $userId, $receive, $updateCosts): Expense {
            if ($purchase->exists && $purchase->isReceived() && isset($data['items'])) {
                throw new LogicException('A received purchase keeps its items; undo the stock entry first.');
            }

            $items = InventoryItem::query()->withTrashed()->whereKey(array_column($data['items'] ?? [], 'inventory_item_id'))->get()->keyBy('id');
            $lines = collect($data['items'] ?? [])->map(function (array $line) use ($items): array {
                $item = $items->get((int) $line['inventory_item_id']);

                return [
                    'inventory_item_id' => $item->id,
                    'quantity' => round((float) $line['quantity'] * $item->unit_of_measure->costDisplayFactor(), 3),
                    'total' => round((float) $line['total'], 2),
                ];
            });

            $purchase->fill([
                'kind' => ExpenseKind::Supplier,
                'supplier_id' => (int) $data['supplier_id'],
                'spent_on' => $data['spent_on'],
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'category' => null,
                'employee_name' => null,
                'employee_role' => null,
            ]);

            if ($lines->isNotEmpty()) {
                $purchase->amount = round($lines->sum('total'), 2);
            }

            if (! $purchase->exists) {
                $purchase->created_by = $userId;
            }

            $purchase->save();

            if ($lines->isNotEmpty()) {
                $purchase->items()->delete();
                $purchase->items()->createMany($lines->all());

                // Remember what this supplier sells, so the next purchase starts with it.
                Supplier::query()->findOrFail($purchase->supplier_id)->items()->syncWithoutDetaching($lines->pluck('inventory_item_id')->all());
            }

            $purchase->tags()->sync($this->tagIds($data['tags'] ?? []));

            if ($receive) {
                $this->receive($purchase, $updateCosts);
            }

            return $purchase->load(['items.inventoryItem', 'supplier', 'tags']);
        });
    }

    /**
     * "Activate" the purchase: add every line to stock and, optionally, make each item's cost the
     * price paid here. Runs at most once (the purchase row is locked and checked).
     */
    public function receive(Expense $purchase, bool $updateCosts = true): bool
    {
        return DB::transaction(function () use ($purchase, $updateCosts): bool {
            $locked = Expense::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($locked->received_at !== null) {
                return false;
            }

            foreach ($locked->items()->with('inventoryItem')->get() as $line) {
                $this->stock->addToItem($line->inventory_item_id, (float) $line->quantity);

                if ($updateCosts && (float) $line->quantity > 0) {
                    InventoryItem::query()->withTrashed()->whereKey($line->inventory_item_id)
                        ->update(['cost_per_unit' => round((float) $line->total / (float) $line->quantity, 6)]);
                }
            }

            $locked->forceFill(['received_at' => now()])->save();
            $purchase->received_at = $locked->received_at;

            return true;
        });
    }

    /** Undo the stock entry (to fix the lines). Item costs are left as they are. */
    public function unreceive(Expense $purchase): bool
    {
        return DB::transaction(function () use ($purchase): bool {
            $locked = Expense::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($locked->received_at === null) {
                return false;
            }

            foreach ($locked->items as $line) {
                $this->stock->deductFromItem($line->inventory_item_id, (float) $line->quantity);
            }

            $locked->forceFill(['received_at' => null])->save();
            $purchase->received_at = null;

            return true;
        });
    }

    /** Deletes a purchase; if it was received, its items leave stock again. */
    public function delete(Expense $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $this->unreceive($purchase);
            $purchase->delete();
        });
    }

    /** @param  list<string>  $names */
    private function tagIds(array $names): array
    {
        return collect($names)
            ->map(fn (string $name): string => Tag::normalize($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->map(fn (string $name): int => Tag::findOrCreateByName($name)->id)
            ->values()
            ->all();
    }
}
