<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders can now be typed in by staff ("manual") besides being imported from Saipos.
 * Existing rows are Saipos orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('saipos')->after('saipos_order_id');
            $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('created_by');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['source', 'notes']);
        });
    }
};
