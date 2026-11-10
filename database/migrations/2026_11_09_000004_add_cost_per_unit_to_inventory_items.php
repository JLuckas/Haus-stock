<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase cost per stored unit (per g, per ml or per un), used to price recipes.
 * 6 decimals because per-gram costs are tiny: R$ 45,90/kg = R$ 0,0459/g.
 * Nullable on purpose: "unknown" must not be mistaken for "free".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 14, 6)->nullable()->after('minimum_stock');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('cost_per_unit');
        });
    }
};
