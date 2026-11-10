<?php

namespace Tests\Feature\Web;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Stock\ManualDeductionService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_an_item_can_be_created(): void
    {
        $this->post(route('inventory.store'), [
            'name' => 'Picles',
            'type' => 'ingredient',
            'unit_of_measure' => 'g',
            'current_stock' => '1500.5',
            'minimum_stock' => '300',
        ])->assertRedirect(route('inventory.index'));

        $item = InventoryItem::query()->where('name', 'Picles')->sole();
        $this->assertEquals(1500.5, (float) $item->current_stock);
    }

    public function test_editing_never_overwrites_current_stock(): void
    {
        $bacon = $this->item('Bacon');

        $this->put(route('inventory.update', $bacon), [
            'name' => 'Bacon fatiado',
            'type' => 'ingredient',
            'unit_of_measure' => 'g',
            'minimum_stock' => '1200',
            'current_stock' => '999999',
        ])->assertRedirect(route('inventory.index'));

        $bacon->refresh();
        $this->assertSame('Bacon fatiado', $bacon->name);
        $this->assertEquals(5000, (float) $bacon->current_stock);
        $this->assertEquals(1200, (float) $bacon->minimum_stock);
    }

    public function test_receiving_stock_adds_to_the_current_amount(): void
    {
        $buns = $this->item('Pão brioche');

        $this->post(route('inventory.receive.store', $buns), ['quantity' => '48'])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success', 'Received 48 un of "Pão brioche". Stock now: 168 un.');
    }

    public function test_receiving_requires_a_positive_quantity(): void
    {
        $this->post(route('inventory.receive.store', $this->item('Bacon')), ['quantity' => '0'])
            ->assertSessionHasErrors('quantity');
    }

    public function test_an_item_used_by_a_recipe_cannot_be_deleted_and_the_error_names_the_products(): void
    {
        $bacon = $this->item('Bacon');

        $this->delete(route('inventory.destroy', $bacon))->assertSessionHas(
            'error',
            '"Bacon" can\'t be deleted because it is an ingredient of Adicional bacon and Box 1. Remove it from those products first.',
        );

        $this->assertNotSoftDeleted($bacon);
    }

    public function test_a_standalone_item_sold_as_a_product_cannot_be_deleted(): void
    {
        $this->delete(route('inventory.destroy', $this->item('Coca-Cola Zero 2L')))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'it is sold as Coca-Cola Zero 2L'));
    }

    public function test_an_item_with_only_deduction_history_can_be_deleted_and_its_history_stays_readable(): void
    {
        $special = InventoryItem::query()->create([
            'name' => 'Ultrabacon', 'type' => 'standalone_product', 'unit_of_measure' => 'un', 'current_stock' => 5, 'minimum_stock' => 0,
        ]);
        app(ManualDeductionService::class)->deduct($special, 1, 'Employee meal');

        $this->delete(route('inventory.destroy', $special))
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success', '"Ultrabacon" deleted. Its past deductions stay in the history.');

        $this->assertSoftDeleted($special);
        $this->get(route('inventory.index'))
            ->assertSee('"Ultrabacon" deleted.') // the flash message
            ->assertDontSee(route('inventory.edit', $special)); // but no row for it
        $this->get(route('manual-deductions.create'))->assertDontSee('Ultrabacon');
        $this->get(route('manual-deductions.index'))->assertSee('Ultrabacon (deleted)');
        $this->get(route('dashboard'))->assertOk()->assertSee('Ultrabacon (deleted)');

        // A deleted item can't be used any more…
        $this->post(route('manual-deductions.store'), ['inventory_item_id' => $special->id, 'quantity' => 1, 'reason' => 'Waste'])
            ->assertSessionHasErrors('inventory_item_id');
        $this->get(route('inventory.edit', $special))->assertNotFound();

        // …and its name is free again.
        $this->post(route('inventory.store'), [
            'name' => 'Ultrabacon', 'type' => 'ingredient', 'unit_of_measure' => 'g', 'current_stock' => '0', 'minimum_stock' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, InventoryItem::query()->where('name', 'Ultrabacon')->count());
    }

    public function test_an_item_used_inside_a_product_deduction_can_be_deleted(): void
    {
        $potato = $this->item('Batata congelada');
        Product::query()->where('saipos_sku_id', 'HAUS-FRIES')->sole()->ingredients()->detach();
        app(ManualDeductionService::class)->deduct($potato, 100, 'Waste');

        $this->delete(route('inventory.destroy', $potato))->assertSessionHas('success');

        $this->assertSoftDeleted($potato);
    }

    public function test_an_unused_item_can_be_deleted(): void
    {
        $unused = InventoryItem::query()->create([
            'name' => 'Unused', 'type' => 'ingredient', 'unit_of_measure' => 'g', 'current_stock' => 0, 'minimum_stock' => 0,
        ]);

        $this->delete(route('inventory.destroy', $unused))->assertRedirect(route('inventory.index'));

        $this->assertSoftDeleted($unused);
    }

    public function test_a_linked_standalone_item_cannot_become_an_ingredient(): void
    {
        $coke = $this->item('Coca-Cola Zero 2L');

        $this->put(route('inventory.update', $coke), [
            'name' => $coke->name,
            'type' => 'ingredient',
            'unit_of_measure' => 'un',
            'minimum_stock' => '6',
        ])->assertSessionHasErrors('type');
    }

    private function item(string $name): InventoryItem
    {
        return InventoryItem::query()->where('name', $name)->sole();
    }
}
