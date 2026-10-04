<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->timestamp('activation_key_consumed_at')->nullable()->after('generated_at')->index();
            $table->ulid('activation_key_consumed_by')->nullable()->after('activation_key_consumed_at');
            $table->unsignedInteger('activation_key_generation')->default(1)->after('version');
        });

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->unsignedInteger('session_version')->default(1)->after('build_number');
            $table->unsignedBigInteger('current_user_id')->nullable()->after('session_version');
            $table->string('current_user_name')->nullable()->after('current_user_id');
            $table->string('current_user_email')->nullable()->after('current_user_name');
            $table->string('last_ip_address', 45)->nullable()->after('current_user_email');
            $table->timestamp('session_started_at')->nullable()->after('last_ip_address');
            $table->timestamp('session_last_seen_at')->nullable()->after('session_started_at')->index();
        });

        Schema::create('license_support_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('license_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('activation_id')->nullable()->constrained('license_activations')->nullOnDelete();
            $table->foreignUlid('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

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
                'last_ip_address',
                'session_started_at',
                'session_last_seen_at',
            ]);
        });

        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropColumn([
                'activation_key_consumed_at',
                'activation_key_consumed_by',
                'activation_key_generation',
            ]);
        });
    }
};
