<?php

namespace App\Services\Stock;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Support\Quantity;

/**
 * Changes to one order line: what the customer asked to swap, remove or add.
 *
 *   swap_ingredient   {from, to}               Cheddar → Mussarela (same ingredient category)
 *   remove_ingredient {item}                   no onion
 *   add_ingredient    {item, quantity}         + 30 g bacon (per unit of the product, in g / ml / un)
 *   swap_product      {from, to}               in a box: Coca → Guaraná (same product category)
 *   remove_product    {product}                in a box: no fries
 *   add_product       {product, quantity}      in a box: + 1 Cheeseburger
 *
 * Stock and cost follow the changed recipe (BillOfMaterials::explode). Orders keep the changes
 * with the names at the time (normalize()), so they still read right if items are renamed.
 */
class OrderCustomizer
{
    public const TYPES = ['swap_ingredient', 'remove_ingredient', 'add_ingredient', 'swap_product', 'remove_product', 'add_product'];

    public function __construct(private readonly BillOfMaterials $billOfMaterials) {}

    /**
     * Why these changes can't be applied to the product (empty = fine).
     *
     * @param  list<array<string, mixed>>  $changes
     * @return list<string>
     */
    public function errors(Product $product, array $changes): array
    {
        if ($changes === []) {
            return [];
        }

        try {
            // Ingredient changes apply to the recipe after the box changes (a swapped-in Guaraná can itself be swapped).
            $productChanges = array_values(array_filter($changes, fn ($c): bool => str_ends_with((string) ($c['type'] ?? ''), '_product')));
            $plan = $productChanges
                ? $this->billOfMaterials->perUnitCustomized($product, $productChanges)
                : $this->billOfMaterials->perUnit($product);
        } catch (InvalidRecipeException $e) {
            return [$e->getMessage()];
        }

        $product->loadMissing('components');
        $components = $product->components->keyBy('id');
        $items = InventoryItem::query()->whereKey([...$this->ids($changes, ['from', 'to', 'item'], ingredient: true), ...array_keys($plan)])->get()->keyBy('id');
        $products = Product::query()->whereKey($this->ids($changes, ['from', 'to', 'product'], ingredient: false))->get()->keyBy('id');
        $errors = [];

        foreach ($changes as $change) {
            $type = $change['type'] ?? null;
            $from = (int) ($change['from'] ?? 0);
            $to = (int) ($change['to'] ?? 0);

            $error = match ($type) {
                'swap_ingredient' => match (true) {
                    ! isset($plan[$from]) => __(':product doesn\'t use that ingredient.', ['product' => $product->name]),
                    ! $items->has($to) || $from === $to => __('Choose another ingredient to swap to.'),
                    ! $this->sameKind($items->get($from), $items->get($to)) => __('Swap :from only for an ingredient of the same category.', ['from' => $items->get($from)?->name]),
                    default => null,
                },
                'remove_ingredient' => isset($plan[(int) ($change['item'] ?? 0)]) ? null : __(':product doesn\'t use that ingredient.', ['product' => $product->name]),
                'add_ingredient' => ! $items->has((int) ($change['item'] ?? 0)) ? __('Choose the ingredient to add.')
                    : (! is_numeric($change['quantity'] ?? null) || (float) $change['quantity'] <= 0 ? __('Type how much to add.') : null),
                'swap_product' => match (true) {
                    ! $components->has($from) => __(':product doesn\'t contain that product.', ['product' => $product->name]),
                    ! $products->has($to) || $from === $to || $to === $product->id => __('Choose another product to swap to.'),
                    ! $this->sameCategory($components->get($from), $products->get($to)) => __('Swap :from only for a product of the same category.', ['from' => $components->get($from)->name]),
                    default => null,
                },
                'remove_product' => $components->has((int) ($change['product'] ?? 0)) ? null : __(':product doesn\'t contain that product.', ['product' => $product->name]),
                'add_product' => ! $products->has((int) ($change['product'] ?? 0)) || (int) $change['product'] === $product->id ? __('Choose the product to add.')
                    : (! is_numeric($change['quantity'] ?? null) || (float) $change['quantity'] <= 0 ? __('Type how many to add.') : null),
                default => __('Unknown change.'),
            };

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if ($errors === []) {
            try {
                $this->billOfMaterials->perUnitCustomized($product, $changes);
            } catch (InvalidRecipeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Keeps only the fields each change needs and adds the names (and unit) at the time.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    public function normalize(array $changes): array
    {
        $changes = array_values(array_filter($changes, fn ($c): bool => is_array($c) && in_array($c['type'] ?? null, self::TYPES, true)));
        $items = InventoryItem::query()->withTrashed()->whereKey($this->ids($changes, ['from', 'to', 'item'], ingredient: true))->get()->keyBy('id');
        $products = Product::query()->whereKey($this->ids($changes, ['from', 'to', 'product'], ingredient: false))->get()->keyBy('id');

        return array_map(function (array $change) use ($items, $products): array {
            $type = $change['type'];
            $item = fn (string $key) => $items->get((int) ($change[$key] ?? 0));
            $product = fn (string $key) => $products->get((int) ($change[$key] ?? 0));

            return match ($type) {
                'swap_ingredient' => ['type' => $type, 'from' => (int) $change['from'], 'to' => (int) $change['to'],
                    'from_name' => $item('from')?->name, 'to_name' => $item('to')?->name],
                'remove_ingredient' => ['type' => $type, 'item' => (int) $change['item'], 'item_name' => $item('item')?->name],
                'add_ingredient' => ['type' => $type, 'item' => (int) $change['item'], 'quantity' => round((float) $change['quantity'], 3),
                    'item_name' => $item('item')?->name, 'unit' => $item('item')?->unit_of_measure->value],
                'swap_product' => ['type' => $type, 'from' => (int) $change['from'], 'to' => (int) $change['to'],
                    'from_name' => $product('from')?->name, 'to_name' => $product('to')?->name],
                'remove_product' => ['type' => $type, 'product' => (int) $change['product'], 'product_name' => $product('product')?->name],
                'add_product' => ['type' => $type, 'product' => (int) $change['product'], 'quantity' => round((float) $change['quantity'], 3),
                    'product_name' => $product('product')?->name],
            };
        }, $changes);
    }

    /** "Cheddar → Mussarela", "Sem Cebola", "+ 30 g Bacon", "Coca → Guaraná", "Sem Batata", "+ 1 × Cheeseburger". */
    public static function describe(array $change): string
    {
        return match ($change['type'] ?? null) {
            'swap_ingredient', 'swap_product' => ($change['from_name'] ?? '?').' → '.($change['to_name'] ?? '?'),
            'remove_ingredient' => __('No :name', ['name' => $change['item_name'] ?? '?']),
            'remove_product' => __('No :name', ['name' => $change['product_name'] ?? '?']),
            'add_ingredient' => '+ '.Quantity::format((float) ($change['quantity'] ?? 0), $change['unit'] ?? null).' '.($change['item_name'] ?? '?'),
            'add_product' => '+ '.Quantity::format((float) ($change['quantity'] ?? 1)).' × '.($change['product_name'] ?? '?'),
            default => '',
        };
    }

    /** Ingredient swaps stay within a category (or the same unit, for uncategorized items). */
    private function sameKind(?InventoryItem $from, ?InventoryItem $to): bool
    {
        if (! $from || ! $to) {
            return false;
        }

        return $from->category_id !== null
            ? $from->category_id === $to->category_id
            : $from->unit_of_measure === $to->unit_of_measure;
    }

    private function sameCategory(Product $from, Product $to): bool
    {
        return $from->category_id === null || $from->category_id === $to->category_id;
    }

    /** @return list<int> */
    private function ids(array $changes, array $keys, bool $ingredient): array
    {
        $ids = [];
        foreach ($changes as $change) {
            if (! is_array($change) || str_ends_with((string) ($change['type'] ?? ''), $ingredient ? '_product' : '_ingredient')) {
                continue;
            }
            foreach ($keys as $key) {
                if (isset($change[$key]) && is_numeric($change[$key])) {
                    $ids[] = (int) $change[$key];
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
