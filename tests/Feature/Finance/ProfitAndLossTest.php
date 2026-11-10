<?php

namespace Tests\Feature\Finance;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Finance\ProfitAndLossService;
use App\Services\Saipos\SaiposIntegrationService;
use App\Services\Stock\ManualDeductionService;
use App\Services\Stock\ManualOrderService;
use App\Services\Stock\StockService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitAndLossTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
    }

    public function test_a_deducted_order_saves_revenue_and_cost_per_item(): void
    {
        // Saipos prices win over product prices; add-on priced by aditional_price; discount subtracted.
        $this->processSaipos([
            'order_id' => 'S1', 'status' => 'CONFIRMED', 'total_discount' => 3,
            'items' => [[
                'integration_code' => 'HAUS-BOX-1', 'desc_item' => 'Box 1', 'quantity' => 1, 'unit_price' => 45,
                'choice_items' => [['integration_code' => 'HAUS-ADD-BACON', 'desc_item_choice' => 'Bacon', 'quantity' => 1, 'aditional_price' => 5]],
            ]],
        ]);

        $order = Order::query()->where('saipos_order_id', 'S1')->sole();
        $this->assertEquals(47.00, (float) $order->revenue);       // 45 + 5 − 3
        $this->assertEqualsWithDelta(16.24, (float) $order->cost, 0.0001); // Box 1 14,38 + bacon 1,86
        $this->assertTrue($order->cost_is_complete);
        $this->assertCount(2, $order->items);
        $this->assertTrue($order->items->firstWhere('is_add_on', true)->is_add_on);
    }

    public function test_a_manual_deduction_saves_its_cost_as_a_loss(): void
    {
        $deduction = app(ManualDeductionService::class)->deductProduct(
            Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->sole(), 1, 'Employee meal',
        );

        $this->assertEqualsWithDelta(11.532, (float) $deduction->fresh()->cost, 0.0001);
        $this->assertTrue($deduction->fresh()->cost_is_complete);
    }

    public function test_the_report_adds_up_profit_and_losses(): void
    {
        $this->manualOrder('HAUS-BOX-CHEESE', 2);                            // 2 × 54,90 = 109,80; cost 2 × 24,332
        app(ManualDeductionService::class)->deductProduct(Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->sole(), 1, 'Employee meal'); // 11,532
        app(ManualDeductionService::class)->deduct(InventoryItem::query()->where('name', 'Bacon')->sole(), 100, 'Waste / spoilage');          // 6,20

        $report = app(ProfitAndLossService::class)->report(today(), today());

        $this->assertSame(109.80, $report['revenue']);
        $this->assertSame(48.66, $report['cogs']);
        $this->assertSame(61.14, $report['gross_profit']);
        $this->assertSame(17.73, $report['losses']);
        $this->assertSame(43.40, $report['net']);
        $this->assertSame(1, $report['orders_count']);
        $this->assertSame(['Employee meal', 'Waste / spoilage'], array_column($report['losses_by_reason'], 'reason'));
        $this->assertSame('Box Cheeseburger', $report['products'][0]['name']);
        $this->assertSame(61.14, $report['products'][0]['profit']);
        $this->assertCount(1, $report['days']);
        $this->assertSame(43.40, $report['days'][0]['net']);
    }

    public function test_an_order_cancelled_after_deduction_counts_as_a_loss_not_revenue(): void
    {
        $this->processSaipos(['order_id' => 'C1', 'status' => 'CONFIRMED', 'items' => [['integration_code' => 'HAUS-BOX-1', 'desc_item' => 'Box 1', 'quantity' => 1]]]);
        $this->processSaipos(['order_id' => 'C1', 'status' => 'CANCELLED', 'items' => [['integration_code' => 'HAUS-BOX-1', 'desc_item' => 'Box 1', 'quantity' => 1]]]);

        $report = app(ProfitAndLossService::class)->report(today(), today());

        $this->assertSame(0.0, $report['revenue']);
        $this->assertSame(14.38, $report['losses']);
        $this->assertSame(ProfitAndLossService::CANCELLED_ORDERS, $report['losses_by_reason'][0]['reason']);
    }

    public function test_the_period_filter_and_snapshot_survive_cost_changes(): void
    {
        $old = app(ManualDeductionService::class)->deduct(InventoryItem::query()->where('name', 'Bacon')->sole(), 100, 'Waste / spoilage');
        $old->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->manualOrder('HAUS-FRIES', 1); // cost 150 g × 0,022 = 3,30

        // A later supplier price change must not rewrite the past.
        InventoryItem::query()->where('name', 'Batata congelada')->update(['cost_per_unit' => 1]);

        $report = app(ProfitAndLossService::class)->report(today()->subDays(29), today());

        $this->assertSame(0.0, $report['losses']);
        $this->assertSame(3.30, $report['cogs']);
        $this->assertCount(30, $report['days']);
        $this->assertSame('day', $report['chart']['granularity']);

        $long = app(ProfitAndLossService::class)->report(today()->subDays(99), today());
        $this->assertSame('week', $long['chart']['granularity']);
    }

    public function test_the_page_renders_and_validates_custom_ranges(): void
    {
        $this->actingAs(User::factory()->create());
        $this->manualOrder('HAUS-BOX-CHEESE', 1);

        foreach (['today', '7d', '30d', 'month', 'last_month'] as $period) {
            $this->get(route('reports.pnl', ['period' => $period]))->assertOk();
        }

        $this->get(route('reports.pnl', ['period' => 'today']))
            ->assertSee('Gross profit')
            ->assertSee('Box Cheeseburger')
            ->assertSee('R$ 54,90');

        $this->get(route('reports.pnl', ['period' => 'custom', 'from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk();
        $this->get(route('reports.pnl', ['period' => 'custom', 'from' => '2026-01-10', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
        $this->get(route('reports.pnl', ['period' => 'custom', 'from' => '2024-01-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    }

    public function test_backfill_values_records_made_before_snapshots(): void
    {
        $this->manualOrder('HAUS-FRIES', 2);
        $deduction = app(ManualDeductionService::class)->deduct(InventoryItem::query()->where('name', 'Bacon')->sole(), 100, 'Waste / spoilage');
        Order::query()->update(['revenue' => null, 'cost' => null]);
        ManualDeduction::query()->update(['cost' => null]);

        $this->artisan('finance:backfill')->expectsOutputToContain('Valued 1 order(s) and 1 manual deduction(s)')->assertSuccessful();

        $this->assertEquals(36.00, (float) Order::query()->sole()->revenue);
        $this->assertEqualsWithDelta(6.20, (float) $deduction->fresh()->cost, 0.0001);
    }

    private function processSaipos(array $payload): void
    {
        app(StockService::class)->processOrder(app(SaiposIntegrationService::class)->normalizeOrder($payload));
    }

    private function manualOrder(string $sku, float $quantity): Order
    {
        return app(ManualOrderService::class)->create(
            [['product_id' => Product::query()->where('saipos_sku_id', $sku)->value('id'), 'quantity' => $quantity]],
            null, null, null,
        );
    }
}
