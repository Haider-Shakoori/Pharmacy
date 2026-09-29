<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('pharmacy_name', 160);
            $table->string('requested_slug', 100)->unique();
            $table->string('owner_name', 160);
            $table->string('owner_email')->index();
            $table->string('phone_whatsapp', 64);
            $table->string('location', 255);
            $table->string('preferred_locale', 8)->default('en');
            $table->text('notes')->nullable();
            $table->text('owner_password_ciphertext')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_requests');
    }
};
