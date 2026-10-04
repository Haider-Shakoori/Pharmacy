<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->timestamp('windows_consumed_at')->nullable()->after('generated_at');
            $table->ulid('windows_activation_id')->nullable()->after('windows_consumed_at');
        });

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->unsignedInteger('session_version')->default(1)->after('last_seen_at');
            $table->string('current_user_id', 64)->nullable()->after('session_version');
            $table->string('current_user_name')->nullable()->after('current_user_id');
            $table->string('current_user_email')->nullable()->after('current_user_name');
            $table->timestamp('session_started_at')->nullable()->after('current_user_email');
            $table->timestamp('session_last_seen_at')->nullable()->after('session_started_at');
            $table->string('last_ip', 64)->nullable()->after('session_last_seen_at');
        });

        Schema::create('license_support_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('license_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('license_activation_id')->nullable()->constrained('license_activations')->nullOnDelete();
            $table->foreignUlid('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('action', 64);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_support_actions');

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->dropColumn([
                'session_version',
                'current_user_id',
                'current_user_name',
                'current_user_email',
                'session_started_at',
                'session_last_seen_at',
                'last_ip',
            ]);
        });

        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropColumn(['windows_consumed_at', 'windows_activation_id']);
        });
    }
};
