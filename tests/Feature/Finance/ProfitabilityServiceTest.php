<?php

namespace Tests\Feature\Finance;

use App\Enums\ProfitabilityStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\Finance\ProfitabilityService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfitabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProfitabilityService::class);
    }

    /** The example from the spec: 2 buns $2 + patty $5 + lettuce $1 + tomato $1 + 40 g mayo $1 = $10. */
    private function specBurger(float $price): Product
    {
        $item = fn (string $name, string $unit, float $costPerUnit) => InventoryItem::query()->create([
            'name' => $name, 'type' => 'ingredient', 'unit_of_measure' => $unit,
            'current_stock' => 0, 'minimum_stock' => 0, 'cost_per_unit' => $costPerUnit,
        ]);

        $burger = Product::query()->create(['saipos_sku_id' => 'SPEC', 'name' => 'Spec burger', 'price' => $price]);
        $burger->ingredients()->attach([
            $item('Bun', 'un', 1.00)->id => ['quantity_needed' => 2],             // $2
            $item('Patty', 'g', 5 / 160)->id => ['quantity_needed' => 160],       // $5
            $item('Lettuce', 'un', 1.00)->id => ['quantity_needed' => 1],         // $1
            $item('Tomato', 'un', 1.00)->id => ['quantity_needed' => 1],          // $1
            $item('Mayo', 'g', 1 / 40)->id => ['quantity_needed' => 40],          // $1
        ]);

        return $burger;
    }

    public function test_the_cost_is_the_sum_of_quantity_times_cost_per_unit(): void
    {
        $cost = $this->service->calculateProductCost($this->specBurger(15));

        $this->assertEqualsWithDelta(10.0, $cost->total, 0.0001);
        $this->assertTrue($cost->isComplete());
        $this->assertCount(5, $cost->lines);
    }

    public function test_a_burger_sold_above_cost_shows_its_profit_and_margin(): void
    {
        config(['profitability.healthy_margin_percent' => 30]);

        $metrics = $this->service->getProfitabilityMetrics($this->specBurger(15));

        $this->assertSame(10.0, $metrics->cost);
        $this->assertSame(5.0, $metrics->profit);
        $this->assertSame(33.33, $metrics->marginPercent);
        $this->assertSame(66.67, $metrics->foodCostPercent);
        $this->assertSame(ProfitabilityStatus::Healthy, $metrics->status);
    }

    public function test_a_burger_sold_below_cost_is_flagged_as_a_loss(): void
    {
        $metrics = $this->service->getProfitabilityMetrics($this->specBurger(5));

        $this->assertSame(-5.0, $metrics->profit);
        $this->assertSame(-100.0, $metrics->marginPercent);
        $this->assertSame(ProfitabilityStatus::Loss, $metrics->status);
        $this->assertTrue($metrics->isFlagged());
    }

    public function test_a_positive_margin_under_the_threshold_is_flagged(): void
    {
        config(['profitability.healthy_margin_percent' => 65]);

        $metrics = $this->service->getProfitabilityMetrics($this->specBurger(15)); // 33,33 %

        $this->assertSame(ProfitabilityStatus::BelowThreshold, $metrics->status);
        $this->assertSame(['below_threshold'], $metrics->toArray()['flags']);
    }

    public function test_combos_are_costed_through_their_nested_products(): void
    {
        $this->seed(StockDemoSeeder::class);

        // Cheeseburger 160 g × 0,048 + 1 × 1,80 + 30 g × 0,056 + 15,5 ml × 0,024 = 11,532
        // Fries 150 g × 0,022 = 3,30; Coke 9,50 → Box Cheeseburger = 24,332
        $box = Product::query()->where('saipos_sku_id', 'HAUS-BOX-CHEESE')->sole();

        $metrics = $this->service->getProfitabilityMetrics($box);

        $this->assertSame(24.33, $metrics->cost);
        $this->assertSame(30.57, $metrics->profit);    // 54,90 − 24,332
        $this->assertSame(55.68, $metrics->marginPercent);
    }

    public function test_missing_ingredient_costs_are_reported_instead_of_counted_as_free(): void
    {
        $burger = $this->specBurger(15);
        InventoryItem::query()->where('name', 'Patty')->update(['cost_per_unit' => null]);

        $metrics = $this->service->getProfitabilityMetrics($burger->fresh());

        $this->assertSame(5.0, $metrics->cost); // without the patty
        $this->assertSame(['Patty'], $metrics->missingCostItems);
        $this->assertSame(ProfitabilityStatus::IncompleteCost, $metrics->status);
        $this->assertFalse($metrics->toArray()['cost_is_complete']);
    }

    public function test_a_known_loss_stays_a_loss_even_with_missing_costs(): void
    {
        $burger = $this->specBurger(3);
        InventoryItem::query()->where('name', 'Patty')->update(['cost_per_unit' => null]);

        $metrics = $this->service->getProfitabilityMetrics($burger->fresh()); // partial cost 5 > price 3

        $this->assertSame(ProfitabilityStatus::Loss, $metrics->status);
        $this->assertSame(['loss', 'incomplete_cost'], $metrics->toArray()['flags']);
    }

    public function test_products_without_a_recipe_or_price_get_their_own_status(): void
    {
        $unmapped = Product::query()->create(['saipos_sku_id' => 'NONE', 'name' => 'No recipe', 'price' => 10]);
        $this->assertSame(ProfitabilityStatus::Unmapped, $this->service->getProfitabilityMetrics($unmapped)->status);

        $component = $this->specBurger(0);
        $metrics = $this->service->getProfitabilityMetrics($component);
        $this->assertSame(ProfitabilityStatus::NoPrice, $metrics->status);
        $this->assertNull($metrics->marginPercent);
        $this->assertFalse($metrics->isFlagged());
    }

    public function test_the_system_report_lists_every_product_worst_first_with_a_summary(): void
    {
        $this->seed(StockDemoSeeder::class);
        config(['profitability.healthy_margin_percent' => 65]);
        $this->specBurger(5); // a loss

        $report = $this->service->generateSystemProfitabilityReport();

        $this->assertSame('BRL', $report['currency']);
        $this->assertSame(65.0, $report['healthy_margin_percent']);
        $this->assertSame(7, $report['summary']['products']);
        $this->assertSame(1, $report['summary']['by_status']['loss']);
        $this->assertSame('Spec burger', $report['products'][0]['name']);
        $this->assertSame('loss', $report['products'][0]['status']);

        $statuses = array_column($report['products'], 'status');
        $severities = array_map(fn (string $s): int => ProfitabilityStatus::from($s)->severity(), $statuses);
        $sorted = $severities;
        sort($sorted);
        $this->assertSame($sorted, $severities, 'Products are ordered from most to least urgent.');

        $this->assertSame('Spec burger', $report['summary']['lowest_margin']['name']);
        $this->assertNotNull($report['summary']['average_margin_percent']);
    }
}
