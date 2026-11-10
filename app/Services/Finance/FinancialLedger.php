<?php

namespace App\Services\Finance;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Order;
use App\Models\Product;

/**
 * Takes the money snapshot of each stock movement, at the moment it happens:
 *
 *  - a deducted order: revenue (price charged, minus discount) and production cost per item;
 *  - a manual deduction: the cost of what left stock (employee meal, waste…), i.e. a loss.
 *
 * Called inside the same DB transaction as the stock movement, so stock and money never disagree.
 * Costs come from inventory_items.cost_per_unit *now*; later price changes don't rewrite history.
 */
class FinancialLedger
{
    public function __construct(private readonly ProfitabilityService $profitability) {}

    public function recordOrder(Order $record, NormalizedOrder $order): void
    {
        $skus = collect($order->items)->pluck('skuId')->filter()->unique();
        $products = Product::query()
            ->with(['ingredients', 'components', 'standaloneItem'])
            ->whereIn('saipos_sku_id', $skus)
            ->get()
            ->keyBy('saipos_sku_id');
        $inventory = InventoryItem::query()->get()->keyBy('id');

        $record->items()->delete(); // re-recording (e.g. a backfill) replaces the snapshot
        $costs = [];
        $revenue = 0.0;
        $cost = 0.0;
        $complete = true;

        foreach ($order->items as $line) {
            $product = $line->skuId !== null ? $products->get($line->skuId) : null;

            // Add-ons that aren't registered and cost nothing extra (e.g. "medium rare") don't matter here.
            if ($product === null && $line->isChoice && ! $line->unitPrice) {
                continue;
            }

            $unitPrice = $line->unitPrice ?? (float) ($product?->price ?? 0);
            $productCost = $product ? ($costs[$product->id] ??= $this->profitability->calculateProductCost($product, $inventory)) : null;
            $lineComplete = $productCost?->isComplete() ?? false;

            $record->items()->create([
                'product_id' => $product?->id,
                'saipos_sku_id' => $line->skuId,
                'name' => $product?->name ?? ($line->name !== '' ? $line->name : (string) $line->skuId),
                'is_add_on' => $line->isChoice,
                'quantity' => $line->quantity,
                'unit_price' => round($unitPrice, 2),
                'unit_cost' => $productCost ? round($productCost->total, 4) : null,
                'cost_is_complete' => $lineComplete,
            ]);

            $revenue += $unitPrice * $line->quantity;
            $cost += ($productCost?->total ?? 0.0) * $line->quantity;
            $complete = $complete && $lineComplete;
        }

        $record->forceFill([
            'discount' => round($order->discount, 2),
            'revenue' => round(max(0.0, $revenue - $order->discount), 2),
            'cost' => round($cost, 4),
            'cost_is_complete' => $complete,
        ])->save();
    }

    public function recordManualDeduction(ManualDeduction $deduction): void
    {
        $deduction->loadMissing('lines.inventoryItem');

        $total = 0.0;
        $complete = true;

        foreach ($deduction->lines as $line) {
            $item = $line->inventoryItem;
            $unitCost = $item?->hasCost() ? (float) $item->cost_per_unit : null;

            $line->forceFill(['unit_cost' => $unitCost])->save();

            $total += ($unitCost ?? 0.0) * (float) $line->quantity;
            $complete = $complete && $unitCost !== null;
        }

        $deduction->forceFill([
            'cost' => round($total, 4),
            'cost_is_complete' => $complete,
        ])->save();
    }
}
