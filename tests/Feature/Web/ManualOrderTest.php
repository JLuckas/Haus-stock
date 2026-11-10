<?php

namespace Tests\Feature\Web;

use App\Enums\OrderSource;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->user = User::factory()->create(['name' => 'Ana Caixa']);
        $this->actingAs($this->user);
    }

    public function test_a_manual_order_deducts_stock_through_the_normal_pipeline(): void
    {
        $response = $this->post(route('orders.store'), [
            'items' => [
                ['product_id' => $this->productId('HAUS-BOX-CHEESE'), 'quantity' => '2'],  // combo: burger + fries + coke
                ['product_id' => $this->productId('HAUS-ADD-BACON'), 'quantity' => '1'],
                ['product_id' => '', 'quantity' => '1'],                                       // empty row, ignored
            ],
            'notes' => 'Balcão, cliente sem cadastro',
        ]);

        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success', "Order {$order->saipos_order_id} registered and stock deducted.");

        $this->assertMatchesRegularExpression('/^M-\d{6}-[2-9A-HJ-NP-Z]{4}$/', $order->saipos_order_id);
        $this->assertSame(OrderSource::Manual, $order->source);
        $this->assertSame($this->user->id, $order->created_by);
        $this->assertSame('Balcão, cliente sem cadastro', $order->notes);
        $this->assertTrue($order->is_stock_processed);

        $this->assertStock('Blend bovino', 20000 - 2 * 160);
        $this->assertStock('Batata congelada', 10000 - 2 * 150);
        $this->assertStock('Coca-Cola Zero 2L', 24 - 2);
        $this->assertStock('Bacon', 5000 - 30);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Box Cheeseburger')
            ->assertSee('Manual · Ana Caixa')
            ->assertSee('Balcão, cliente sem cadastro');

        $this->get(route('orders.index'))->assertSee($order->saipos_order_id)->assertSee('Manual');
    }

    public function test_an_order_typed_with_its_saipos_number_is_not_deducted_again_by_the_sync(): void
    {
        $this->post(route('orders.store'), [
            'reference' => '987654',
            'items' => [['product_id' => $this->productId('HAUS-BOX-1'), 'quantity' => '1']],
        ])->assertSessionHasNoErrors();

        $this->assertStock('Blend bovino', 20000 - 160);

        // Later the poller imports the very same Saipos order.
        config([
            'saipos.base_url' => 'https://saipos.test',
            'saipos.id_partner' => 'partner', 'saipos.secret' => 'secret', 'saipos.cod_store' => '1',
            'saipos.endpoints.orders' => '/orders',
        ]);
        Http::fake([
            'saipos.test/auth' => Http::response(['token' => 't']),
            'saipos.test/orders*' => Http::response([
                ['order_id' => '987654', 'status' => 'CONFIRMED', 'items' => [
                    ['integration_code' => 'HAUS-BOX-1', 'desc_item' => 'Box 1', 'quantity' => 1],
                ]],
            ]),
        ]);

        $this->artisan('saipos:poll', ['--once' => true])
            ->expectsOutputToContain('fetched=1 processed=0 skipped=1')
            ->assertSuccessful();

        $this->assertStock('Blend bovino', 20000 - 160);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_order_is_validated_before_anything_is_saved(): void
    {
        Product::query()->create(['saipos_sku_id' => 'EMPTY', 'name' => 'Sem receita', 'price' => 10]);
        Order::query()->create(['saipos_order_id' => '555']);

        $this->post(route('orders.store'), ['items' => [['product_id' => '', 'quantity' => '1']]])
            ->assertSessionHasErrors(['items' => 'Add at least one product to the order.']);

        $this->post(route('orders.store'), [
            'items' => [
                ['product_id' => $this->productId('HAUS-BOX-1'), 'quantity' => '1'],
                ['product_id' => $this->productId('HAUS-BOX-1'), 'quantity' => '0'],
            ],
        ])->assertSessionHasErrors(['items.1.product_id', 'items.1.quantity']);

        $this->post(route('orders.store'), [
            'items' => [['product_id' => $this->productId('EMPTY'), 'quantity' => '1']],
        ])->assertSessionHasErrors(['items.0.product_id' => 'Product "Sem receita" (EMPTY) has no recipe, no products inside it and no standalone item.']);

        $this->post(route('orders.store'), [
            'reference' => '555',
            'items' => [['product_id' => $this->productId('HAUS-BOX-1'), 'quantity' => '1']],
        ])->assertSessionHasErrors(['reference' => 'An order with this number is already registered.']);

        $this->post(route('orders.store'), [
            'reference' => 'm-123',
            'items' => [['product_id' => $this->productId('HAUS-BOX-1'), 'quantity' => '1']],
        ])->assertSessionHasErrors('reference');

        $this->assertSame(1, Order::query()->count());
        $this->assertStock('Blend bovino', 20000);
    }

    public function test_the_form_renders_with_prices_and_previews(): void
    {
        $this->get(route('orders.create'))
            ->assertOk()
            ->assertSee('Box Cheeseburger — R$ 54,90')
            ->assertSee('Register order');

        $this->get(route('orders.index'))->assertSee(route('orders.create'));
    }

    public function test_manual_orders_do_not_count_as_a_sign_of_life_from_the_saipos_sync(): void
    {
        $this->post(route('orders.store'), [
            'items' => [['product_id' => $this->productId('HAUS-FRIES'), 'quantity' => '1']],
        ]);

        $this->get(route('dashboard'))->assertSee('No Saipos orders received yet.');
    }

    private function productId(string $sku): int
    {
        return Product::query()->where('saipos_sku_id', $sku)->value('id');
    }

    private function assertStock(string $name, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, (float) InventoryItem::query()->where('name', $name)->value('current_stock'), 0.0005, $name);
    }
}
