<?php

namespace App\Services\Stock;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Per-unit stock consumption of each product, shaped for the live previews in forms
 * ("this will deduct…"). Products whose recipe can't be resolved get an `error` instead.
 */
class ProductBreakdownService
{
    public function __construct(private readonly BillOfMaterials $billOfMaterials) {}

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array{lines?: list<array{name: string, unit: string, qty: float}>, error?: string}>
     */
    public function forProducts(Collection $products): array
    {
        $items = InventoryItem::query()->get()->keyBy('id');
        $products->load(['ingredients', 'components', 'standaloneItem']);

        return $products->mapWithKeys(function (Product $product) use ($items): array {
            try {
                $lines = collect($this->billOfMaterials->perUnit($product))
                    ->map(fn (float $qty, int $id): array => [
                        'name' => $items[$id]->name,
                        'unit' => $items[$id]->unit_of_measure->value,
                        'qty' => $qty,
                    ])
                    ->values()
                    ->all();

                return [$product->id => ['lines' => $lines]];
            } catch (InvalidRecipeException $e) {
                return [$product->id => ['error' => $e->getMessage()]];
            }
        })->all();
    }
}
