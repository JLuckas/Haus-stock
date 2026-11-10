<?php

namespace Tests\Feature\Saipos;

use App\Models\InventoryItem;
use App\Models\Order;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PollSaiposOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);

        config([
            'saipos.base_url' => 'https://saipos.test',
            'saipos.id_partner' => 'partner-id',
            'saipos.secret' => 'partner-secret',
            'saipos.cod_store' => '123',
            'saipos.endpoints.orders' => '/orders',
        ]);
    }

    public function test_a_polling_cycle_deducts_stock_once_per_order(): void
    {
        Http::fake([
            'saipos.test/auth' => Http::response(['token' => 'tok-1']),
            'saipos.test/orders*' => Http::response([
                ['order_id' => 'P1', 'status' => 'CONFIRMED', 'items' => [
                    ['integration_code' => 'HAUS-BOX-1', 'desc_item' => 'Box 1', 'quantity' => 1],
                ]],
                ['order_id' => 'P2', 'status' => 'CONFIRMED', 'items' => [
                    ['integration_code' => 'UNKNOWN', 'desc_item' => 'Not registered', 'quantity' => 1],
                ]],
            ]),
        ]);

        $this->artisan('saipos:poll', ['--once' => true])
            ->expectsOutputToContain('fetched=2 processed=1 skipped=0 cancelled=0 failed=1')
            ->assertSuccessful();

        // Second cycle sees the same window: P1 is skipped, P2 fails again until its SKU is registered.
        $this->artisan('saipos:poll', ['--once' => true])
            ->expectsOutputToContain('fetched=2 processed=0 skipped=1 cancelled=0 failed=1')
            ->assertSuccessful();

        $this->assertEquals(20000 - 160, (float) InventoryItem::query()->where('name', 'Blend bovino')->value('current_stock'));
        $this->assertTrue(Order::query()->where('saipos_order_id', 'P1')->sole()->is_stock_processed);
        $this->assertFalse(Order::query()->where('saipos_order_id', 'P2')->sole()->is_stock_processed);
    }

    public function test_it_stops_on_configuration_errors_instead_of_looping(): void
    {
        config(['saipos.endpoints.orders' => null]);
        Http::fake();

        $this->artisan('saipos:poll')
            ->expectsOutputToContain('SAIPOS_ORDERS_ENDPOINT is not set')
            ->assertFailed();
    }

    public function test_a_transient_api_error_does_not_stop_the_poller(): void
    {
        Http::fake([
            'saipos.test/auth' => Http::response(['token' => 'tok-1']),
            'saipos.test/orders*' => Http::response(['message' => 'Bad request'], 400),
        ]);

        $this->artisan('saipos:poll', ['--once' => true])
            ->expectsOutputToContain('HTTP 400')
            ->assertSuccessful();
    }
}
