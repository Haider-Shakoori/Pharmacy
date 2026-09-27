<?php

use App\Enums\LicenseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('key_hash', 64)->unique();
            $table->string('key_hint', 24);
            $table->string('status', 24)->default(LicenseStatus::Active->value)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('generated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
