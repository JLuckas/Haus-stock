<?php

namespace Tests\Feature\Stock;

use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\Stock\BillOfMaterials;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillOfMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private BillOfMaterials $bom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->bom = app(BillOfMaterials::class);
    }

    public function test_a_combo_expands_the_recipes_of_the_products_inside_it(): void
    {
        $plan = $this->bom->explode([[$this->product('HAUS-BOX-CHEESE'), 2]]);

        $this->assertSame([
            'Blend bovino' => 320.0,      // 2 × Cheeseburger × 160 g
            'Pão brioche' => 2.0,         // 2 × Cheeseburger × 1
            'Cheddar' => 60.0,            // 2 × Cheeseburger × 30 g
            'Molho da casa' => 31.0,      // 2 × Cheeseburger × 15.5 ml
            'Coca-Cola Zero 2L' => 2.0,   // 2 × standalone Coke
            'Batata congelada' => 300.0,  // 2 × Fries × 150 g
        ], $this->byName($plan));
    }

    public function test_direct_ingredients_and_products_inside_are_added_together(): void
    {
        $box = $this->combo('BOX-BACON', ['HAUS-CHEESE' => 2], ['Bacon' => 40, 'Blend bovino' => 10]);

        $this->assertSame([
            'Blend bovino' => 330.0,  // 2 × 160 g from the burgers + 10 g directly
            'Pão brioche' => 2.0,
            'Bacon' => 40.0,
            'Cheddar' => 60.0,
            'Molho da casa' => 31.0,
        ], $this->byName($this->bom->perUnit($box)));
    }

    public function test_products_can_be_nested_several_levels_deep(): void
    {
        $party = $this->combo('PARTY', ['HAUS-BOX-CHEESE' => 3, 'HAUS-ADD-BACON' => 2]);

        $plan = $this->byName($this->bom->perUnit($party));

        $this->assertSame(480.0, $plan['Blend bovino']);
        $this->assertSame(450.0, $plan['Batata congelada']);
        $this->assertSame(3.0, $plan['Coca-Cola Zero 2L']);
        $this->assertSame(60.0, $plan['Bacon']);
    }

    public function test_the_same_product_reached_twice_is_counted_twice(): void
    {
        // Cheeseburger is inside the box AND listed directly: both count.
        $combo = $this->combo('DOUBLE', ['HAUS-BOX-CHEESE' => 1, 'HAUS-CHEESE' => 1]);

        $this->assertSame(320.0, $this->byName($this->bom->perUnit($combo))['Blend bovino']);
    }

    public function test_a_loop_between_products_is_rejected(): void
    {
        $a = $this->combo('LOOP-A', ['HAUS-CHEESE' => 1]);
        $b = $this->combo('LOOP-B', ['LOOP-A' => 1]);
        $a->components()->attach($b->id, ['quantity' => 1]); // bypasses form validation on purpose

        $this->expectException(InvalidRecipeException::class);
        $this->expectExceptionMessage('LOOP-A → LOOP-B → LOOP-A');

        $this->bom->perUnit($a->fresh());
    }

    public function test_an_unmapped_product_inside_a_combo_names_the_combo(): void
    {
        Product::query()->create(['saipos_sku_id' => 'EMPTY', 'name' => 'EMPTY', 'price' => 0]);
        $combo = $this->combo('HAS-EMPTY', ['EMPTY' => 1]);

        $this->expectException(InvalidRecipeException::class);
        $this->expectExceptionMessage('Inside HAS-EMPTY: Product "EMPTY"');

        $this->bom->perUnit($combo);
    }

    public function test_recipe_changes_are_picked_up_between_calls(): void
    {
        $box = $this->product('HAUS-BOX-CHEESE');
        $this->assertSame(320.0 / 2, $this->byName($this->bom->perUnit($box))['Blend bovino']);

        $this->product('HAUS-CHEESE')->ingredients()->updateExistingPivot($this->itemId('Blend bovino'), ['quantity_needed' => 200]);

        $this->assertSame(200.0, $this->byName($this->bom->perUnit($box->fresh()))['Blend bovino']);
    }

    /**
     * @param  array<string, float>  $components  SKU => quantity
     * @param  array<string, float>  $ingredients  item name => quantity
     */
    private function combo(string $sku, array $components, array $ingredients = []): Product
    {
        $product = Product::query()->create(['saipos_sku_id' => $sku, 'name' => $sku, 'price' => 0]);

        foreach ($components as $componentSku => $quantity) {
            $product->components()->attach($this->product($componentSku)->id, ['quantity' => $quantity]);
        }

        foreach ($ingredients as $name => $quantity) {
            $product->ingredients()->attach($this->itemId($name), ['quantity_needed' => $quantity]);
        }

        return $product->fresh();
    }

    private function product(string $sku): Product
    {
        return Product::query()->where('saipos_sku_id', $sku)->sole();
    }

    private function itemId(string $name): int
    {
        return InventoryItem::query()->where('name', $name)->value('id');
    }

    /** @param  array<int, float>  $plan */
    private function byName(array $plan): array
    {
        $names = InventoryItem::query()->pluck('name', 'id');

        return collect($plan)->mapWithKeys(fn (float $qty, int $id): array => [$names[$id] => $qty])->all();
    }
}
