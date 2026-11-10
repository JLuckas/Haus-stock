<?php

namespace Tests\Feature\Web;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        foreach (['/dashboard', '/inventory', '/products', '/orders', '/manual-deductions'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_every_page_renders_for_a_signed_in_user(): void
    {
        $item = InventoryItem::query()->firstOrFail();
        $product = Product::query()->where('saipos_sku_id', 'HAUS-BOX-1')->firstOrFail();
        $order = Order::query()->create([
            'saipos_order_id' => 'WEB-1',
            'status' => 'CONFIRMED',
            'last_error' => 'Order WEB-1: item "Mystery" (SKU NOPE) has no matching product.',
            'raw_payload' => ['order_id' => 'WEB-1', 'items' => [
                ['integration_code' => 'HAUS-BOX-1', 'desc_item' => 'Box 1', 'quantity' => 1],
                ['integration_code' => 'NOPE', 'desc_item' => 'Mystery', 'quantity' => 1],
            ]],
        ]);

        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk()->assertSee('Failed orders')->assertSee('WEB-1');
        $this->get(route('inventory.index'))->assertOk()->assertSee('Blend bovino')->assertSee('20 kg')->assertSee('20.000 g')->assertSee('4 L');
        $this->get(route('inventory.create'))->assertOk();
        $this->get(route('inventory.edit', $item))->assertOk();
        $this->get(route('inventory.receive', $item))->assertOk();
        $this->get(route('products.index'))->assertOk()->assertSee('160 g Blend bovino');
        $this->get(route('products.create'))->assertOk();
        $this->get(route('products.edit', $product))->assertOk();
        $this->get(route('orders.index'))->assertOk()->assertSee('WEB-1');
        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee('Not registered')
            ->assertSee(route('products.create', ['sku' => 'NOPE', 'name' => 'Mystery']));
        $this->get(route('manual-deductions.index'))->assertOk();
        $this->get(route('manual-deductions.create', ['item' => $item->id]))->assertOk();
    }
}
