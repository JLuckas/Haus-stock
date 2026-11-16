<?php

namespace Tests\Feature\Web;

use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\ProfitAndLossService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $bakery;

    private InventoryItem $buns;

    private InventoryItem $meat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->buns = InventoryItem::query()->where('name', 'Pão brioche')->sole();
        $this->meat = InventoryItem::query()->where('name', 'Blend bovino')->sole();
        $this->bakery = Supplier::query()->create(['name' => 'Padaria Pão Bom']);
        $this->bakery->items()->sync([$this->buns->id]);
    }

    private function purchase(array $overrides = []): array
    {
        return $overrides + [
            'supplier_id' => $this->bakery->id,
            'spent_on' => today()->toDateString(),
            'items' => [['inventory_item_id' => $this->buns->id, 'quantity' => '60', 'total' => '54']],
            'receive_now' => '1',
            'update_costs' => '1',
        ];
    }

    private function stock(InventoryItem $item): float
    {
        return (float) $item->fresh()->current_stock;
    }

    public function test_a_received_purchase_from_the_bun_supplier_increases_bun_stock(): void
    {
        $before = $this->stock($this->buns);

        $this->post(route('expenses.purchases.store'), $this->purchase())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $purchase = Expense::query()->where('kind', 'supplier')->sole();
        $this->assertSame('54.00', $purchase->amount);
        $this->assertTrue($purchase->isReceived());
        $this->assertSame($before + 60, $this->stock($this->buns));
        $this->assertEqualsWithDelta(0.9, (float) $this->buns->fresh()->cost_per_unit, 0.000001); // R$ 54 / 60 un

        $this->get(route('expenses.index', 'supplier'))
            ->assertOk()
            ->assertSee('Padaria Pão Bom')
            ->assertSee('60 un Pão brioche')
            ->assertSee('In stock');
    }

    public function test_quantities_are_typed_in_kg_and_stored_in_grams(): void
    {
        $before = $this->stock($this->meat);

        $this->post(route('expenses.purchases.store'), $this->purchase([
            'items' => [['inventory_item_id' => $this->meat->id, 'quantity' => '5', 'total' => '240']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame($before + 5000, $this->stock($this->meat));
        $this->assertSame('R$ 48,00/kg', $this->meat->fresh()->formattedCost());
        // The meat is now remembered as something this supplier sells.
        $this->assertTrue($this->bakery->items()->whereKey($this->meat->id)->exists());
    }

    public function test_an_order_on_the_way_is_activated_once_when_it_arrives(): void
    {
        $before = $this->stock($this->buns);
        $this->post(route('expenses.purchases.store'), $this->purchase(['receive_now' => '0']))->assertSessionHasNoErrors();
        $purchase = Expense::query()->where('kind', 'supplier')->sole();

        $this->assertFalse($purchase->isReceived());
        $this->assertSame($before, $this->stock($this->buns));
        $this->get(route('expenses.index', 'supplier'))->assertSee('Waiting for the goods')->assertSee('Activate');

        $this->post(route('expenses.purchases.receive', $purchase))->assertSessionHas('success');
        $this->assertSame($before + 60, $this->stock($this->buns));

        // Activating again does nothing.
        $this->post(route('expenses.purchases.receive', $purchase))->assertSessionHas('error');
        $this->assertSame($before + 60, $this->stock($this->buns));
    }

    public function test_costs_are_kept_when_asked(): void
    {
        $cost = $this->buns->cost_per_unit;
        $this->post(route('expenses.purchases.store'), $this->purchase(['update_costs' => '0', 'items' => [['inventory_item_id' => $this->buns->id, 'quantity' => '10', 'total' => '99']]]));

        $this->assertSame($cost, $this->buns->fresh()->cost_per_unit);
    }

    public function test_undo_lets_the_items_be_corrected_and_delete_takes_them_out_of_stock(): void
    {
        $before = $this->stock($this->buns);
        $this->post(route('expenses.purchases.store'), $this->purchase());
        $purchase = Expense::query()->where('kind', 'supplier')->sole();

        // Items of a received purchase can't change…
        $this->put(route('expenses.purchases.update', $purchase), $this->purchase(['items' => [['inventory_item_id' => $this->buns->id, 'quantity' => '70', 'total' => '63']]]))
            ->assertSessionHasErrors('items');

        // …until the stock entry is undone.
        $this->post(route('expenses.purchases.unreceive', $purchase))->assertRedirect(route('expenses.purchases.edit', $purchase));
        $this->assertSame($before, $this->stock($this->buns));

        $this->put(route('expenses.purchases.update', $purchase), $this->purchase(['items' => [['inventory_item_id' => $this->buns->id, 'quantity' => '70', 'total' => '63']]]))
            ->assertSessionHasNoErrors();
        $this->assertSame('63.00', $purchase->fresh()->amount);

        $this->post(route('expenses.purchases.receive', $purchase));
        $this->assertSame($before + 70, $this->stock($this->buns));

        $this->delete(route('expenses.purchases.destroy', $purchase))->assertSessionHas('success');
        $this->assertSame($before, $this->stock($this->buns));
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_a_purchase_needs_a_supplier_and_valid_items(): void
    {
        $this->post(route('expenses.purchases.store'), $this->purchase(['supplier_id' => '']))->assertSessionHasErrors('supplier_id');
        $this->post(route('expenses.purchases.store'), $this->purchase(['items' => []]))->assertSessionHasErrors('items');
        $this->post(route('expenses.purchases.store'), $this->purchase(['items' => [['inventory_item_id' => $this->buns->id, 'quantity' => '0', 'total' => '5']]]))
            ->assertSessionHasErrors('items.0.quantity');
        $this->post(route('expenses.purchases.store'), $this->purchase(['items' => [
            ['inventory_item_id' => $this->buns->id, 'quantity' => '1', 'total' => '1'],
            ['inventory_item_id' => $this->buns->id, 'quantity' => '2', 'total' => '2'],
        ]]))->assertSessionHasErrors('items.1.inventory_item_id');

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_purchases_are_stock_not_expenses_in_results(): void
    {
        $this->post(route('expenses.purchases.store'), $this->purchase());
        $this->post(route('expenses.store'), ['kind' => 'bill', 'spent_on' => today()->toDateString(), 'category' => 'electricity', 'amount' => '300']);

        $report = app(ProfitAndLossService::class)->report(today(), today());

        $this->assertSame(300.0, $report['expenses']);   // the power bill only
        $this->assertSame(54.0, $report['purchases']);   // the buns, shown apart
        $this->assertSame(-300.0, $report['net']);

        $this->get(route('reports.pnl', ['period' => 'today']))->assertOk()->assertSee('Supplier purchases');
    }

    public function test_suppliers_are_managed_and_protected_once_they_have_purchases(): void
    {
        $this->post(route('expenses.suppliers.store'), ['name' => 'Frigorífico Boi Bom', 'phone' => '11 99999-0000', 'items' => [$this->meat->id]])
            ->assertSessionHas('success');
        $meatSupplier = Supplier::query()->where('name', 'Frigorífico Boi Bom')->sole();
        $this->assertSame([$this->meat->id], $meatSupplier->items->pluck('id')->all());

        $this->post(route('expenses.suppliers.store'), ['name' => 'Padaria Pão Bom'])->assertSessionHasErrors('name');

        $this->put(route('expenses.suppliers.update', $meatSupplier), ['name' => 'Frigorífico Boi Bom', 'items' => [$this->meat->id, $this->buns->id]]);
        $this->assertCount(2, $meatSupplier->fresh()->items);

        $this->get(route('expenses.suppliers.index'))->assertOk()->assertSee('Frigorífico Boi Bom')->assertSee('Blend bovino');

        $this->post(route('expenses.purchases.store'), $this->purchase());
        $this->delete(route('expenses.suppliers.destroy', $this->bakery))->assertSessionHas('error');
        $this->delete(route('expenses.suppliers.destroy', $meatSupplier))->assertSessionHas('success');
        $this->assertSame(['Padaria Pão Bom'], Supplier::query()->pluck('name')->all());
    }
}
