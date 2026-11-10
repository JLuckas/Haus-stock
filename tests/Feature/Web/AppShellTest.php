<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create(['name' => 'Maria Souza']));
    }

    public function test_the_sidebar_marks_the_current_section_and_the_breadcrumb_names_it(): void
    {
        $html = $this->get(route('products.costs'))->assertOk()->getContent();

        // Products → Costs belongs to Finance; the Products (catalog) item must not be active too.
        $this->assertMatchesRegularExpression('#aria-current="page"\s+title="Product costs"#', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-current="page"\s+title="Products"#', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);

        $edit = $this->get(route('products.edit', \App\Models\Product::query()->value('id')))->getContent();
        $this->assertMatchesRegularExpression('#aria-current="page"\s+title="Products"#', $edit);
    }

    public function test_the_sidebar_flags_failed_orders(): void
    {
        Order::query()->create([
            'saipos_order_id' => 'BAD-1',
            'raw_payload' => ['order_id' => 'BAD-1', 'items' => []],
            'last_error' => 'boom',
        ]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#title="Orders".{0,2500}?bg-red-100 text-red-700[^"]*">1</span>#s', $html);
    }

    public function test_the_dashboard_greets_and_shows_money_tiles(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Good', false)          // greeting
            ->assertSee('Maria')
            ->assertSee('MS')                    // avatar initials
            ->assertSee('Sales today')
            ->assertSee('Net result this month');
    }

    public function test_the_command_palette_lists_pages_and_actions(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('commandPalette(', false)
            ->assertSee(route('orders.create'), false)
            ->assertSee(route('reports.pnl'), false)
            ->assertSee('Search or jump to…');
    }

    public function test_flash_messages_show_as_a_toast(): void
    {
        $this->withSession(['success' => 'Saved it.'])
            ->get(route('dashboard'))
            ->assertSee('Saved it.')
            ->assertSee('role="status"', false);
    }
}
