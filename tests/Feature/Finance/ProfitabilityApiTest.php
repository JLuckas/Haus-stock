<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfitabilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
    }

    public function test_the_report_requires_a_token(): void
    {
        // No Accept header on purpose: API routes must still answer 401 JSON, not redirect to /login.
        $this->get('/api/reports/profitability')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_session_login_is_not_enough(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->getJson('/api/reports/profitability')
            ->assertUnauthorized();
    }

    public function test_it_returns_the_menu_financial_health(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/profitability')
            ->assertOk()
            ->assertJsonPath('currency', 'BRL')
            ->assertJsonPath('summary.products', 6)
            ->assertJsonStructure([
                'generated_at',
                'healthy_margin_percent',
                'summary' => ['products', 'flagged', 'by_status' => ['loss', 'unmapped', 'incomplete_cost', 'below_threshold', 'no_price', 'healthy'], 'average_margin_percent', 'lowest_margin'],
                'products' => [[
                    'id', 'name', 'saipos_sku_id', 'price', 'cost', 'profit', 'margin_percent', 'food_cost_percent',
                    'status', 'flags', 'is_flagged', 'cost_is_complete', 'missing_cost_items',
                    'cost_breakdown' => [['inventory_item_id', 'name', 'unit', 'quantity', 'cost_per_unit', 'cost']],
                    'error',
                ]],
            ]);
    }

    public function test_a_token_from_the_cli_works_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['email' => 'bi@haus.test']);

        $this->artisan('api:token', ['email' => 'bi@haus.test', '--name' => 'bi'])->assertSuccessful();
        $token = $user->createToken('bi')->plainTextToken; // a second token we know the value of

        $this->withToken($token)->getJson('/api/reports/profitability')->assertOk();

        $this->artisan('api:token', ['email' => 'bi@haus.test', '--name' => 'bi', '--revoke' => true])
            ->expectsOutputToContain('Revoked 2 token(s)')
            ->assertSuccessful();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/reports/profitability')->assertUnauthorized();
    }
}
