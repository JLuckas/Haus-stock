<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products inside products (nested Bill of Materials), e.g. "Box Cheeseburger" contains
 * 1 × "Cheeseburger" + 1 × "Fries". Deducting the box deducts the burger's own recipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_components', function (Blueprint $table) {
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            // restrict: a product can't be deleted while a combo still contains it.
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            // How many units of the component one unit of the parent contains.
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->primary(['parent_product_id', 'component_product_id']);
            $table->index('component_product_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_components ADD CONSTRAINT product_components_not_self CHECK (parent_product_id <> component_product_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
