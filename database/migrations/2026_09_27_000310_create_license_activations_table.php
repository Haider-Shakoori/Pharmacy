<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('license_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->string('device_name')->nullable();
            $table->string('platform', 32)->default('android');
            $table->string('app_version', 40)->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['license_id', 'device_id']);
            $table->index(['license_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
