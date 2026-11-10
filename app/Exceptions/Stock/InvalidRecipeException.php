<?php

namespace App\Exceptions\Stock;

use App\Models\Product;

/** Messages are shown in the UI, so they are translated (keys in lang/pt_BR.json). */
class InvalidRecipeException extends StockProcessingException
{
    /**
     * @param  list<string>  $via  Names of the products that led here, outermost first (e.g. ["Box 1"]).
     */
    public static function noStockMapping(Product $product, array $via = []): self
    {
        return new self(self::prefix($via).__('Product ":name" (:sku) has no recipe, no products inside it and no standalone item.', self::product($product)));
    }

    public static function ambiguousStockMapping(Product $product, array $via = []): self
    {
        return new self(self::prefix($via).__('Product ":name" (:sku) has both a recipe and a standalone item; keep only one.', self::product($product)));
    }

    public static function standaloneLinkMustBeStandalone(Product $product, array $via = []): self
    {
        return new self(self::prefix($via).__('Product ":name" (:sku) links to an inventory item that is not a standalone product.', self::product($product)));
    }

    /** @param  list<string>  $path  Product names forming the loop, e.g. ["Box", "Burger", "Box"]. */
    public static function circular(array $path): self
    {
        return new self(__('Products contain each other in a loop: :path.', ['path' => implode(' → ', $path)]));
    }

    /** @param  list<string>  $path */
    public static function tooDeep(array $path, int $maxDepth): self
    {
        return new self(__('Products are nested more than :max levels deep: :path.', ['max' => $maxDepth, 'path' => implode(' → ', $path)]));
    }

    private static function prefix(array $via): string
    {
        return $via === [] ? '' : __('Inside :path:', ['path' => implode(' → ', $via)]).' ';
    }

    private static function product(Product $product): array
    {
        return ['name' => $product->name, 'sku' => $product->saipos_sku_id];
    }
}
