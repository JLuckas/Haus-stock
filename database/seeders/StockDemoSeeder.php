<?php

namespace Database\Seeders;

use App\Enums\InventoryItemType;
use App\Enums\UnitOfMeasure;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo catalogue. The saipos_sku_id values are placeholders: replace them with the
 * integration codes registered for each item in Saipos.
 */
class StockDemoSeeder extends Seeder
{
    public function run(): void
    {
        // $cost is per kg / per L / per un, as suppliers quote it; stored per g / ml / un.
        $item = fn (string $name, InventoryItemType $type, UnitOfMeasure $unit, float $stock, float $minimum, float $cost): InventoryItem => InventoryItem::query()->updateOrCreate(
            ['name' => $name],
            [
                'type' => $type,
                'unit_of_measure' => $unit,
                'current_stock' => $stock,
                'minimum_stock' => $minimum,
                'cost_per_unit' => $cost / $unit->costDisplayFactor(),
            ],
        );

        $meat = $item('Blend bovino', InventoryItemType::Ingredient, UnitOfMeasure::Grams, 20000, 5000, 48.00);
        $bun = $item('Pão brioche', InventoryItemType::Ingredient, UnitOfMeasure::Units, 120, 30, 1.80);
        $bacon = $item('Bacon', InventoryItemType::Ingredient, UnitOfMeasure::Grams, 5000, 1000, 62.00);
        $cheddar = $item('Cheddar', InventoryItemType::Ingredient, UnitOfMeasure::Grams, 3000, 800, 56.00);
        $sauce = $item('Molho da casa', InventoryItemType::Ingredient, UnitOfMeasure::Milliliters, 4000, 1000, 24.00);
        $cokeZero = $item('Coca-Cola Zero 2L', InventoryItemType::StandaloneProduct, UnitOfMeasure::Units, 24, 6, 9.50);
        $potato = $item('Batata congelada', InventoryItemType::Ingredient, UnitOfMeasure::Grams, 10000, 2500, 22.00);

        // BoM product: the combo from the spec.
        $box1 = Product::query()->updateOrCreate(['saipos_sku_id' => 'HAUS-BOX-1'], ['name' => 'Box 1', 'price' => 49.90]);
        $box1->ingredients()->sync([
            $meat->id => ['quantity_needed' => 160],
            $bun->id => ['quantity_needed' => 2],
            $bacon->id => ['quantity_needed' => 50],
        ]);

        $cheeseburger = Product::query()->updateOrCreate(['saipos_sku_id' => 'HAUS-CHEESE'], ['name' => 'Cheeseburger', 'price' => 32.00]);
        $cheeseburger->ingredients()->sync([
            $meat->id => ['quantity_needed' => 160],
            $bun->id => ['quantity_needed' => 1],
            $cheddar->id => ['quantity_needed' => 30],
            $sauce->id => ['quantity_needed' => 15.5],
        ]);

        // Add-on sold as a Saipos choice item.
        $extraBacon = Product::query()->updateOrCreate(['saipos_sku_id' => 'HAUS-ADD-BACON'], ['name' => 'Adicional bacon', 'price' => 6.00]);
        $extraBacon->ingredients()->sync([$bacon->id => ['quantity_needed' => 30]]);

        // Standalone product: deducts exactly 1 unit, no recipe.
        $coke = Product::query()->updateOrCreate(
            ['saipos_sku_id' => 'HAUS-COKE-ZERO-2L'],
            ['name' => 'Coca-Cola Zero 2L', 'price' => 14.00, 'inventory_item_id' => $cokeZero->id],
        );

        $fries = Product::query()->updateOrCreate(['saipos_sku_id' => 'HAUS-FRIES'], ['name' => 'Batata frita', 'price' => 18.00]);
        $fries->ingredients()->sync([$potato->id => ['quantity_needed' => 150]]);

        // Combo made of other products: selling it deducts each product's own recipe.
        $boxCheese = Product::query()->updateOrCreate(['saipos_sku_id' => 'HAUS-BOX-CHEESE'], ['name' => 'Box Cheeseburger', 'price' => 54.90]);
        $boxCheese->components()->sync([
            $cheeseburger->id => ['quantity' => 1],
            $fries->id => ['quantity' => 1],
            $coke->id => ['quantity' => 1],
        ]);
    }
}
