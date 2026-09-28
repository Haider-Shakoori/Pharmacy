<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_activations', function (Blueprint $table) {
            $table->string('device_model', 160)->nullable()->after('device_name');
            $table->string('os_version', 80)->nullable()->after('platform');
            $table->string('build_number', 40)->nullable()->after('app_version');
        });
    }

    public function down(): void
    {
        Schema::table('license_activations', function (Blueprint $table) {
            $table->dropColumn(['device_model', 'os_version', 'build_number']);
        });
    }
};
