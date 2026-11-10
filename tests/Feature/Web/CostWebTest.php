<?php

namespace Tests\Feature\Web;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_cost_is_typed_per_kg_and_stored_per_gram(): void
    {
        $this->post(route('inventory.store'), [
            'name' => 'Picanha', 'type' => 'ingredient', 'unit_of_measure' => 'g',
            'current_stock' => '0', 'minimum_stock' => '0', 'cost' => '89.90',
        ])->assertSessionHasNoErrors();

        $item = InventoryItem::query()->where('name', 'Picanha')->sole();
        $this->assertSame('0.089900', $item->cost_per_unit);
        $this->assertSame('R$ 89,90/kg', $item->formattedCost());

        $this->get(route('inventory.edit', $item))->assertSee('89.9', escape: false); // initial value of the decimal field
        $this->get(route('inventory.index'))->assertSee('R$ 89,90/kg');
    }

    public function test_cost_per_unit_items_are_not_converted_and_cost_can_be_cleared(): void
    {
        $bun = InventoryItem::query()->where('name', 'Pão brioche')->sole();

        $this->put(route('inventory.update', $bun), [
            'name' => $bun->name, 'type' => 'ingredient', 'unit_of_measure' => 'un', 'minimum_stock' => '30', 'cost' => '2.10',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2.100000', $bun->fresh()->cost_per_unit);

        $this->put(route('inventory.update', $bun), [
            'name' => $bun->name, 'type' => 'ingredient', 'unit_of_measure' => 'un', 'minimum_stock' => '30', 'cost' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($bun->fresh()->cost_per_unit);

        $this->get(route('inventory.index'))->assertSee('Set cost');
    }

    public function test_the_profitability_page_shows_cost_profit_and_margin(): void
    {
        $this->get(route('reports.profitability'))
            ->assertOk()
            ->assertSee('Box Cheeseburger')
            ->assertSee('R$ 24,33')     // cost
            ->assertSee('R$ 30,57')     // profit
            ->assertSee('55,7 %')       // margin
            ->assertSee('Low margin');

        $this->get(route('reports.profitability', ['status' => 'healthy']))
            ->assertOk()
            ->assertSee('Batata frita')
            ->assertDontSee('Box Cheeseburger');
    }

    public function test_the_product_page_shows_its_cost_breakdown_and_margin(): void
    {
        $cheese = Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->sole();

        $this->get(route('products.edit', $cheese))
            ->assertOk()
            ->assertSee('160 g Blend bovino')
            ->assertSee('R$ 7,68')      // 160 g × R$ 48/kg
            ->assertSee('R$ 11,53')     // total cost
            ->assertSee('64,0 %');      // (32 − 11,532) ÷ 32
    }
}
