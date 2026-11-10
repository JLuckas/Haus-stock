<?php

namespace Tests\Feature\Stock;

use App\Models\InventoryItem;
use App\Models\ManualDeduction;
use App\Services\Stock\ManualDeductionService;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ManualDeductionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ManualDeductionService $service;

    private InventoryItem $buns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StockDemoSeeder::class);
        $this->service = app(ManualDeductionService::class);
        $this->buns = InventoryItem::query()->where('name', 'Pão brioche')->sole();
    }

    public function test_it_deducts_stock_and_records_the_reason(): void
    {
        $deduction = $this->service->deduct($this->buns, 2, '  Employee Meal ');

        $this->assertSame('Employee Meal', $deduction->reason);
        $this->assertEquals(118, (float) $this->buns->fresh()->current_stock);
        $this->assertDatabaseHas('manual_deductions', [
            'inventory_item_id' => $this->buns->id,
            'reason' => 'Employee Meal',
        ]);
    }

    public function test_it_accepts_fractional_quantities_by_id(): void
    {
        $bacon = InventoryItem::query()->where('name', 'Bacon')->sole();

        $this->service->deduct($bacon->id, 12.345, 'Waste');

        $this->assertEqualsWithDelta(5000 - 12.345, (float) $bacon->fresh()->current_stock, 0.0005);
    }

    public function test_it_rejects_a_non_positive_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service->deduct($this->buns, 0, 'Employee Meal');
        } finally {
            $this->assertSame(0, ManualDeduction::query()->count());
            $this->assertEquals(120, (float) $this->buns->fresh()->current_stock);
        }
    }

    public function test_it_requires_a_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->deduct($this->buns, 1, '   ');
    }

    public function test_it_fails_for_an_unknown_item(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->deduct(999_999, 1, 'Employee Meal');
    }

    public function test_the_artisan_command_deducts_by_name(): void
    {
        $this->artisan('stock:deduct', ['target' => 'Pão brioche', 'quantity' => '2', 'reason' => 'Employee Meal'])
            ->expectsOutputToContain('Deducted 2 un "Pão brioche" (Employee Meal).')
            ->assertSuccessful();

        $this->assertSame(1, ManualDeduction::query()->count());
        $this->assertEquals(118, (float) $this->buns->fresh()->current_stock);
    }
}
