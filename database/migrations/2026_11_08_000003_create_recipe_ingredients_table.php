<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            // Amount of the inventory item consumed per 1 unit of product sold, in the item's unit.
            $table->decimal('quantity_needed', 12, 3);
            $table->timestamps();

            // One BoM line per (product, ingredient) pair.
            $table->primary(['product_id', 'inventory_item_id']);
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
