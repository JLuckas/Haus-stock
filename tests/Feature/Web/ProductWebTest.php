<?php

namespace Tests\Feature\Web;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_a_recipe_product_can_be_created_and_empty_rows_are_ignored(): void
    {
        $this->post(route('products.store'), [
            'saipos_sku_id' => 'HAUS-DOUBLE',
            'name' => 'Double Bacon',
            'price' => '42.90',
            'stock_mode' => 'recipe',
            'ingredients' => [
                ['inventory_item_id' => $this->id('Blend bovino'), 'quantity_needed' => '320'],
                ['inventory_item_id' => $this->id('Bacon'), 'quantity_needed' => '62.5'],
                ['inventory_item_id' => '', 'quantity_needed' => ''],
            ],
        ])->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

        $product = Product::query()->where('saipos_sku_id', 'HAUS-DOUBLE')->sole();
        $this->assertNull($product->inventory_item_id);
        $this->assertEquals(
            [$this->id('Blend bovino') => 320.0, $this->id('Bacon') => 62.5],
            $product->ingredients->mapWithKeys(fn ($i) => [$i->id => (float) $i->pivot->quantity_needed])->all(),
        );
    }

    public function test_switching_a_product_to_standalone_clears_its_recipe(): void
    {
        $product = Product::query()->where('saipos_sku_id', 'HAUS-BOX-1')->sole();

        $this->put(route('products.update', $product), [
            'saipos_sku_id' => 'HAUS-BOX-1',
            'name' => 'Box 1',
            'price' => '49.90',
            'stock_mode' => 'standalone',
            'inventory_item_id' => $this->id('Coca-Cola Zero 2L'),
            'ingredients' => [['inventory_item_id' => $this->id('Bacon'), 'quantity_needed' => '10']],
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame($this->id('Coca-Cola Zero 2L'), $product->inventory_item_id);
        $this->assertSame(0, $product->ingredients()->count());
    }

    public function test_standalone_mode_requires_a_standalone_item(): void
    {
        $this->post(route('products.store'), [
            'saipos_sku_id' => 'X', 'name' => 'X', 'price' => '1',
            'stock_mode' => 'standalone',
            'inventory_item_id' => $this->id('Bacon'),
        ])->assertSessionHasErrors('inventory_item_id');
    }

    public function test_recipe_mode_rejects_duplicate_and_missing_ingredients(): void
    {
        $this->post(route('products.store'), [
            'saipos_sku_id' => 'X', 'name' => 'X', 'price' => '1', 'stock_mode' => 'recipe',
            'ingredients' => [
                ['inventory_item_id' => $this->id('Bacon'), 'quantity_needed' => '10'],
                ['inventory_item_id' => $this->id('Bacon'), 'quantity_needed' => '0'],
            ],
        ])->assertSessionHasErrors(['ingredients.1.inventory_item_id', 'ingredients.1.quantity_needed']);

        $this->post(route('products.store'), [
            'saipos_sku_id' => 'X', 'name' => 'X', 'price' => '1', 'stock_mode' => 'recipe', 'ingredients' => [],
        ])->assertSessionHasErrors('ingredients');
    }

    public function test_the_saipos_sku_must_be_unique(): void
    {
        $this->post(route('products.store'), [
            'saipos_sku_id' => 'HAUS-BOX-1', 'name' => 'Copy', 'price' => '1',
            'stock_mode' => 'standalone', 'inventory_item_id' => $this->id('Coca-Cola Zero 2L'),
        ])->assertSessionHasErrors('saipos_sku_id');
    }

    public function test_the_create_form_can_be_prefilled_from_an_order(): void
    {
        $this->get(route('products.create', ['sku' => 'NEW-SKU', 'name' => 'Smash Burger']))
            ->assertOk()
            ->assertSee('value="NEW-SKU"', escape: false)
            ->assertSee('value="Smash Burger"', escape: false);
    }

    private function id(string $name): int
    {
        return InventoryItem::query()->where('name', $name)->value('id');
    }
}
