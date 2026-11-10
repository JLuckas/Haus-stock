<?php

namespace App\Services\Stock;

use App\Enums\ProductStockMode;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates sellable products together with their stock mapping.
 *
 * A product is either:
 *  - recipe-based: inventory items (ingredients) and/or other products (components), or
 *  - standalone: exactly 1 unit of one standalone inventory item,
 * never both. Components may themselves contain components; loops are rejected.
 */
class ProductCatalogService
{
    /**
     * @param  array{
     *     saipos_sku_id: string,
     *     name: string,
     *     price: numeric-string|float|int,
     *     stock_mode: string,
     *     inventory_item_id?: int|string|null,
     *     ingredients?: list<array{inventory_item_id: int|string, quantity_needed: numeric-string|float|int}>,
     *     components?: list<array{product_id: int|string, quantity: numeric-string|float|int}>
     * }  $data  Validated input.
     */
    public function save(Product $product, array $data): Product
    {
        $mode = ProductStockMode::from($data['stock_mode']);
        $isRecipe = $mode === ProductStockMode::Recipe;

        return DB::transaction(function () use ($product, $data, $isRecipe): Product {
            $product->fill([
                'saipos_sku_id' => trim($data['saipos_sku_id']),
                'name' => trim($data['name']),
                'price' => $data['price'],
                'target_margin_percent' => $data['target_margin_percent'] ?? null,
                'inventory_item_id' => $isRecipe ? null : (int) $data['inventory_item_id'],
            ])->save();

            $product->ingredients()->sync($isRecipe
                ? collect($data['ingredients'] ?? [])->mapWithKeys(fn (array $line): array => [
                    (int) $line['inventory_item_id'] => ['quantity_needed' => round((float) $line['quantity_needed'], 3)],
                ])->all()
                : []);

            $product->components()->sync($isRecipe
                ? collect($data['components'] ?? [])->mapWithKeys(fn (array $line): array => [
                    (int) $line['product_id'] => ['quantity' => round((float) $line['quantity'], 3)],
                ])->all()
                : []);

            return $product;
        });
    }

    /**
     * IDs of every product that contains this one, directly or through other products.
     * Adding any of them (or the product itself) as a component would create a loop.
     *
     * @return list<int>
     */
    public function ancestorIds(Product $product): array
    {
        $found = [];
        $frontier = [$product->id];

        while ($frontier !== []) {
            $parents = DB::table('product_components')
                ->whereIn('component_product_id', $frontier)
                ->pluck('parent_product_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->reject(fn (int $id): bool => isset($found[$id]) || $id === $product->id)
                ->values()
                ->all();

            foreach ($parents as $id) {
                $found[$id] = true;
            }

            $frontier = $parents;
        }

        return array_keys($found);
    }
}
