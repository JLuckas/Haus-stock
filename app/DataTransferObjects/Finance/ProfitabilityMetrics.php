<?php

namespace App\DataTransferObjects\Finance;

use App\Enums\ProfitabilityStatus;

/**
 * Cost vs. selling price for one product.
 *
 *   profit        = price − cost
 *   margin %      = profit ÷ price × 100       (e.g. cost 10, price 15 → 33,33 %)
 *   food cost %   = cost ÷ price × 100         ("CMV"; margin + food cost = 100 %)
 */
final readonly class ProfitabilityMetrics
{
    /**
     * @param  ?float  $cost  Null when the recipe can't be resolved.
     * @param  list<ProfitabilityStatus>  $flags  Every condition that applies; $status is the most urgent one.
     * @param  list<string>  $missingCostItems
     * @param  list<array<string, mixed>>  $costBreakdown
     */
    public function __construct(
        public int $productId,
        public string $name,
        public string $saiposSkuId,
        public float $price,
        public ?float $cost,
        public ?float $profit,
        public ?float $marginPercent,
        public ?float $foodCostPercent,
        public ProfitabilityStatus $status,
        public array $flags,
        public array $missingCostItems = [],
        public array $costBreakdown = [],
        public ?string $error = null,
        public ?float $targetMarginPercent = null,
        public ?float $recommendedPrice = null,
    ) {}

    public function isFlagged(): bool
    {
        return $this->status->isFlagged();
    }

    /** JSON-friendly shape used by the API. */
    public function toArray(): array
    {
        return [
            'id' => $this->productId,
            'name' => $this->name,
            'saipos_sku_id' => $this->saiposSkuId,
            'price' => $this->price,
            'cost' => $this->cost,
            'profit' => $this->profit,
            'margin_percent' => $this->marginPercent,
            'food_cost_percent' => $this->foodCostPercent,
            'status' => $this->status->value,
            'flags' => array_map(fn (ProfitabilityStatus $flag): string => $flag->value, $this->flags),
            'is_flagged' => $this->isFlagged(),
            'cost_is_complete' => $this->missingCostItems === [] && $this->error === null,
            'missing_cost_items' => $this->missingCostItems,
            'cost_breakdown' => $this->costBreakdown,
            'error' => $this->error,
            'target_margin_percent' => $this->targetMarginPercent,
            // Price for the target margin: cost ÷ (1 − margin).
            'recommended_price' => $this->recommendedPrice,
        ];
    }
}
