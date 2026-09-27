<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('return_number', 80)->unique();
            $table->foreignUlid('sale_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->string('status', 24)->default('completed')->index();
            $table->decimal('refund_total', 20, 4)->default(0);
            $table->string('idempotency_key', 191)->unique();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->index();
            $table->timestamps();

            $table->index(['sale_id', 'status']);
            $table->index(['stock_location_id', 'business_date']);
        });

        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_line_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('refund_amount', 20, 4);
            $table->string('disposition', 32)->default('restock');
            $table->timestamps();

            $table->index(['sale_line_id', 'sale_return_id']);
        });

        Schema::create('sale_return_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_return_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_batch_allocation_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_batch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->boolean('restocked')->default(false);
            $table->timestamps();

            $table->index(['sale_batch_allocation_id', 'sale_return_line_id']);
        });

        Schema::create('sale_return_refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_return_id')->constrained()->restrictOnDelete();
            $table->string('method', 32)->index();
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3)->default('AFN');
            $table->string('reference', 160)->nullable();
            $table->timestamps();

            $table->index(['sale_return_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_refunds');
        Schema::dropIfExists('sale_return_allocations');
        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');
    }
};
