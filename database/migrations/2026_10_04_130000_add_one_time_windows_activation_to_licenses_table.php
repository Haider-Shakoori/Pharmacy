<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->char('windows_activation_key_hash', 64)->nullable()->unique()->after('key_hint');
            $table->string('windows_activation_key_hint', 24)->nullable()->after('windows_activation_key_hash');
            $table->unsignedInteger('windows_activation_key_version')->default(1)->after('windows_activation_key_hint');
            $table->timestamp('windows_activation_key_generated_at')->nullable()->after('windows_activation_key_version');
            $table->timestamp('windows_activation_key_consumed_at')->nullable()->after('windows_activation_key_generated_at');
            $table->foreignUlid('windows_activation_id')
                ->nullable()
                ->after('windows_activation_key_consumed_at')
                ->constrained('license_activations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('windows_activation_id');
            $table->dropColumn([
                'windows_activation_key_hash',
                'windows_activation_key_hint',
                'windows_activation_key_version',
                'windows_activation_key_generated_at',
                'windows_activation_key_consumed_at',
            ]);
        });
    }
};
