<?php

namespace App\DataTransferObjects\Finance;

/** Production cost of ONE unit of a product, with every nested level flattened. */
final readonly class ProductCost
{
    /**
     * @param  float  $total  Sum of the lines that have a cost (unrounded).
     * @param  list<array{inventory_item_id: int, name: string, unit: string, quantity: float, cost_per_unit: ?float, cost: ?float}>  $lines
     * @param  list<string>  $missingCostItems  Names of ingredients with no cost_per_unit.
     */
    public function __construct(
        public float $total,
        public array $lines,
        public array $missingCostItems,
    ) {}

    public function isComplete(): bool
    {
        return $this->missingCostItems === [];
    }
}
