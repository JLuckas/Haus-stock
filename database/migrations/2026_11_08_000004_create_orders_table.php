<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Idempotency key: the unique constraint is what prevents double deduction.
            $table->string('saipos_order_id')->unique();
            $table->string('status')->nullable();
            $table->boolean('is_stock_processed')->default(false);
            $table->timestamp('stock_processed_at')->nullable();
            // Last failure reason (e.g. unmapped SKU), kept so stuck orders are visible.
            $table->text('last_error')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->index('is_stock_processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
