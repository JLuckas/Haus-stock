<?php

namespace App\DataTransferObjects\Stock;

use App\Enums\StockProcessingOutcome;

final readonly class StockDeductionResult
{
    /**
     * @param  array<int, float>  $deductions  inventory_item_id => quantity deducted
     */
    public function __construct(
        public StockProcessingOutcome $outcome,
        public string $saiposOrderId,
        public array $deductions = [],
    ) {}
}
