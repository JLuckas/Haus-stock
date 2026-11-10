<?php

namespace Tests\Feature\Web;

use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAndDeductionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_a_failed_order_can_be_retried_after_registering_its_product(): void
    {
        $order = Order::query()->create([
            'saipos_order_id' => 'R1',
            'status' => 'CONFIRMED',
            'last_error' => 'unmapped',
            'raw_payload' => ['order_id' => 'R1', 'items' => [
                ['integration_code' => 'SMASH', 'desc_item' => 'Smash', 'quantity' => 2],
            ]],
        ]);

        // Still unmapped: retry fails and nothing is deducted.
        $this->post(route('orders.retry', $order))->assertSessionHas('error');
        $this->assertFalse($order->fresh()->is_stock_processed);

        $smash = Product::query()->create(['saipos_sku_id' => 'SMASH', 'name' => 'Smash', 'price' => 30]);
        $smash->ingredients()->attach(InventoryItem::query()->where('name', 'Blend bovino')->value('id'), ['quantity_needed' => 90]);

        $this->post(route('orders.retry', $order))->assertSessionHas('success', 'Stock deducted for order R1.');

        $this->assertTrue($order->fresh()->is_stock_processed);
        $this->assertNull($order->fresh()->last_error);
        $this->assertEquals(20000 - 180, (float) InventoryItem::query()->where('name', 'Blend bovino')->value('current_stock'));

        // A processed order can't be retried again.
        $this->post(route('orders.retry', $order))->assertSessionHas('error');
        $this->assertEquals(20000 - 180, (float) InventoryItem::query()->where('name', 'Blend bovino')->value('current_stock'));
    }

    public function test_the_order_list_filters_by_stock_state(): void
    {
        Order::query()->create(['saipos_order_id' => 'OK-1', 'is_stock_processed' => true, 'stock_processed_at' => now()]);
        Order::query()->create(['saipos_order_id' => 'BAD-1', 'last_error' => 'boom']);
        Order::query()->create(['saipos_order_id' => 'CAN-1', 'status' => 'CANCELLED']);

        $this->get(route('orders.index', ['state' => 'failed']))->assertSee('BAD-1')->assertDontSee('OK-1')->assertDontSee('CAN-1');
        $this->get(route('orders.index', ['state' => 'processed']))->assertSee('OK-1')->assertDontSee('BAD-1');
        $this->get(route('orders.index', ['state' => 'cancelled']))->assertSee('CAN-1')->assertDontSee('BAD-1');
    }

    public function test_a_manual_deduction_is_recorded_with_the_signed_in_user(): void
    {
        $buns = InventoryItem::query()->where('name', 'Pão brioche')->sole();

        $this->post(route('manual-deductions.store'), [
            'inventory_item_id' => $buns->id,
            'quantity' => '2',
            'reason' => 'Employee meal',
        ])->assertRedirect(route('manual-deductions.index'))
            ->assertSessionHas('success', 'Deducted 2 un of "Pão brioche". Stock now: 118 un.');

        $deduction = ManualDeduction::query()->sole();
        $this->assertSame($this->user->id, $deduction->user_id);
        $this->assertSame('Employee meal', $deduction->reason);

        $this->get(route('manual-deductions.index'))->assertSee($this->user->name)->assertSee('−2 un');
    }

    public function test_a_manual_deduction_is_validated(): void
    {
        $this->post(route('manual-deductions.store'), ['inventory_item_id' => 999999, 'quantity' => '-1', 'reason' => ''])
            ->assertSessionHasErrors(['inventory_item_id', 'quantity', 'reason']);

        $this->assertSame(0, ManualDeduction::query()->count());
    }
}
