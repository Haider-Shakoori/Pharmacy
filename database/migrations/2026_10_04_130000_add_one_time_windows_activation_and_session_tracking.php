<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->timestamp('windows_consumed_at')->nullable()->after('generated_at');
            $table->ulid('windows_consumed_activation_id')->nullable()->after('windows_consumed_at');
        });

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->string('current_user_id', 64)->nullable()->after('build_number');
            $table->string('current_user_name', 160)->nullable()->after('current_user_id');
            $table->string('current_user_email')->nullable()->after('current_user_name');
            $table->unsignedInteger('session_version')->default(0)->after('current_user_email');
            $table->timestamp('session_started_at')->nullable()->after('session_version');
            $table->timestamp('session_last_seen_at')->nullable()->after('session_started_at');
            $table->timestamp('session_expires_at')->nullable()->after('session_last_seen_at');
            $table->timestamp('session_signed_out_at')->nullable()->after('session_expires_at');
            $table->string('last_ip_address', 45)->nullable()->after('session_signed_out_at');
            $table->text('last_user_agent')->nullable()->after('last_ip_address');
        });

        DB::table('licenses')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $license): void {
                $activation = DB::table('license_activations')
                    ->where('license_id', $license->id)
                    ->where('platform', 'windows')
                    ->whereNull('revoked_at')
                    ->latest('last_seen_at')
                    ->latest('activated_at')
                    ->first(['id', 'activated_at']);

                if ($activation !== null) {
                    DB::table('licenses')
                        ->where('id', $license->id)
                        ->update([
                            'windows_consumed_at' => $activation->activated_at ?? now(),
                            'windows_consumed_activation_id' => $activation->id,
                        ]);
                }
            });

        Schema::create('license_support_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('license_id')->index();
            $table->ulid('activation_id')->nullable()->index();
            $table->ulid('platform_admin_id')->nullable()->index();
            $table->string('action', 64)->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_support_actions');

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->dropColumn([
                'current_user_id',
                'current_user_name',
                'current_user_email',
                'session_version',
                'session_started_at',
                'session_last_seen_at',
                'session_expires_at',
                'session_signed_out_at',
                'last_ip_address',
                'last_user_agent',
            ]);
        });

        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropColumn([
                'windows_consumed_at',
                'windows_consumed_activation_id',
            ]);
        });
    }
};
