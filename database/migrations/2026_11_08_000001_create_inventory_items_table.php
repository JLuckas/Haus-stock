<?php

use App\Enums\InventoryItemType;
use App\Enums\UnitOfMeasure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type', array_column(InventoryItemType::cases(), 'value'));
            $table->string('name')->unique();
            $table->enum('unit_of_measure', array_column(UnitOfMeasure::cases(), 'value'));
            // numeric(12,3): supports fractional stock (e.g. 12500.750 g) without float drift.
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
