<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name', 180)->index();
            $table->string('contact_person', 160)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('whatsapp', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->unsignedInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable()->index();
            $table->char('currency', 3)->default('AFN');
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('landed_cost_total', 20, 4)->default(0);
            $table->decimal('grand_total', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'order_date']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('medicine_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('ordered_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('landed_cost_allocated', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4);
            $table->timestamps();

            $table->index(['purchase_order_id', 'medicine_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number', 80)->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->dateTime('received_at')->index();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('inventory_posted_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('bonus_quantity', 18, 4)->default(0);
            $table->string('batch_number', 120)->nullable()->index();
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('sale_price', 20, 4)->nullable();
            $table->timestamps();

            $table->index(['medicine_id', 'expires_at']);
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 80)->unique();
            $table->string('supplier_invoice_number', 120)->nullable();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->char('currency', 3)->default('AFN');
            $table->string('status', 32)->default('open')->index();
            $table->decimal('subtotal', 20, 4);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('landed_cost_total', 20, 4)->default(0);
            $table->decimal('grand_total', 20, 4);
            $table->decimal('paid_total', 20, 4)->default(0);
            $table->decimal('balance_due', 20, 4);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_invoice_number']);
            $table->index(['supplier_id', 'invoice_date']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 80)->unique();
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);
            $table->string('method', 32);
            $table->string('reference', 160)->nullable();
            $table->dateTime('paid_at')->index();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
