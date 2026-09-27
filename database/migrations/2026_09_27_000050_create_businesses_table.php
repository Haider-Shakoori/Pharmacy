<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('pharmacy_name', 160);
            $table->string('slug', 100)->unique();
            $table->string('contact_person', 160)->nullable();
            $table->string('phone_whatsapp', 64)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('owner_email');
            $table->string('billing_currency', 3)->default('AFN');
            $table->string('default_timezone', 64)->default('Asia/Kabul');
            $table->string('default_locale', 8)->default('en');
            $table->timestamps();

            $table->index(['pharmacy_name', 'owner_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
