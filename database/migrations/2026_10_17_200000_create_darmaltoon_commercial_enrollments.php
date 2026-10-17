<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_launch_counters', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('confirmed_founders')->default(0);
            $table->timestamps();
        });

        Schema::create('commercial_enrollments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('business_id')->unique()->constrained()->restrictOnDelete();
            $table->string('offer_tier', 16);
            $table->unsignedTinyInteger('founder_number')->nullable()->unique();
            $table->decimal('first_year_amount', 14, 2);
            $table->decimal('renewal_amount', 14, 2);
            $table->char('currency', 3)->default('AFN');
            $table->foreignUlid('confirmed_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamp('first_paid_at');
            $table->timestamps();
        });

        Schema::create('commercial_payment_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('commercial_enrollments')->restrictOnDelete();
            $table->string('kind', 16); // initial or renewal
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('AFN');
            $table->string('payment_reference', 150)->unique();
            $table->timestamp('paid_at');
            $table->timestamp('coverage_ends_at');
            $table->foreignUlid('confirmed_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['enrollment_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_payment_receipts');
        Schema::dropIfExists('commercial_enrollments');
        Schema::dropIfExists('commercial_launch_counters');
    }
};
