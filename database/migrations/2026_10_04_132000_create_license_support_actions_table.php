<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_support_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('license_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('license_activation_id')->nullable()->constrained('license_activations')->nullOnDelete();
            $table->foreignUlid('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_support_actions');
    }
};
