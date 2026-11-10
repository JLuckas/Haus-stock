<?php

namespace App\Services\Stock;

use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Product;
use App\Services\Finance\FinancialLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock removed outside of Saipos sales: employee meals, waste, spoilage, tastings.
 *
 * Two targets:
 *  - an inventory item: removes exactly that amount;
 *  - a product: removes everything its recipe uses, including products nested inside it
 *    (deducting 1 × Box also deducts the burger inside it, as part of the box).
 *
 * Every deduction leaves an audit row in `manual_deductions` plus one `manual_deduction_lines`
 * row per inventory item that actually left stock.
 */
class ManualDeductionService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly BillOfMaterials $billOfMaterials,
        private readonly FinancialLedger $ledger,
    ) {}

    /**
     * @param  float  $quantity  Amount in the item's own unit (g, ml or un).
     *
     * @throws InvalidArgumentException for a non-positive quantity or an empty reason
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the item does not exist
     */
    public function deduct(InventoryItem|int $item, float $quantity, string $reason, ?int $userId = null): ManualDeduction
    {
        [$quantity, $reason] = $this->validate($quantity, $reason);
        $itemId = $item instanceof InventoryItem ? $item->getKey() : $item;

        return $this->record(
            fn (): array => [
                ['inventory_item_id' => InventoryItem::query()->findOrFail($itemId)->id],
                [$itemId => $quantity],
            ],
            $quantity,
            $reason,
            $userId,
        );
    }

    /**
     * @param  float  $quantity  Number of product units (e.g. 1 Box).
     *
     * @throws InvalidArgumentException for a non-positive quantity or an empty reason
     * @throws \App\Exceptions\Stock\InvalidRecipeException when the product (or one nested in it) has no valid recipe
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the product does not exist
     */
    public function deductProduct(Product|int $product, float $quantity, string $reason, ?int $userId = null): ManualDeduction
    {
        [$quantity, $reason] = $this->validate($quantity, $reason);
        $productId = $product instanceof Product ? $product->getKey() : $product;

        return $this->record(
            function () use ($productId, $quantity): array {
                $product = Product::query()->findOrFail($productId);

                return [['product_id' => $product->id], $this->billOfMaterials->explode([[$product, $quantity]])];
            },
            $quantity,
            $reason,
            $userId,
        );
    }

    /**
     * @param  callable(): array{0: array<string, int>, 1: array<int, float>}  $resolve  Returns [target columns, plan]; runs inside the transaction.
     */
    private function record(callable $resolve, float $quantity, string $reason, ?int $userId): ManualDeduction
    {
        $deduction = DB::transaction(function () use ($resolve, $quantity, $reason, $userId): ManualDeduction {
            [$target, $plan] = $resolve();

            $deduction = ManualDeduction::query()->create($target + [
                'quantity_deducted' => $quantity,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

            // $plan is sorted by item id, so concurrent deductions lock rows in the same order.
            foreach ($plan as $inventoryItemId => $amount) {
                $deduction->lines()->create(['inventory_item_id' => $inventoryItemId, 'quantity' => $amount]);
                $this->stockService->deductFromItem($inventoryItemId, $amount);
            }

            // What this cost the store (a loss in the P&L), snapshotted with the movement.
            $this->ledger->recordManualDeduction($deduction);

            return $deduction;
        });

        $this->stockService->reportLowStock($deduction->lines()->pluck('inventory_item_id')->all());

        return $deduction->load(['inventoryItem', 'product', 'lines.inventoryItem']);
    }

    /** @return array{0: float, 1: string} */
    private function validate(float $quantity, string $reason): array
    {
        $quantity = round($quantity, 3);
        $reason = trim($reason);

        if ($quantity <= 0) {
            throw new InvalidArgumentException(__('Quantity must be greater than zero.'));
        }

        if ($reason === '') {
            throw new InvalidArgumentException(__('A reason is required for manual deductions.'));
        }

        return [$quantity, $reason];
    }
}
