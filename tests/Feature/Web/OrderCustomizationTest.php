<?php

namespace Tests\Feature\Web;

use App\Enums\InventoryItemType;
use App\Enums\UnitOfMeasure;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Orders with changes: swap an ingredient for another of the same category, remove or add
 * ingredients, and in a box swap / add products. Stock and cost follow the changed recipe.
 */
class OrderCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $mussarela;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());

        $cheese = $this->category(Category::INGREDIENT, 'queijo');
        $bread = $this->category(Category::INGREDIENT, 'pao');
        $this->item('Cheddar')->update(['category_id' => $cheese->id]);
        $this->item('Pão brioche')->update(['category_id' => $bread->id]);
        $this->mussarela = InventoryItem::query()->create([
            'name' => 'Mussarela', 'type' => InventoryItemType::Ingredient, 'unit_of_measure' => UnitOfMeasure::Grams,
            'current_stock' => 2000, 'minimum_stock' => 0, 'cost_per_unit' => 0.05, 'category_id' => $cheese->id,
        ]);

        $beverage = $this->category(Category::PRODUCT, 'bebida');
        $burger = $this->category(Category::PRODUCT, 'hamburguer');
        $this->product('HAUS-COKE-ZERO-2L')->update(['category_id' => $beverage->id]);
        $this->product('HAUS-CHEESE')->update(['category_id' => $burger->id]);

        $guaranaItem = InventoryItem::query()->create([
            'name' => 'Guaraná 2L', 'type' => InventoryItemType::StandaloneProduct, 'unit_of_measure' => UnitOfMeasure::Units,
            'current_stock' => 10, 'minimum_stock' => 0, 'cost_per_unit' => 7,
        ]);
        Product::query()->create(['saipos_sku_id' => 'HAUS-GUARANA', 'name' => 'Guaraná 2L', 'price' => 12, 'inventory_item_id' => $guaranaItem->id, 'category_id' => $beverage->id]);
    }

    private function category(string $scope, string $slug): Category
    {
        return Category::query()->where('scope', $scope)->where('slug', $slug)->sole();
    }

    private function item(string $name): InventoryItem
    {
        return InventoryItem::query()->where('name', $name)->sole();
    }

    private function product(string $sku): Product
    {
        return Product::query()->where('saipos_sku_id', $sku)->sole();
    }

    private function stock(string $name): float
    {
        return (float) $this->item($name)->current_stock;
    }

    private function order(array $line): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('orders.store'), ['items' => [$line + ['quantity' => '1']]]);
    }

    public function test_an_ingredient_is_swapped_for_another_of_the_same_category(): void
    {
        $cheddar = $this->stock('Cheddar');
        $mussarela = $this->stock('Mussarela');

        $this->order([
            'product_id' => $this->product('HAUS-CHEESE')->id, 'quantity' => '2',
            'customizations' => [['type' => 'swap_ingredient', 'from' => $this->item('Cheddar')->id, 'to' => $this->mussarela->id]],
        ])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertTrue($order->is_stock_processed);
        $this->assertSame($cheddar, $this->stock('Cheddar'));
        $this->assertSame($mussarela - 60, $this->stock('Mussarela'));

        // The order keeps the change, readable, and its cost uses mussarela (30 g × R$ 0,05 = R$ 1,50 instead of cheddar's R$ 1,68).
        $line = $order->items()->sole();
        $this->assertSame('Cheddar', $line->customizations[0]['from_name']);
        $this->assertSame('Mussarela', $line->customizations[0]['to_name']);
        $this->assertEqualsWithDelta(11.532 - 1.68 + 1.5, (float) $line->unit_cost, 0.001);

        $this->get(route('orders.show', $order))->assertOk()->assertSee('Cheddar → Mussarela');
    }

    public function test_ingredients_are_removed_and_extras_added(): void
    {
        $bun = $this->stock('Pão brioche');
        $bacon = $this->stock('Bacon');

        $this->order([
            'product_id' => $this->product('HAUS-CHEESE')->id,
            'unit_price' => '38',
            'customizations' => [
                ['type' => 'remove_ingredient', 'item' => $this->item('Pão brioche')->id],
                ['type' => 'add_ingredient', 'item' => $this->item('Bacon')->id, 'quantity' => '40'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame($bun, $this->stock('Pão brioche'));
        $this->assertSame($bacon - 40, $this->stock('Bacon'));
        $this->assertSame('38.00', Order::query()->sole()->items()->sole()->unit_price); // extras charged

        $this->get(route('orders.show', Order::query()->sole()))->assertSee('No Pão brioche')->assertSee('+ 40 g Bacon');
    }

    public function test_a_box_can_swap_its_drink_and_take_another_burger(): void
    {
        $coke = $this->stock('Coca-Cola Zero 2L');
        $guarana = $this->stock('Guaraná 2L');
        $meat = $this->stock('Blend bovino');

        $this->order([
            'product_id' => $this->product('HAUS-BOX-CHEESE')->id,
            'customizations' => [
                ['type' => 'swap_product', 'from' => $this->product('HAUS-COKE-ZERO-2L')->id, 'to' => $this->product('HAUS-GUARANA')->id],
                ['type' => 'add_product', 'product' => $this->product('HAUS-CHEESE')->id, 'quantity' => '1'],
                // Applies to every burger in the box, including the added one.
                ['type' => 'swap_ingredient', 'from' => $this->item('Cheddar')->id, 'to' => $this->mussarela->id],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame($coke, $this->stock('Coca-Cola Zero 2L'));
        $this->assertSame($guarana - 1, $this->stock('Guaraná 2L'));
        $this->assertSame($meat - 320, $this->stock('Blend bovino'));
        $this->assertSame(2000.0 - 60, $this->stock('Mussarela'));

        $this->get(route('orders.show', Order::query()->sole()))
            ->assertSee('Coca-Cola Zero 2L → Guaraná 2L')
            ->assertSee('+ 1 × Cheeseburger');
    }

    public function test_swaps_must_stay_within_the_same_category_and_apply_to_the_product(): void
    {
        $cheese = $this->product('HAUS-CHEESE');

        // Cheddar → bread: different category.
        $this->order(['product_id' => $cheese->id, 'customizations' => [['type' => 'swap_ingredient', 'from' => $this->item('Cheddar')->id, 'to' => $this->item('Pão brioche')->id]]])
            ->assertSessionHasErrors('items.0.customizations');
        // Bacon isn't in a cheeseburger.
        $this->order(['product_id' => $cheese->id, 'customizations' => [['type' => 'remove_ingredient', 'item' => $this->item('Bacon')->id]]])
            ->assertSessionHasErrors('items.0.customizations');
        // In the box, the drink can't become a burger.
        $this->order(['product_id' => $this->product('HAUS-BOX-CHEESE')->id, 'customizations' => [['type' => 'swap_product', 'from' => $this->product('HAUS-COKE-ZERO-2L')->id, 'to' => $cheese->id]]])
            ->assertSessionHasErrors('items.0.customizations');
        // A product can't be added inside itself.
        $this->order(['product_id' => $cheese->id, 'customizations' => [['type' => 'add_product', 'product' => $cheese->id, 'quantity' => '1']]])
            ->assertSessionHasErrors('items.0.customizations');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_the_same_product_can_be_ordered_twice_with_different_changes(): void
    {
        $cheddar = $this->stock('Cheddar');

        $this->post(route('orders.store'), ['items' => [
            ['product_id' => $this->product('HAUS-CHEESE')->id, 'quantity' => '1'],
            ['product_id' => $this->product('HAUS-CHEESE')->id, 'quantity' => '1', 'customizations' => [['type' => 'remove_ingredient', 'item' => $this->item('Cheddar')->id]]],
        ]])->assertSessionHasNoErrors();

        $this->assertSame($cheddar - 30, $this->stock('Cheddar'));
        $this->assertSame(2, Order::query()->sole()->items()->count());
    }

    public function test_a_failed_order_keeps_its_changes_when_retried(): void
    {
        // Out of a product mapping: the order fails, then works after the fix, still with the swap.
        $burger = $this->product('HAUS-CHEESE');
        $this->order([
            'product_id' => $burger->id,
            'customizations' => [['type' => 'swap_ingredient', 'from' => $this->item('Cheddar')->id, 'to' => $this->mussarela->id]],
        ])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $payloadLine = $order->raw_payload['items'][0];
        $this->assertSame('swap_ingredient', $payloadLine['customizations'][0]['type']);
        $this->assertSame('Mussarela', $payloadLine['customizations'][0]['to_name']);
    }

    public function test_the_new_order_page_offers_the_changes(): void
    {
        $this->get(route('orders.create'))
            ->assertOk()
            ->assertSee('orderForm(', false)
            ->assertSee('Customize')
            ->assertSee('Mussarela');
    }
}
