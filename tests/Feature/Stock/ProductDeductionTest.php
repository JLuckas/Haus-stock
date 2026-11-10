<?php

namespace Tests\Feature\Stock;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\DataTransferObjects\Saipos\NormalizedOrderItem;
use App\Exceptions\Stock\InvalidRecipeException;
use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Product;
use App\Services\Stock\ManualDeductionService;
use App\Services\Stock\StockService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
    }

    public function test_deducting_a_combo_deducts_the_products_inside_it_as_part_of_the_combo(): void
    {
        $box = Product::query()->where('saipos_sku_id', 'HAUS-BOX-CHEESE')->sole();

        $deduction = app(ManualDeductionService::class)->deductProduct($box, 1, 'Employee meal');

        $this->assertStock('Blend bovino', 20000 - 160);
        $this->assertStock('Pão brioche', 120 - 1);
        $this->assertStock('Cheddar', 3000 - 30);
        $this->assertStock('Molho da casa', 4000 - 15.5);
        $this->assertStock('Batata congelada', 10000 - 150);
        $this->assertStock('Coca-Cola Zero 2L', 24 - 1);

        // One deduction, for the box; the burger and fries are not logged as deductions of their own.
        $this->assertSame(1, ManualDeduction::query()->count());
        $this->assertSame($box->id, $deduction->product_id);
        $this->assertNull($deduction->inventory_item_id);
        $this->assertCount(6, $deduction->lines);
        $this->assertSame('1 ×', $deduction->formattedQuantity());
    }

    public function test_a_product_with_a_broken_recipe_deducts_nothing(): void
    {
        $empty = Product::query()->create(['saipos_sku_id' => 'EMPTY', 'name' => 'Empty', 'price' => 0]);
        $combo = Product::query()->create(['saipos_sku_id' => 'COMBO', 'name' => 'Combo', 'price' => 0]);
        $combo->components()->attach($empty->id, ['quantity' => 1]);
        $combo->ingredients()->attach(InventoryItem::query()->where('name', 'Bacon')->value('id'), ['quantity_needed' => 10]);

        try {
            app(ManualDeductionService::class)->deductProduct($combo, 1, 'Waste');
            $this->fail('Expected InvalidRecipeException.');
        } catch (InvalidRecipeException) {
        }

        $this->assertStock('Bacon', 5000);
        $this->assertSame(0, ManualDeduction::query()->count());
    }

    public function test_an_order_with_a_combo_deducts_the_nested_recipes(): void
    {
        app(StockService::class)->processOrder(new NormalizedOrder('C1', 'CONFIRMED', [
            new NormalizedOrderItem('HAUS-BOX-CHEESE', 'Box Cheeseburger', 2),
            new NormalizedOrderItem('HAUS-ADD-BACON', 'Adicional bacon', 2, isChoice: true),
        ]));

        $this->assertStock('Blend bovino', 20000 - 320);
        $this->assertStock('Batata congelada', 10000 - 300);
        $this->assertStock('Coca-Cola Zero 2L', 24 - 2);
        $this->assertStock('Bacon', 5000 - 60);
    }

    public function test_the_cli_can_deduct_a_product(): void
    {
        $this->artisan('stock:deduct', ['target' => 'Box Cheeseburger', 'quantity' => '1', 'reason' => 'Employee meal', '--product' => true])
            ->expectsOutputToContain('Deducted 1 × "Box Cheeseburger" (Employee meal).')
            ->assertSuccessful();

        $this->assertStock('Batata congelada', 10000 - 150);
    }

    private function assertStock(string $name, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, (float) InventoryItem::query()->where('name', $name)->value('current_stock'), 0.0005, $name);
    }
}
