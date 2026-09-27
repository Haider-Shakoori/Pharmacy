<?php

use App\Enums\BillingPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->char('currency', 3)->default('AFN');
            $table->string('billing_period', 24)->default(BillingPeriod::Monthly->value);
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_android_devices')->nullable();
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('offline_grace_days')->default(7);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
