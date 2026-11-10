<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Saipos "integration_code" of the sellable item. unique() also creates the index.
            $table->string('saipos_sku_id')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            // Direct link for products resold as-is (type = standalone_product).
            // When set, selling 1 product deducts exactly 1 unit of this item, bypassing the BoM.
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
