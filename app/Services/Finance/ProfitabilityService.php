<?php

namespace App\Services\Finance;

use App\DataTransferObjects\Finance\ProductCost;
use App\DataTransferObjects\Finance\ProfitabilityMetrics;
use App\Enums\ProfitabilityStatus;
use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Stock\BillOfMaterials;
use Illuminate\Support\Collection;

/**
 * Production cost, profit and margin per product, from its Bill of Materials.
 *
 * Cost uses the same recipe resolution as stock deduction (BillOfMaterials), so combos,
 * products inside products and standalone items are priced exactly as they are deducted.
 */
class ProfitabilityService
{
    public function __construct(private readonly BillOfMaterials $billOfMaterials) {}

    /** The store's target margin: set in the app (Products → Costs), else PROFITABILITY_HEALTHY_MARGIN. */
    public function healthyMarginPercent(): float
    {
        return (float) Setting::get(Setting::TARGET_MARGIN, config('profitability.healthy_margin_percent'));
    }

    /** A product's own target margin, or the store's. */
    public function targetMarginFor(Product $product): float
    {
        return $product->target_margin_percent !== null ? (float) $product->target_margin_percent : $this->healthyMarginPercent();
    }

    /**
     * Price that yields the target margin: cost ÷ (1 − margin). E.g. cost 10, margin 65 % → 28,57.
     * Null when there is no cost or the margin is 100 % or more (no price can reach it).
     */
    public function recommendedPrice(?float $cost, float $marginPercent): ?float
    {
        if ($cost === null || $cost <= 0 || $marginPercent >= 100) {
            return null;
        }

        return round($cost / (1 - $marginPercent / 100), 2);
    }

    /**
     * Cost of ONE unit: Σ quantity_needed × cost_per_unit over every ingredient, all levels flattened.
     * Ingredients without a cost are listed in missingCostItems and left out of the total.
     *
     * @param  Collection<int, InventoryItem>|null  $items  Pre-loaded items keyed by id (avoids a query per product in reports).
     *
     * @throws InvalidRecipeException when the product (or one nested in it) has no valid recipe
     */
    public function calculateProductCost(Product $product, ?Collection $items = null): ProductCost
    {
        $plan = $this->billOfMaterials->perUnit($product);
        $items ??= InventoryItem::query()->whereKey(array_keys($plan))->get()->keyBy('id');

        $total = 0.0;
        $lines = [];
        $missing = [];

        foreach ($plan as $itemId => $quantity) {
            /** @var InventoryItem|null $item */
            $item = $items->get($itemId);
            $costPerUnit = $item?->hasCost() ? (float) $item->cost_per_unit : null;
            $lineCost = $costPerUnit === null ? null : $quantity * $costPerUnit;

            if ($lineCost === null) {
                $missing[] = $item?->name ?? "Item #{$itemId}";
            } else {
                $total += $lineCost;
            }

            $lines[] = [
                'inventory_item_id' => $itemId,
                'name' => $item?->name ?? "Item #{$itemId}",
                'unit' => $item?->unit_of_measure->value ?? '',
                'quantity' => $quantity,
                'cost_per_unit' => $costPerUnit,
                'cost' => $lineCost === null ? null : round($lineCost, 4),
            ];
        }

        return new ProductCost($total, $lines, $missing);
    }

    /**
     * Profit (price − cost), margin % and food cost %, plus a status:
     * loss > unmapped > incomplete_cost > below_threshold > no_price > healthy (most urgent wins;
     * every condition that applies is also listed in flags).
     *
     * @param  Collection<int, InventoryItem>|null  $items
     */
    public function getProfitabilityMetrics(Product $product, ?Collection $items = null): ProfitabilityMetrics
    {
        $price = round((float) $product->price, 2);
        $target = $this->targetMarginFor($product);

        try {
            $cost = $this->calculateProductCost($product, $items);
        } catch (InvalidRecipeException $e) {
            return new ProfitabilityMetrics(
                $product->id, $product->name, $product->saipos_sku_id, $price,
                cost: null, profit: null, marginPercent: null, foodCostPercent: null,
                status: ProfitabilityStatus::Unmapped, flags: [ProfitabilityStatus::Unmapped],
                error: $e->getMessage(),
                targetMarginPercent: $target,
            );
        }

        $profit = $price - $cost->total;
        $marginPercent = $price > 0 ? $profit / $price * 100 : null;

        $flags = [];

        if ($price <= 0) {
            $flags[] = ProfitabilityStatus::NoPrice;
        } elseif ($profit < 0) {
            // Even with missing costs this is certain: adding them only makes it worse.
            $flags[] = ProfitabilityStatus::Loss;
        } elseif ($marginPercent < $target) {
            $flags[] = ProfitabilityStatus::BelowThreshold;
        }

        if (! $cost->isComplete()) {
            $flags[] = ProfitabilityStatus::IncompleteCost;
        }

        $status = $flags === []
            ? ProfitabilityStatus::Healthy
            : collect($flags)->sortBy(fn (ProfitabilityStatus $s): int => $s->severity())->first();

        return new ProfitabilityMetrics(
            $product->id, $product->name, $product->saipos_sku_id, $price,
            cost: round($cost->total, 2),
            profit: round($profit, 2),
            marginPercent: $marginPercent === null ? null : round($marginPercent, 2),
            foodCostPercent: $price > 0 ? round($cost->total / $price * 100, 2) : null,
            status: $status,
            flags: $flags,
            missingCostItems: $cost->missingCostItems,
            costBreakdown: $cost->lines,
            targetMarginPercent: $target,
            recommendedPrice: $this->recommendedPrice($cost->total, $target),
        );
    }

    /**
     * Metrics for every product, worst first (by status urgency, then lowest margin).
     *
     * @return Collection<int, ProfitabilityMetrics>
     */
    public function allProductMetrics(): Collection
    {
        $items = InventoryItem::query()->get()->keyBy('id');

        return Product::query()
            ->with(['ingredients', 'components', 'standaloneItem'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): ProfitabilityMetrics => $this->getProfitabilityMetrics($product, $items))
            ->sortBy([
                fn (ProfitabilityMetrics $a, ProfitabilityMetrics $b): int => $a->status->severity() <=> $b->status->severity(),
                fn (ProfitabilityMetrics $a, ProfitabilityMetrics $b): int => ($a->marginPercent ?? INF) <=> ($b->marginPercent ?? INF),
            ])
            ->values();
    }

    /**
     * Financial health of the whole menu, ready for JSON.
     * Products have no "inactive" state yet, so every product is evaluated.
     */
    public function generateSystemProfitabilityReport(): array
    {
        $metrics = $this->allProductMetrics();

        return [
            'generated_at' => now()->toIso8601String(),
            'currency' => config('profitability.currency'),
            'healthy_margin_percent' => $this->healthyMarginPercent(),
            'summary' => $this->summarize($metrics),
            'products' => $metrics->map(fn (ProfitabilityMetrics $m): array => $m->toArray())->all(),
        ];
    }

    /** @param  Collection<int, ProfitabilityMetrics>  $metrics */
    public function summarize(Collection $metrics): array
    {
        // Only margins computed from complete costs and a real price are averaged.
        $reliable = $metrics->filter(fn (ProfitabilityMetrics $m): bool => $m->marginPercent !== null
            && $m->missingCostItems === []
            && $m->error === null);

        return [
            'products' => $metrics->count(),
            'flagged' => $metrics->filter(fn (ProfitabilityMetrics $m): bool => $m->isFlagged())->count(),
            'by_status' => collect(ProfitabilityStatus::cases())
                ->mapWithKeys(fn (ProfitabilityStatus $s): array => [
                    $s->value => $metrics->filter(fn (ProfitabilityMetrics $m): bool => $m->status === $s)->count(),
                ])
                ->all(),
            'average_margin_percent' => $reliable->isEmpty() ? null : round($reliable->avg('marginPercent'), 2),
            'lowest_margin' => ($worst = $reliable->sortBy('marginPercent')->first())
                ? ['id' => $worst->productId, 'name' => $worst->name, 'margin_percent' => $worst->marginPercent]
                : null,
        ];
    }
}
