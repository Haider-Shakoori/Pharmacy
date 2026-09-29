<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_windows_devices')
                ->nullable()
                ->after('max_android_devices');
        });

        DB::table('plans')
            ->where('code', 'TRIAL')
            ->whereNull('max_windows_devices')
            ->update(['max_windows_devices' => 1]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_windows_devices');
        });
    }
};
