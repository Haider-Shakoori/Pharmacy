<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_activations', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_user_id')->nullable()->after('build_number');
            $table->string('current_user_name')->nullable()->after('current_user_id');
            $table->string('current_user_email')->nullable()->after('current_user_name');
            $table->unsignedInteger('session_version')->default(1)->after('current_user_email');
            $table->timestamp('session_issued_at')->nullable()->after('session_version');
            $table->timestamp('session_expires_at')->nullable()->after('session_issued_at');
            $table->timestamp('last_session_activity_at')->nullable()->after('session_expires_at');
            $table->string('last_ip_address', 45)->nullable()->after('last_session_activity_at');
            $table->text('last_user_agent')->nullable()->after('last_ip_address');

            $table->index(['license_id', 'platform', 'last_seen_at']);
            $table->index(['license_id', 'current_user_email']);
        });
    }

    public function down(): void
    {
        Schema::table('license_activations', function (Blueprint $table): void {
            $table->dropIndex(['license_id', 'platform', 'last_seen_at']);
            $table->dropIndex(['license_id', 'current_user_email']);
            $table->dropColumn([
                'current_user_id',
                'current_user_name',
                'current_user_email',
                'session_version',
                'session_issued_at',
                'session_expires_at',
                'last_session_activity_at',
                'last_ip_address',
                'last_user_agent',
            ]);
        });
    }
};
