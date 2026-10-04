<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_user_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('license_activation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_name');
            $table->string('user_email')->index();
            $table->string('login_ip', 45)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['license_activation_id', 'revoked_at', 'last_seen_at'], 'desktop_sessions_activation_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_user_sessions');
    }
};
