<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring expenses. A series is created with all of its occurrences at once (each one an
 * ordinary `expenses` row with its own date), so nothing has to run in the background:
 * an occurrence counts in the Resultado when its date arrives, like any other expense.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_series', function (Blueprint $table) {
            $table->id();
            $table->string('frequency', 20);
            $table->unsignedSmallInteger('occurrences');
            $table->date('starts_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            // Deleting a series deletes its occurrences.
            $table->foreignId('series_id')->nullable()->after('id')->constrained('expense_series')->cascadeOnDelete();
            $table->unsignedSmallInteger('occurrence')->nullable()->after('series_id');
            $table->index(['series_id', 'occurrence']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['series_id', 'occurrence']);
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('occurrence');
        });

        Schema::dropIfExists('expense_series');
    }
};
