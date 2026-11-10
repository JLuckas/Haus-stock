<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\ProfitabilityService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCostWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_recommended_price_is_cost_divided_by_one_minus_margin(): void
    {
        $service = app(ProfitabilityService::class);

        $this->assertSame(28.57, $service->recommendedPrice(10.0, 65.0));
        $this->assertSame(20.0, $service->recommendedPrice(10.0, 50.0));
        $this->assertNull($service->recommendedPrice(10.0, 100.0));
        $this->assertNull($service->recommendedPrice(0.0, 65.0));
    }

    public function test_the_store_target_margin_is_editable_and_drives_the_recommendation(): void
    {
        $cheese = Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->sole(); // cost 11,532, price 32

        $metrics = app(ProfitabilityService::class)->getProfitabilityMetrics($cheese);
        $this->assertSame(65.0, $metrics->targetMarginPercent);   // config default
        $this->assertSame(32.95, $metrics->recommendedPrice);      // 11,532 ÷ 0,35

        $this->put(route('products.target-margin'), ['target_margin_percent' => '60'])->assertSessionHasNoErrors();
        $this->assertEquals(60, Setting::get(Setting::TARGET_MARGIN));

        $metrics = app(ProfitabilityService::class)->getProfitabilityMetrics($cheese);
        $this->assertSame(60.0, $metrics->targetMarginPercent);
        $this->assertSame(28.83, $metrics->recommendedPrice);      // 11,532 ÷ 0,40

        $this->put(route('products.target-margin'), ['target_margin_percent' => '100'])->assertSessionHasErrors('target_margin_percent');
    }

    public function test_a_product_can_have_its_own_target_margin(): void
    {
        $cheese = Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->with('ingredients')->sole();
        $payload = [
            'saipos_sku_id' => 'HAUS-CHEESE', 'name' => 'Cheeseburger', 'price' => '32', 'stock_mode' => 'recipe',
            'target_margin_percent' => '50',
            'ingredients' => $cheese->ingredients->map(fn ($i) => ['inventory_item_id' => $i->id, 'quantity_needed' => $i->pivot->quantity_needed])->all(),
        ];

        $this->put(route('products.update', $cheese), $payload)->assertSessionHasNoErrors();
        $cheese->refresh();
        $this->assertSame('50.00', $cheese->target_margin_percent);

        $metrics = app(ProfitabilityService::class)->getProfitabilityMetrics($cheese);
        $this->assertSame(50.0, $metrics->targetMarginPercent);
        $this->assertSame(23.06, $metrics->recommendedPrice);

        // Empty = back to the store's target.
        $this->put(route('products.update', $cheese), ['target_margin_percent' => ''] + $payload)->assertSessionHasNoErrors();
        $this->assertNull($cheese->refresh()->target_margin_percent);

        $this->put(route('products.update', $cheese), ['target_margin_percent' => '120'] + $payload)->assertSessionHasErrors('target_margin_percent');
    }

    public function test_the_costs_tab_lists_each_product_cost_and_recommended_price(): void
    {
        $this->get(route('products.costs'))
            ->assertOk()
            ->assertSee('Catalog')
            ->assertSee('Cheeseburger')
            ->assertSee('R$ 11,53')     // cost: sum of every ingredient
            ->assertSee('R$ 32,95')     // recommended for 65 %
            ->assertSee('160 g Blend bovino');

        $this->get(route('products.costs', ['show' => 'below_target']))->assertOk()->assertSee('Cheeseburger');
        $this->get(route('products.costs', ['search' => 'nothing-like-this']))->assertOk()->assertSee('No products match.');
        $this->get(route('products.index'))->assertOk()->assertSee(route('products.costs'));
    }

    public function test_the_product_form_gets_the_costs_for_the_live_panel(): void
    {
        $cheese = Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->sole();

        $this->get(route('products.edit', $cheese))
            ->assertOk()
            ->assertSee('productForm(', escape: false)
            ->assertSee('itemCosts', escape: false)
            ->assertSee('Cost and price')
            ->assertSee('R$ 32,95');    // server-rendered recommended price before Alpine runs

        $this->get(route('products.create'))->assertOk()->assertSee('Recommended price');
    }
}
