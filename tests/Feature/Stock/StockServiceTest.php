<?php

namespace Tests\Feature\Stock;

use App\DataTransferObjects\Saipos\NormalizedOrder;
use App\DataTransferObjects\Saipos\NormalizedOrderItem;
use App\Enums\StockProcessingOutcome;
use App\Exceptions\Stock\InvalidRecipeException;
use App\Exceptions\Stock\UnmappedProductException;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\Finance\FinancialLedger;
use App\Services\Stock\BillOfMaterials;
use App\Services\Stock\StockService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->service = app(StockService::class);
    }

    public function test_it_deducts_bom_standalone_and_choice_items(): void
    {
        $result = $this->service->processOrder($this->order('A1', [
            new NormalizedOrderItem('HAUS-BOX-1', 'Box 1', 2),
            new NormalizedOrderItem('HAUS-ADD-BACON', 'Adicional bacon', 2, isChoice: true),
            new NormalizedOrderItem('HAUS-COKE-ZERO-2L', 'Coca-Cola Zero 2L', 1),
        ]));

        $this->assertSame(StockProcessingOutcome::Processed, $result->outcome);
        $this->assertStock('Blend bovino', 20000 - 2 * 160);
        $this->assertStock('Pão brioche', 120 - 2 * 2);
        $this->assertStock('Bacon', 5000 - 2 * 50 - 2 * 30);
        $this->assertStock('Coca-Cola Zero 2L', 24 - 1);

        $order = Order::query()->where('saipos_order_id', 'A1')->sole();
        $this->assertTrue($order->is_stock_processed);
        $this->assertNotNull($order->stock_processed_at);
    }

    public function test_it_deducts_fractional_quantities(): void
    {
        $this->service->processOrder($this->order('A2', [
            new NormalizedOrderItem('HAUS-CHEESE', 'Cheeseburger', 3),
        ]));

        $this->assertStock('Molho da casa', 4000 - 3 * 15.5);
    }

    public function test_the_same_order_is_only_deducted_once(): void
    {
        $order = $this->order('A3', [new NormalizedOrderItem('HAUS-BOX-1', 'Box 1', 1)]);

        $first = $this->service->processOrder($order);
        $second = $this->service->processOrder($order);

        $this->assertSame(StockProcessingOutcome::Processed, $first->outcome);
        $this->assertSame(StockProcessingOutcome::AlreadyProcessed, $second->outcome);
        $this->assertStock('Blend bovino', 20000 - 160);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_an_unmapped_main_item_fails_the_whole_order(): void
    {
        try {
            $this->service->processOrder($this->order('A4', [
                new NormalizedOrderItem('HAUS-BOX-1', 'Box 1', 1),
                new NormalizedOrderItem('NEW-ITEM', 'Burger not registered yet', 1),
            ]));
            $this->fail('Expected UnmappedProductException.');
        } catch (UnmappedProductException) {
        }

        $this->assertStock('Blend bovino', 20000);

        $order = Order::query()->where('saipos_order_id', 'A4')->sole();
        $this->assertFalse($order->is_stock_processed);
        $this->assertStringContainsString('NEW-ITEM', $order->last_error);
    }

    public function test_a_failure_mid_deduction_rolls_back_earlier_deductions(): void
    {
        $calls = 0;
        $service = Mockery::mock(StockService::class, [app(BillOfMaterials::class), app(FinancialLedger::class)])->makePartial();
        $service->shouldReceive('deductFromItem')->andReturnUsing(function (int $id, float $quantity) use (&$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('Simulated database failure');
            }

            InventoryItem::query()->whereKey($id)->decrement('current_stock', $quantity);
        });

        try {
            $service->processOrder($this->order('A5', [new NormalizedOrderItem('HAUS-BOX-1', 'Box 1', 1)]));
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database failure', $e->getMessage());
        }

        // The first ingredient was decremented before the failure; the rollback must restore it.
        $this->assertSame(2, $calls);
        $this->assertStock('Blend bovino', 20000);
        $this->assertStock('Pão brioche', 120);
        $this->assertStock('Bacon', 5000);
        $this->assertFalse(Order::query()->where('saipos_order_id', 'A5')->sole()->is_stock_processed);
    }

    public function test_an_unmapped_choice_item_is_ignored(): void
    {
        $result = $this->service->processOrder($this->order('A6', [
            new NormalizedOrderItem('HAUS-BOX-1', 'Box 1', 1),
            new NormalizedOrderItem('NO-STOCK-MODIFIER', 'Ponto da carne', 1, isChoice: true),
        ]));

        $this->assertSame(StockProcessingOutcome::Processed, $result->outcome);
        $this->assertStock('Blend bovino', 20000 - 160);
    }

    public function test_a_cancelled_order_does_not_deduct_stock(): void
    {
        $result = $this->service->processOrder($this->order('A7', [
            new NormalizedOrderItem('HAUS-BOX-1', 'Box 1', 1),
        ], NormalizedOrder::STATUS_CANCELLED));

        $this->assertSame(StockProcessingOutcome::SkippedCancelled, $result->outcome);
        $this->assertStock('Blend bovino', 20000);
        $this->assertSame('CANCELLED', Order::query()->where('saipos_order_id', 'A7')->value('status'));
    }

    public function test_a_product_without_recipe_or_standalone_item_fails(): void
    {
        Product::query()->create(['saipos_sku_id' => 'EMPTY', 'name' => 'Empty product', 'price' => 1]);

        $this->expectException(InvalidRecipeException::class);

        $this->service->processOrder($this->order('A8', [new NormalizedOrderItem('EMPTY', 'Empty product', 1)]));
    }

    public function test_stock_may_go_negative_because_the_sale_already_happened(): void
    {
        $this->service->processOrder($this->order('A9', [
            new NormalizedOrderItem('HAUS-COKE-ZERO-2L', 'Coca-Cola Zero 2L', 30),
        ]));

        $this->assertStock('Coca-Cola Zero 2L', -6);
    }

    /** @param list<NormalizedOrderItem> $items */
    private function order(string $id, array $items, string $status = 'CONFIRMED'): NormalizedOrder
    {
        return new NormalizedOrder($id, $status, $items);
    }

    private function assertStock(string $name, float $expected): void
    {
        $actual = (float) InventoryItem::query()->where('name', $name)->value('current_stock');

        $this->assertEqualsWithDelta($expected, $actual, 0.0005, "Unexpected stock for {$name}.");
    }
}
