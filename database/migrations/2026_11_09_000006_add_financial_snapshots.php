<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money snapshots taken when stock actually moves, so past profit and losses don't change
 * when ingredient costs are updated later.
 *
 *  - order_items: what each order sold, at what price and at what production cost.
 *  - orders.revenue / cost: order totals (revenue is net of the order discount).
 *  - manual_deductions.cost: what an employee meal, waste, etc. cost (a loss).
 *
 * `cost_is_complete` is false when some ingredient had no cost at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('saipos_sku_id')->nullable();
            $table->string('name');
            $table->boolean('is_add_on')->default(false);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->boolean('cost_is_complete')->default(true);

            $table->index('product_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->after('raw_payload');
            $table->decimal('revenue', 12, 2)->nullable()->after('discount');
            $table->decimal('cost', 14, 4)->nullable()->after('revenue');
            $table->boolean('cost_is_complete')->nullable()->after('cost');

            $table->index('stock_processed_at');
        });

        Schema::table('manual_deductions', function (Blueprint $table) {
            $table->decimal('cost', 14, 4)->nullable()->after('reason');
            $table->boolean('cost_is_complete')->nullable()->after('cost');
        });

        Schema::table('manual_deduction_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 6)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('manual_deduction_lines', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('manual_deductions', fn (Blueprint $table) => $table->dropColumn(['cost', 'cost_is_complete']));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['stock_processed_at']);
            $table->dropColumn(['discount', 'revenue', 'cost', 'cost_is_complete']);
        });
        Schema::dropIfExists('order_items');
    }
};
