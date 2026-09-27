<?php

use App\Enums\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 24)->default(TenantStatus::Active->value)->index();
            $table->string('timezone', 64)->default('Asia/Kabul');
            $table->char('currency', 3)->default('AFN');
            $table->string('locale', 8)->default('en');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
