<?php

namespace App\Services\Stock;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\Product;

/**
 * Resolves products into the inventory they consume, through any number of nesting levels.
 *
 *   Box Cheeseburger  = 1 × Cheeseburger + 1 × Fries + 1 × Coke Zero 2L
 *   Cheeseburger      = 160 g meat + 1 bun + 30 g cheddar
 *   Fries             = 150 g potato
 *   Coke Zero 2L      = standalone → 1 un
 *
 *   explode([[Box, 2]]) → meat 320 g, bun 2 un, cheddar 60 g, potato 300 g, coke 2 un
 *
 * Components are expanded only as part of their parent: nothing is recorded as if the
 * component had been deducted on its own.
 */
class BillOfMaterials
{
    public const MAX_DEPTH = 10;

    /** Per-unit consumption by product id, valid for one public call only (recipes may change between calls). */
    private array $memo = [];

    /**
     * Total inventory consumed by several product lines, aggregated per item.
     *
     * @param  iterable<array{0: Product, 1: float|int}>  $lines  [product, quantity] pairs
     * @return array<int, float> inventory_item_id => quantity, sorted by id, rounded to 3 decimals
     *
     * @throws InvalidRecipeException
     */
    public function explode(iterable $lines): array
    {
        $this->memo = [];
        $totals = [];

        foreach ($lines as [$product, $quantity]) {
            foreach ($this->resolve($product, []) as $itemId => $perUnit) {
                $totals[$itemId] = ($totals[$itemId] ?? 0.0) + $perUnit * $quantity;
            }
        }

        // Fixed order = fixed lock order when the totals are deducted (avoids deadlocks).
        ksort($totals);

        // Drop amounts that round to zero (e.g. a tiny fraction of a sauce) instead of failing on them.
        return array_filter(array_map(fn (float $q): float => round($q, 3), $totals), fn (float $q): bool => $q > 0);
    }

    /**
     * Inventory consumed by ONE unit of the product, all levels flattened.
     *
     * @return array<int, float>
     *
     * @throws InvalidRecipeException
     */
    public function perUnit(Product $product): array
    {
        return $this->explode([[$product, 1]]);
    }

    /**
     * @param  list<Product>  $path  Products currently being expanded, outermost first.
     * @return array<int, float>
     */
    private function resolve(Product $product, array $path): array
    {
        if (isset($this->memo[$product->id])) {
            return $this->memo[$product->id];
        }

        $names = array_map(fn (Product $p): string => $p->name, $path);

        foreach ($path as $ancestor) {
            if ($ancestor->id === $product->id) {
                throw InvalidRecipeException::circular([...$names, $product->name]);
            }
        }

        if (count($path) >= self::MAX_DEPTH) {
            throw InvalidRecipeException::tooDeep([...$names, $product->name], self::MAX_DEPTH);
        }

        $product->loadMissing(['ingredients', 'components', 'standaloneItem']);

        $hasRecipe = $product->ingredients->isNotEmpty() || $product->components->isNotEmpty();

        if ($product->inventory_item_id !== null) {
            if ($hasRecipe) {
                throw InvalidRecipeException::ambiguousStockMapping($product, $names);
            }

            if (! $product->standaloneItem?->isStandalone()) {
                throw InvalidRecipeException::standaloneLinkMustBeStandalone($product, $names);
            }

            // Standalone products (e.g. 2L Coke Zero) always consume exactly 1 unit.
            return $this->memo[$product->id] = [$product->standaloneItem->id => 1.0];
        }

        if (! $hasRecipe) {
            throw InvalidRecipeException::noStockMapping($product, $names);
        }

        $totals = [];

        foreach ($product->ingredients as $ingredient) {
            /** @var InventoryItem $ingredient */
            $totals[$ingredient->id] = ($totals[$ingredient->id] ?? 0.0) + (float) $ingredient->pivot->quantity_needed;
        }

        foreach ($product->components as $component) {
            $perComponent = (float) $component->pivot->quantity;

            foreach ($this->resolve($component, [...$path, $product]) as $itemId => $quantity) {
                $totals[$itemId] = ($totals[$itemId] ?? 0.0) + $quantity * $perComponent;
            }
        }

        return $this->memo[$product->id] = $totals;
    }
}
