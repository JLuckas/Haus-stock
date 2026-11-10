<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting an inventory item archives it (deleted_at) instead of removing the row, so
 * manual deductions that reference it keep showing what was taken.
 * The name stays unique among active items only, so a deleted name can be reused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropUnique(['name']);
        });

        // Partial unique index: supported by both PostgreSQL and SQLite.
        DB::statement('CREATE UNIQUE INDEX inventory_items_name_active_unique ON inventory_items (name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_items_name_active_unique');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('name');
        });
    }
};
