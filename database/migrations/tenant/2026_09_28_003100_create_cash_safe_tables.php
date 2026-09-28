<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_safes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_location_id', 'is_active']);
        });

        Schema::create('safe_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cash_safe_id')->constrained('cash_safes')->restrictOnDelete();
            $table->date('business_date')->index();
            $table->string('direction', 8)->index();
            $table->string('movement_type', 48)->index();
            $table->decimal('amount', 20, 4);
            $table->string('source_type', 120)->nullable()->index();
            $table->string('source_id', 64)->nullable()->index();
            $table->string('reference', 160)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['cash_safe_id', 'business_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('safe_closings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cash_safe_id')->constrained('cash_safes')->restrictOnDelete();
            $table->date('business_date')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->decimal('cash_in', 20, 4)->default(0);
            $table->decimal('cash_out', 20, 4)->default(0);
            $table->decimal('expected_balance', 20, 4)->default(0);
            $table->decimal('counted_balance', 20, 4)->nullable();
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

            $table->unique(['cash_safe_id', 'business_date']);
        });

        Schema::create('safe_closing_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('safe_closing_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_closing_events');
        Schema::dropIfExists('safe_closings');
        Schema::dropIfExists('safe_movements');
        Schema::dropIfExists('cash_safes');
    }
};
