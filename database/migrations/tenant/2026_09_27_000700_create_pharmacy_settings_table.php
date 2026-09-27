<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_settings', function (Blueprint $table) {
            $table->id();
            $table->string('display_name', 160)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('receipt_footer', 500)->nullable();
            $table->string('timezone', 64)->default('Asia/Kabul');
            $table->string('locale', 8)->default('en');
            $table->char('currency', 3)->default('AFN');
            $table->json('daily_closing')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_settings');
    }
};
