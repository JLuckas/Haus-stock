<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operating expenses (staff day payments and general costs like rent or bills), free-form tags
 * to classify them, a key/value settings table (target margin) and a per-product margin override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);                       // personnel | general
            $table->date('spent_on');
            $table->decimal('amount', 12, 2);
            $table->string('category', 30)->nullable();       // general: bills, rent, transport…
            $table->string('description')->nullable();
            $table->string('employee_name')->nullable();      // personnel only
            $table->string('employee_role', 20)->nullable();  // kitchen | delivery | cashier
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'spent_on']);
            $table->index('spent_on');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('slug', 40)->unique();             // lower-cased name: "Fixa" and "fixa" are one tag
            $table->string('color', 20);
            $table->timestamps();
        });

        Schema::create('expense_tag', function (Blueprint $table) {
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['expense_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            // Null = use the store's target margin (settings.target_margin_percent).
            $table->decimal('target_margin_percent', 5, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('target_margin_percent'));
        Schema::dropIfExists('settings');
        Schema::dropIfExists('expense_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('expenses');
    }
};
