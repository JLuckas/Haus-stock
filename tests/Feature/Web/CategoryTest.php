<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_default_categories_exist_for_ingredients_and_products(): void
    {
        $this->assertContains('Queijo', Category::query()->forIngredients()->pluck('name')->all());
        $this->assertContains('Molho', Category::query()->forIngredients()->pluck('name')->all());
        $this->assertContains('Box', Category::query()->forProducts()->pluck('name')->all());
        $this->assertContains('Bebida', Category::query()->forProducts()->pluck('name')->all());

        $this->get(route('products.categories'))->assertOk()->assertSee('Ingredient categories')->assertSee('Queijo')->assertSee('Hambúrguer');
    }

    public function test_categories_can_be_created_renamed_and_deleted(): void
    {
        $this->post(route('products.categories.store'), ['scope' => 'ingredient', 'name' => 'Picles e conservas'])->assertSessionHas('success');
        $category = Category::query()->where('name', 'Picles e conservas')->sole();
        $this->assertSame('ingredient', $category->scope);

        // Same name in the same scope is refused; the other scope is fine.
        $this->post(route('products.categories.store'), ['scope' => 'ingredient', 'name' => 'picles e conservas'])->assertSessionHasErrors('slug');
        $this->post(route('products.categories.store'), ['scope' => 'product', 'name' => 'Picles e conservas'])->assertSessionHasNoErrors();

        $this->put(route('products.categories.update', $category), ['name' => 'Conservas', 'color' => 'teal'])->assertSessionHasNoErrors();
        $this->assertSame(['Conservas', 'teal'], [$category->fresh()->name, $category->fresh()->color]);

        $item = InventoryItem::query()->where('name', 'Bacon')->sole();
        $item->update(['category_id' => $category->id]);
        $this->delete(route('products.categories.destroy', $category))->assertSessionHas('success');
        $this->assertNull($item->fresh()->category_id); // the item stays, uncategorized
    }

    public function test_items_and_products_get_a_category_from_their_forms_and_can_be_filtered(): void
    {
        $cheese = Category::query()->forIngredients()->where('slug', 'queijo')->sole();
        $box = Category::query()->forProducts()->where('slug', 'box')->sole();
        $cheddar = InventoryItem::query()->where('name', 'Cheddar')->sole();

        $this->put(route('inventory.update', $cheddar), [
            'name' => 'Cheddar', 'type' => 'ingredient', 'unit_of_measure' => 'g', 'minimum_stock' => '800', 'category_id' => $cheese->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($cheese->id, $cheddar->fresh()->category_id);

        // A product category can't be put on an ingredient.
        $this->put(route('inventory.update', $cheddar), [
            'name' => 'Cheddar', 'type' => 'ingredient', 'unit_of_measure' => 'g', 'minimum_stock' => '800', 'category_id' => $box->id,
        ])->assertSessionHasErrors('category_id');

        $this->get(route('inventory.index', ['category' => $cheese->id]))->assertOk()->assertSee('Cheddar')->assertDontSee('Blend bovino');

        $boxProduct = Product::query()->where('saipos_sku_id', 'HAUS-BOX-1')->with('ingredients')->sole();
        $this->put(route('products.update', $boxProduct), [
            'saipos_sku_id' => 'HAUS-BOX-1', 'name' => 'Box 1', 'price' => '49.90', 'stock_mode' => 'recipe', 'category_id' => $box->id,
            'ingredients' => $boxProduct->ingredients->map(fn ($i) => ['inventory_item_id' => $i->id, 'quantity_needed' => $i->pivot->quantity_needed])->all(),
        ])->assertSessionHasNoErrors();
        $this->assertSame($box->id, $boxProduct->fresh()->category_id);

        $this->get(route('products.index', ['category' => $box->id]))->assertOk()->assertSee('Box 1')->assertDontSee('Batata frita');
    }
}
