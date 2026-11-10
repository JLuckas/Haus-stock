<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A manual deduction now targets either one inventory item or one product.
 * `manual_deduction_lines` records what actually left stock, so a product deduction
 * (e.g. 1 × Box for an employee meal) keeps its full ingredient breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_deductions', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_item_id')->nullable()->change();
            $table->foreignId('product_id')->nullable()->after('inventory_item_id')
                ->constrained('products')->restrictOnDelete();
        });

        Schema::create('manual_deduction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_deduction_id')->constrained('manual_deductions')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);

            $table->unique(['manual_deduction_id', 'inventory_item_id']);
            $table->index('inventory_item_id');
        });

        // Every existing deduction targeted a single item: give each one its line.
        DB::table('manual_deduction_lines')->insertUsing(
            ['manual_deduction_id', 'inventory_item_id', 'quantity'],
            DB::table('manual_deductions')
                ->whereNotNull('inventory_item_id')
                ->select('id', 'inventory_item_id', 'quantity_deducted'),
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE manual_deductions ADD CONSTRAINT manual_deductions_one_target CHECK ((inventory_item_id IS NULL) <> (product_id IS NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE manual_deductions DROP CONSTRAINT IF EXISTS manual_deductions_one_target');
        }

        Schema::dropIfExists('manual_deduction_lines');

        // Product deductions can't be represented in the old single-item shape.
        DB::table('manual_deductions')->whereNull('inventory_item_id')->delete();

        Schema::table('manual_deductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->unsignedBigInteger('inventory_item_id')->nullable(false)->change();
        });
    }
};
