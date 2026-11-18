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
     * @param  iterable<array{0: Product, 1: float|int, 2?: list<array<string, mixed>>}>  $lines
     *     [product, quantity, customizations] — customizations are an order line's changes
     *     (see OrderCustomizer: swap/remove/add an ingredient or a product inside a box).
     * @return array<int, float> inventory_item_id => quantity, sorted by id, rounded to 3 decimals
     *
     * @throws InvalidRecipeException
     */
    public function explode(iterable $lines): array
    {
        $this->memo = [];
        $totals = [];

        foreach ($lines as $line) {
            [$product, $quantity] = $line;
            $customizations = $line[2] ?? [];
            $perUnitPlan = $customizations ? $this->customized($product, $customizations) : $this->resolve($product, []);

            foreach ($perUnitPlan as $itemId => $perUnit) {
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
     * Inventory consumed by ONE unit of the product with an order line's changes applied, e.g.
     * Box + [swap Coke → Guaraná, add 1 × Cheeseburger, swap Cheddar → Mussarela].
     *
     *  1. Products inside it: swap / remove / add (the box's own structure).
     *  2. The result is flattened, then ingredients are swapped / removed / added on the whole of it,
     *     so swapping the cheese also reaches the burgers inside a box.
     *
     * Changes that don't apply (e.g. swapping something the product doesn't have) are skipped;
     * orders typed in the app are validated beforehand by OrderCustomizer.
     *
     * @param  list<array<string, mixed>>  $customizations
     * @return array<int, float>
     */
    private function customized(Product $product, array $customizations): array
    {
        $product->loadMissing(['ingredients', 'components', 'standaloneItem']);
        $totals = [];
        $components = [];

        if ($product->inventory_item_id !== null) {
            $totals = $this->resolve($product, []);
        } else {
            foreach ($product->ingredients as $ingredient) {
                $totals[$ingredient->id] = ($totals[$ingredient->id] ?? 0.0) + (float) $ingredient->pivot->quantity_needed;
            }
            foreach ($product->components as $component) {
                $components[$component->id] = ($components[$component->id] ?? 0.0) + (float) $component->pivot->quantity;
            }
        }

        foreach ($customizations as $change) {
            match ($change['type'] ?? null) {
                'swap_product' => $this->move($components, (int) $change['from'], (int) $change['to']),
                'remove_product' => $components = array_diff_key($components, [(int) $change['product'] => true]),
                'add_product' => $components[(int) $change['product']] = ($components[(int) $change['product']] ?? 0.0) + (float) ($change['quantity'] ?? 1),
                default => null,
            };
        }

        $models = Product::query()->with(['ingredients', 'components', 'standaloneItem'])->whereKey(array_keys($components))->get()->keyBy('id');

        foreach ($components as $componentId => $perParent) {
            $component = $models->get($componentId) ?? throw InvalidRecipeException::noStockMapping($product, [$product->name]);

            foreach ($this->resolve($component, [$product]) as $itemId => $quantity) {
                $totals[$itemId] = ($totals[$itemId] ?? 0.0) + $quantity * $perParent;
            }
        }

        foreach ($customizations as $change) {
            match ($change['type'] ?? null) {
                'swap_ingredient' => $this->move($totals, (int) $change['from'], (int) $change['to']),
                'remove_ingredient' => $totals = array_diff_key($totals, [(int) $change['item'] => true]),
                'add_ingredient' => $totals[(int) $change['item']] = ($totals[(int) $change['item']] ?? 0.0) + (float) ($change['quantity'] ?? 0),
                default => null,
            };
        }

        return $totals;
    }

    /** Moves the amount of $from to $to (no-op when $from isn't there). */
    private function move(array &$amounts, int $from, int $to): void
    {
        if ($from === $to || ! isset($amounts[$from])) {
            return;
        }

        $amounts[$to] = ($amounts[$to] ?? 0.0) + $amounts[$from];
        unset($amounts[$from]);
    }

    /**
     * Per-unit consumption with customizations (for cost snapshots and previews).
     *
     * @param  list<array<string, mixed>>  $customizations
     * @return array<int, float>
     *
     * @throws InvalidRecipeException
     */
    public function perUnitCustomized(Product $product, array $customizations): array
    {
        return $this->explode([[$product, 1, $customizations]]);
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
