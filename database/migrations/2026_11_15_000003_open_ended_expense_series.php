<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Open-ended series (staff day payments that repeat until stopped):
 *  - occurrences NULL = no set number of times;
 *  - generated_count = how many occurrences were created so far (new ones continue from here,
 *    so deleting one never makes it come back);
 *  - stopped_on = the day the series was stopped (nothing is created after it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_series', function (Blueprint $table) {
            $table->unsignedSmallInteger('occurrences')->nullable()->change();
            $table->unsignedInteger('generated_count')->default(0)->after('occurrences');
            $table->date('stopped_on')->nullable()->after('starts_on');
        });

        DB::table('expense_series')->update(['generated_count' => DB::raw('occurrences')]);
    }

    public function down(): void
    {
        DB::table('expense_series')->whereNull('occurrences')->update(['occurrences' => DB::raw('generated_count')]);

        Schema::table('expense_series', function (Blueprint $table) {
            $table->dropColumn(['generated_count', 'stopped_on']);
            $table->unsignedSmallInteger('occurrences')->nullable(false)->change();
        });
    }
};
