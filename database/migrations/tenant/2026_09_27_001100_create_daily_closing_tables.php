<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('business_date')->index();
            $table->string('status', 24)->default('open')->index();
            $table->decimal('opening_cash', 20, 4)->default(0);
            $table->decimal('expected_cash', 20, 4)->nullable();
            $table->decimal('counted_cash', 20, 4)->nullable();
            $table->decimal('variance', 20, 4)->nullable();
            $table->timestamp('opened_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['stock_location_id', 'business_date', 'status']);
            $table->index(['user_id', 'business_date', 'status']);
        });

        Schema::create('daily_closings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->string('status', 24)->default('draft')->index();
            $table->decimal('gross_sales', 20, 4)->default(0);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('returns_total', 20, 4)->default(0);
            $table->decimal('cash_collected', 20, 4)->default(0);
            $table->decimal('bank_collected', 20, 4)->default(0);
            $table->decimal('mobile_collected', 20, 4)->default(0);
            $table->decimal('credit_sales', 20, 4)->default(0);
            $table->decimal('opening_cash', 20, 4)->default(0);
            $table->decimal('expected_cash', 20, 4)->default(0);
            $table->decimal('counted_cash', 20, 4)->nullable();
            $table->decimal('variance', 20, 4)->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('reopened_at')->nullable()->index();
            $table->text('closing_notes')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['stock_location_id', 'business_date']);
        });

        Schema::create('daily_closing_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('daily_closing_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closing_events');
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('cashier_shifts');
    }
};
