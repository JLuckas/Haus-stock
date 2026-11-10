<?php

namespace App\Console\Commands;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\Stock\ManualDeductionService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Manual stock removal from the CLI, e.g.:
 *
 *   php artisan stock:deduct "Pão brioche" 2 "Employee Meal"
 *   php artisan stock:deduct 3 150 "Waste - dropped on floor" --user=1
 *   php artisan stock:deduct "Box Cheeseburger" 1 "Employee Meal" --product
 */
class DeductStock extends Command
{
    protected $signature = 'stock:deduct
        {target : Inventory item (or, with --product, product) ID or exact name}
        {quantity : Amount in the item\'s unit (g, ml or un), or number of products}
        {reason : Why the stock is leaving, e.g. "Employee Meal"}
        {--product : Deduct a product: its whole recipe, including products inside it}
        {--user= : ID of the user registering the deduction}';

    protected $description = 'Manually deduct stock from an inventory item or a product (employee meals, waste, etc.)';

    public function handle(ManualDeductionService $service): int
    {
        $identifier = (string) $this->argument('target');
        $quantity = $this->argument('quantity');
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        if (! is_numeric($quantity)) {
            $this->components->error('Quantity must be a number.');

            return self::INVALID;
        }

        $model = $this->option('product') ? Product::class : InventoryItem::class;
        $target = ctype_digit($identifier)
            ? $model::query()->find((int) $identifier)
            : $model::query()->where('name', $identifier)->first();

        if ($target === null) {
            $this->components->error(($this->option('product') ? 'Product' : 'Inventory item')." \"{$identifier}\" not found.");

            return self::FAILURE;
        }

        try {
            $deduction = $target instanceof Product
                ? $service->deductProduct($target, (float) $quantity, (string) $this->argument('reason'), $userId)
                : $service->deduct($target, (float) $quantity, (string) $this->argument('reason'), $userId);
        } catch (InvalidArgumentException|InvalidRecipeException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        $this->components->info(sprintf('Deducted %s "%s" (%s).', $deduction->formattedQuantity(), $deduction->targetName(), $deduction->reason));

        foreach ($deduction->lines as $line) {
            $item = $line->inventoryItem->refresh();
            $this->components->twoColumnDetail(
                $item->name,
                sprintf('−%s → %s%s', $item->formatQuantity($line->quantity), $item->formatQuantity($item->current_stock), $item->isBelowMinimum() ? ' <fg=yellow>(below minimum)</>' : ''),
            );
        }

        return self::SUCCESS;
    }
}
