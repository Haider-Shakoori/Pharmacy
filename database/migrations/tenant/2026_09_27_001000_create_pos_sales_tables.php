<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 180);
            $table->string('phone', 64)->nullable()->index();
            $table->string('email')->nullable();
            $table->decimal('credit_limit', 20, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('sale_number', 80)->unique();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date')->index();
            $table->string('status', 24)->default('held')->index();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('tax_total', 20, 4)->default(0);
            $table->decimal('grand_total', 20, 4)->default(0);
            $table->decimal('paid_total', 20, 4)->default(0);
            $table->decimal('due_total', 20, 4)->default(0);
            $table->decimal('change_total', 20, 4)->default(0);
            $table->string('payment_status', 32)->default('unpaid')->index();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['business_date', 'status']);
            $table->index(['created_by', 'business_date']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('medicine_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->string('sale_unit', 50);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4);
            $table->decimal('cost_total', 20, 4)->default(0);
            $table->boolean('prescription_required')->default(false);
            $table->timestamps();

            $table->index(['sale_id', 'medicine_id']);
        });

        Schema::create('sale_batch_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_batch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_movement_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 20, 4);
            $table->timestamps();

            $table->index(['sale_line_id', 'product_batch_id']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 80)->unique();
            $table->string('method', 32)->index();
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);
            $table->string('reference', 160)->nullable();
            $table->timestamp('paid_at')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_batch_allocations');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
    }
};
