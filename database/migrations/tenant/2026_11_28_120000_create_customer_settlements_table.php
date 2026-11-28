<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_id')->constrained('sales');
            $table->string('method', 20);
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);
            $table->date('business_date');
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('created_by');
            $table->string('reference', 160)->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_settlements');
    }
};
