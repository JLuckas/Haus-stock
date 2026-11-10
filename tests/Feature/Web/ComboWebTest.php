<?php

namespace Tests\Feature\Web;

use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboWebTest extends TestCase
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

    public function test_a_combo_can_be_made_of_products_only(): void
    {
        $this->post(route('products.store'), [
            'saipos_sku_id' => 'HAUS-BOX-DUO',
            'name' => 'Box Duo',
            'price' => '79.90',
            'stock_mode' => 'recipe',
            'ingredients' => [['inventory_item_id' => '', 'quantity_needed' => '']],
            'components' => [
                ['product_id' => $this->productId('HAUS-CHEESE'), 'quantity' => '2'],
                ['product_id' => $this->productId('HAUS-FRIES'), 'quantity' => '1'],
            ],
        ])->assertSessionHasNoErrors();

        $duo = Product::query()->where('saipos_sku_id', 'HAUS-BOX-DUO')->sole();
        $this->assertSame(0, $duo->ingredients()->count());
        $this->assertEquals(
            [$this->productId('HAUS-CHEESE') => 2.0, $this->productId('HAUS-FRIES') => 1.0],
            $duo->components->mapWithKeys(fn ($c) => [$c->id => (float) $c->pivot->quantity])->all(),
        );

        $this->get(route('products.edit', $duo))
            ->assertOk()
            ->assertSee('One unit deducts')
            ->assertSee('320 g Blend bovino')
            ->assertSee('150 g Batata congelada');

        $this->get(route('products.index'))->assertSee('Combo')->assertSee('2 × Cheeseburger');
    }

    public function test_a_product_cannot_contain_itself_or_a_combo_that_contains_it(): void
    {
        $cheese = Product::query()->where('saipos_sku_id', 'HAUS-CHEESE')->sole();
        $payload = fn (int $componentId): array => [
            'saipos_sku_id' => 'HAUS-CHEESE', 'name' => 'Cheeseburger', 'price' => '32', 'stock_mode' => 'recipe',
            'ingredients' => [['inventory_item_id' => InventoryItem::query()->where('name', 'Cheddar')->value('id'), 'quantity_needed' => '30']],
            'components' => [['product_id' => $componentId, 'quantity' => '1']],
        ];

        // Box Cheeseburger already contains Cheeseburger, so Cheeseburger can't contain the box.
        $this->put(route('products.update', $cheese), $payload($this->productId('HAUS-BOX-CHEESE')))
            ->assertSessionHasErrors('components.0.product_id');

        $this->put(route('products.update', $cheese), $payload($cheese->id))
            ->assertSessionHasErrors('components.0.product_id');

        $this->assertSame(0, $cheese->components()->count());

        // The edit form doesn't even offer them.
        $this->get(route('products.edit', $cheese))
            ->assertDontSee('<option value="'.$this->productId('HAUS-BOX-CHEESE').'">Box Cheeseburger</option>', escape: false);
    }

    public function test_a_recipe_needs_at_least_one_ingredient_or_product(): void
    {
        $this->post(route('products.store'), [
            'saipos_sku_id' => 'NOTHING', 'name' => 'Nothing', 'price' => '1', 'stock_mode' => 'recipe',
            'ingredients' => [], 'components' => [],
        ])->assertSessionHasErrors(['ingredients', 'components']);
    }

    public function test_a_product_inside_a_combo_cannot_be_deleted(): void
    {
        $fries = Product::query()->where('saipos_sku_id', 'HAUS-FRIES')->sole();

        $this->delete(route('products.destroy', $fries))
            ->assertSessionHas('error', '"Batata frita" is inside Box Cheeseburger. Remove it from there first.');

        $this->assertModelExists($fries);
    }

    public function test_a_product_can_be_deducted_from_the_web_form(): void
    {
        $this->get(route('manual-deductions.create', ['product' => $this->productId('HAUS-BOX-CHEESE')]))
            ->assertOk()
            ->assertSee('This will deduct');

        $this->post(route('manual-deductions.store'), [
            'target' => 'product',
            'product_id' => $this->productId('HAUS-BOX-CHEESE'),
            'inventory_item_id' => '',
            'quantity' => '1',
            'reason' => 'Employee meal',
        ])->assertRedirect(route('manual-deductions.index'))->assertSessionHasNoErrors();

        $deduction = ManualDeduction::query()->sole();
        $this->assertSame($this->user->id, $deduction->user_id);
        $this->assertSame($this->productId('HAUS-BOX-CHEESE'), $deduction->product_id);

        // Filtering the log by an ingredient finds the product deduction that used it.
        $potatoId = InventoryItem::query()->where('name', 'Batata congelada')->value('id');
        $this->get(route('manual-deductions.index', ['item' => $potatoId]))
            ->assertSee('Box Cheeseburger')
            ->assertSee('150 g Batata congelada');

        $this->get(route('dashboard'))->assertSee('Box Cheeseburger');
    }

    public function test_deducting_a_product_requires_choosing_one(): void
    {
        $this->post(route('manual-deductions.store'), ['target' => 'product', 'quantity' => '1', 'reason' => 'Waste'])
            ->assertSessionHasErrors('product_id');
    }

    private function productId(string $sku): int
    {
        return Product::query()->where('saipos_sku_id', $sku)->value('id');
    }
}
