<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(['status', 'trial_ends_at'], 'subscriptions_trial_monitor_idx');
        });

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->index(
                ['revoked_at', 'last_seen_at'],
                'license_activations_monitor_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_trial_monitor_idx');
        });

        Schema::table('license_activations', function (Blueprint $table): void {
            $table->dropIndex('license_activations_monitor_idx');
        });
    }
};
