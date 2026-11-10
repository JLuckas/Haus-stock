<?php

namespace App\Services\Stock;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\DataTransferObjects\Stock\StockDeductionResult;
use App\Enums\StockProcessingOutcome;
use App\Exceptions\Stock\InvalidRecipeException;
use App\Exceptions\Stock\UnmappedProductException;
use App\Models\InventoryItem;
use App\Services\Finance\FinancialLedger;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Turns normalized Saipos orders into stock deductions using each product's Bill of Materials.
 *
 * Guarantees:
 *  - All-or-nothing: an order deducts every ingredient or none (single DB transaction).
 *  - At-most-once: the order row is locked and `is_stock_processed` is checked inside the transaction.
 *  - No lost updates: deductions are atomic SQL decrements, not read-modify-write in PHP.
 *
 * Stock may go negative on purpose: the sale already happened at the POS, so refusing the
 * deduction would only hide the discrepancy. Negative stock signals a missing purchase entry.
 */
class StockService
{
    public function __construct(
        private readonly BillOfMaterials $billOfMaterials,
        private readonly FinancialLedger $ledger,
    ) {}

    public function processOrder(NormalizedOrder $order): StockDeductionResult
    {
        $this->registerOrder($order);

        if ($order->isCancelled()) {
            return $this->handleCancelledOrder($order);
        }

        try {
            // attempts: 3 only retries deadlocks / serialization failures, never business errors.
            $result = DB::transaction(fn () => $this->deductInsideTransaction($order), attempts: 3);
        } catch (Throwable $e) {
            // The transaction rolled back every deduction; record why so the order is not silently stuck.
            Order::query()
                ->where('saipos_order_id', $order->saiposOrderId)
                ->update(['last_error' => Str::limit($e->getMessage(), 1000), 'updated_at' => now()]);

            throw $e;
        }

        if ($result->outcome === StockProcessingOutcome::Processed) {
            $this->reportLowStock(array_keys($result->deductions));
        }

        return $result;
    }

    /**
     * Aggregate the order into one total per inventory item.
     *
     * @return array<int, float> inventory_item_id => quantity, sorted by id
     *
     * @throws UnmappedProductException when a main item has no local product
     * @throws InvalidRecipeException when a product's stock mapping is misconfigured
     */
    public function buildDeductionPlan(NormalizedOrder $order): array
    {
        $skus = collect($order->items)->pluck('skuId')->filter()->unique()->values();

        /** @var Collection<string, Product> $products */
        $products = Product::query()
            ->with(['ingredients', 'components', 'standaloneItem'])
            ->whereIn('saipos_sku_id', $skus)
            ->get()
            ->keyBy('saipos_sku_id');

        $lines = [];

        foreach ($order->items as $item) {
            $product = $item->skuId !== null ? $products->get($item->skuId) : null;

            if ($product === null) {
                // Add-ons that are not registered locally (e.g. "extra sauce" not tracked) are ignored;
                // an unknown main item would silently skew stock, so it fails the whole order instead.
                if ($item->isChoice) {
                    Log::debug('Ignoring unmapped Saipos choice item.', ['order' => $order->saiposOrderId, 'sku' => $item->skuId]);

                    continue;
                }

                throw UnmappedProductException::forItem($item, $order->saiposOrderId);
            }

            $lines[] = [$product, $item->quantity];
        }

        // Expands nested products (combos) and returns totals sorted by item id (fixed lock order).
        return $this->billOfMaterials->explode($lines);
    }

    /**
     * Atomically subtract a quantity from one inventory item. Must be called inside a transaction
     * when it is part of a larger operation.
     */
    public function deductFromItem(int $inventoryItemId, float $quantity): void
    {
        $amount = $this->formatQuantity($quantity);

        if ((float) $amount <= 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
        }

        // Compiles to: UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?
        $affected = InventoryItem::query()->whereKey($inventoryItemId)->decrement('current_stock', $amount);

        if ($affected === 0) {
            throw (new ModelNotFoundException)->setModel(InventoryItem::class, [$inventoryItemId]);
        }
    }

    /** Atomically add stock to one item (goods received from a supplier). */
    public function addToItem(int $inventoryItemId, float $quantity): void
    {
        $amount = $this->formatQuantity($quantity);

        if ((float) $amount <= 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
        }

        // UPDATE ... SET current_stock = current_stock + ?: safe while the poller deducts concurrently.
        $affected = InventoryItem::query()->whereKey($inventoryItemId)->increment('current_stock', $amount);

        if ($affected === 0) {
            throw (new ModelNotFoundException)->setModel(InventoryItem::class, [$inventoryItemId]);
        }
    }

    /**
     * Log a warning for each given item now under its minimum stock.
     *
     * @param  list<int>  $inventoryItemIds
     * @return Collection<int, InventoryItem>
     */
    public function reportLowStock(array $inventoryItemIds): Collection
    {
        if ($inventoryItemIds === []) {
            return collect();
        }

        $lowItems = InventoryItem::query()->whereKey($inventoryItemIds)->belowMinimum()->get();

        foreach ($lowItems as $item) {
            Log::warning('Inventory item below minimum stock.', [
                'inventory_item_id' => $item->id,
                'name' => $item->name,
                'current_stock' => $item->current_stock,
                'minimum_stock' => $item->minimum_stock,
                'unit' => $item->unit_of_measure->value,
            ]);
        }

        return $lowItems;
    }

    private function deductInsideTransaction(NormalizedOrder $order): StockDeductionResult
    {
        // Row lock: a second worker handling the same order waits here, then sees it as processed.
        $record = Order::query()
            ->where('saipos_order_id', $order->saiposOrderId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($record->is_stock_processed) {
            return new StockDeductionResult(StockProcessingOutcome::AlreadyProcessed, $order->saiposOrderId);
        }

        $plan = $this->buildDeductionPlan($order);

        foreach ($plan as $inventoryItemId => $quantity) {
            $this->deductFromItem($inventoryItemId, $quantity);
        }

        $record->forceFill([
            'status' => $order->status,
            'is_stock_processed' => true,
            'stock_processed_at' => now(),
            'last_error' => null,
            'raw_payload' => $order->rawPayload,
        ])->save();

        // Revenue and cost snapshot, in the same transaction as the stock movement.
        $this->ledger->recordOrder($record, $order);

        return new StockDeductionResult(StockProcessingOutcome::Processed, $order->saiposOrderId, $plan);
    }

    /** Idempotent insert: ON CONFLICT DO NOTHING on saipos_order_id, safe under concurrency. */
    private function registerOrder(NormalizedOrder $order): void
    {
        $now = now();

        Order::query()->insertOrIgnore([
            'saipos_order_id' => $order->saiposOrderId,
            'status' => $order->status,
            'is_stock_processed' => false,
            'raw_payload' => json_encode($order->rawPayload),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function handleCancelledOrder(NormalizedOrder $order): StockDeductionResult
    {
        $record = Order::query()->where('saipos_order_id', $order->saiposOrderId)->firstOrFail();

        if ($record->status !== $order->status && $record->is_stock_processed) {
            // Food may already have been prepared, so stock is not returned automatically.
            Log::warning('Saipos order cancelled after stock was deducted; review and adjust manually.', [
                'saipos_order_id' => $order->saiposOrderId,
            ]);
        }

        $record->update(['status' => $order->status]);

        return new StockDeductionResult(StockProcessingOutcome::SkippedCancelled, $order->saiposOrderId);
    }

    private function formatQuantity(float $quantity): string
    {
        // Fixed-point string avoids scientific notation (1.0E-5) reaching the SQL expression.
        return number_format($quantity, 3, '.', '');
    }
}
