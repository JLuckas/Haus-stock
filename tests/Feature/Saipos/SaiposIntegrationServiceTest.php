<?php

namespace Tests\Feature\Saipos;

use App\Exceptions\Saipos\SaiposApiException;
use App\Services\Saipos\SaiposIntegrationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaiposIntegrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saipos.base_url' => 'https://saipos.test',
            'saipos.id_partner' => 'partner-id',
            'saipos.secret' => 'partner-secret',
            'saipos.cod_store' => '123',
            'saipos.endpoints.orders' => '/orders',
        ]);
    }

    public function test_it_authenticates_lists_and_normalizes_orders(): void
    {
        Http::fake([
            'saipos.test/auth' => Http::response(['token' => 'tok-1']),
            'saipos.test/orders*' => Http::response([
                [
                    'order_id' => 'A1',
                    'status' => 'CONFIRMED',
                    'items' => [
                        [
                            'integration_code' => 'HAUS-BOX-1',
                            'desc_item' => 'Box 1',
                            'quantity' => 2,
                            'choice_items' => [
                                ['integration_code' => 'HAUS-ADD-BACON', 'desc_item_choice' => 'Bacon extra', 'quantity' => 1],
                                ['desc_item_choice' => 'Sem cebola', 'quantity' => 1],
                            ],
                        ],
                    ],
                ],
                ['order_id' => 'B2'], // summary row: details are fetched from GET /order
            ]),
            'saipos.test/order?*' => Http::response([
                'items' => [
                    ['integration_code' => 'HAUS-COKE-ZERO-2L', 'desc_sale_item' => 'Coca-Cola Zero 2L', 'quantity' => 1, 'deleted' => 'N'],
                    ['integration_code' => 'HAUS-BOX-1', 'desc_sale_item' => 'Box 1', 'quantity' => 1, 'deleted' => 'Y'],
                ],
            ]),
        ]);

        $orders = app(SaiposIntegrationService::class)->fetchRecentOrders(now()->subHour());

        $this->assertCount(2, $orders);

        [$first, $second] = $orders;
        $this->assertSame('A1', $first->saiposOrderId);
        $this->assertSame('CONFIRMED', $first->status);
        $this->assertCount(2, $first->items, 'The code-less modifier must be dropped.');
        $this->assertSame('HAUS-ADD-BACON', $first->items[1]->skuId);
        $this->assertTrue($first->items[1]->isChoice);
        $this->assertSame(2.0, $first->items[1]->quantity, 'Choice quantity is multiplied by the parent quantity.');

        $this->assertSame('B2', $second->saiposOrderId);
        $this->assertCount(1, $second->items, 'Deleted items must be dropped.');
        $this->assertSame('HAUS-COKE-ZERO-2L', $second->items[0]->skuId);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://saipos.test/auth'
            && $r['idPartner'] === 'partner-id'
            && $r['secret'] === 'partner-secret');
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://saipos.test/order?')
            && $r->hasHeader('Authorization', 'tok-1')
            && str_contains($r->url(), 'order_id=B2')
            && str_contains($r->url(), 'cod_store=123'));
    }

    public function test_the_token_is_cached_between_calls(): void
    {
        Http::fake([
            'saipos.test/auth' => Http::response(['token' => 'tok-1']),
            'saipos.test/orders*' => Http::response([]),
        ]);

        $service = app(SaiposIntegrationService::class);
        $service->fetchRecentOrders(now()->subHour());
        $service->fetchRecentOrders(now()->subHour());

        $this->assertCount(1, Http::recorded(fn (Request $r): bool => str_ends_with($r->url(), '/auth')));
    }

    public function test_it_reauthenticates_once_when_the_token_is_rejected(): void
    {
        Http::fake([
            'saipos.test/auth' => Http::sequence()
                ->push(['token' => 'expired-token'])
                ->push(['token' => 'fresh-token']),
            'saipos.test/orders*' => Http::sequence()
                ->push(['message' => 'Token inválido ou expirado.'], 401)
                ->push([]),
        ]);

        $orders = app(SaiposIntegrationService::class)->fetchRecentOrders(now()->subHour());

        $this->assertSame([], $orders);

        $listCalls = Http::recorded(fn (Request $r): bool => str_contains($r->url(), '/orders'))->values();
        $this->assertCount(2, $listCalls);
        $this->assertTrue($listCalls[0][0]->hasHeader('Authorization', 'expired-token'));
        $this->assertTrue($listCalls[1][0]->hasHeader('Authorization', 'fresh-token'));
    }

    public function test_a_missing_listing_endpoint_is_a_configuration_error(): void
    {
        config(['saipos.endpoints.orders' => null]);
        Http::fake();

        try {
            app(SaiposIntegrationService::class)->fetchRecentOrders(now()->subHour());
            $this->fail('Expected SaiposApiException.');
        } catch (SaiposApiException $e) {
            $this->assertTrue($e->isConfigurationError);
        }

        Http::assertNothingSent();
    }
}
