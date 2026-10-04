<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activation_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('license_id')->constrained()->cascadeOnDelete();
            $table->char('key_hash', 64)->unique();
            $table->string('key_hint', 28);
            $table->string('platform', 32)->default('windows')->index();
            $table->foreignUlid('issued_by_platform_admin_id')->nullable()
                ->constrained('platform_admins')->nullOnDelete();
            $table->foreignUlid('consumed_activation_id')->nullable()
                ->constrained('license_activations')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['license_id', 'platform', 'consumed_at', 'revoked_at'], 'license_activation_codes_availability_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activation_codes');
    }
};
