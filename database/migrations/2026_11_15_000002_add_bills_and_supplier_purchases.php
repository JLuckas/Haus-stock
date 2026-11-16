<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two new kinds of expense:
 *  - Bills (Contas): power, water, internet, rent, taxes… Existing general expenses in the bill
 *    categories (bills, rent, taxes) move here.
 *  - Supplier purchases: lines of inventory items; receiving the purchase adds them to stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('phone', 40)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // What each supplier usually sells, so a new purchase starts with those items.
        Schema::create('inventory_item_supplier', function (Blueprint $table) {
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->primary(['supplier_id', 'inventory_item_id']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('employee_role')->constrained('suppliers')->restrictOnDelete();
            // When the purchase was received and its items added to stock (null = not yet).
            $table->timestamp('received_at')->nullable()->after('supplier_id');
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);     // in the item's stock unit (g, ml or un)
            $table->decimal('total', 12, 2);        // what was paid for this line
            $table->index('inventory_item_id');
        });

        DB::table('expenses')
            ->where('kind', 'general')
            ->whereIn('category', ['bills', 'rent', 'taxes'])
            ->update(['kind' => 'bill']);
    }

    public function down(): void
    {
        DB::table('expenses')->where('kind', 'bill')->update(['kind' => 'general']);
        DB::table('expenses')->where('kind', 'supplier')->delete();

        Schema::dropIfExists('expense_items');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('received_at');
        });

        Schema::dropIfExists('inventory_item_supplier');
        Schema::dropIfExists('suppliers');
    }
};
