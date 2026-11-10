<?php

namespace App\Services\Stock;

use App\DataTransferObjects\Stock\StockDeductionResult;
use App\Models\Order;
use App\Services\Saipos\SaiposIntegrationService;

/**
 * Re-runs stock deduction for an order from its stored Saipos payload, without calling Saipos.
 * Typical use: a product SKU was missing, it has now been registered, and the order sits
 * outside the poller's lookback window.
 */
class OrderRetryService
{
    public function __construct(
        private readonly SaiposIntegrationService $saipos,
        private readonly StockService $stockService,
    ) {}

    public function retry(Order $order): StockDeductionResult
    {
        $normalized = $this->saipos->normalizeOrder($order->raw_payload ?? [], $order->saipos_order_id);

        // StockService locks the row and re-checks is_stock_processed, so this is safe to call twice.
        return $this->stockService->processOrder($normalized);
    }
}
