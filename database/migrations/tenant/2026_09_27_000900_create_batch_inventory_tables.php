<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('kind', 40)->default('store');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignUlid('stock_location_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('stock_locations')
                ->restrictOnDelete();
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 120)->nullable()->index();
            $table->string('batch_key', 191);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->string('status', 32)->default('active')->index();
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('available_quantity', 18, 4)->default(0);
            $table->decimal('purchase_cost', 20, 4)->default(0);
            $table->decimal('sale_price', 20, 4)->nullable();
            $table->timestamp('last_movement_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['medicine_id', 'stock_location_id', 'batch_key']);
            $table->index(['medicine_id', 'status', 'expires_at']);
            $table->index(['stock_location_id', 'status', 'expires_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_batch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 48)->index();
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->decimal('unit_cost', 20, 4)->nullable();
            $table->string('source_type', 100);
            $table->string('source_id', 64);
            $table->string('source_line_id', 64)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['medicine_id', 'occurred_at']);
            $table->index(['product_batch_id', 'occurred_at']);
        });

        Schema::create('batch_status_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_batch_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason', 500);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->index();
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number', 80)->unique();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('reason_code', 48)->index();
            $table->string('status', 24)->default('draft')->index();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_delta', 18, 4);
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['inventory_adjustment_id', 'product_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('batch_status_events');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_batches');

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_location_id');
        });

        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('branches');
    }
};
