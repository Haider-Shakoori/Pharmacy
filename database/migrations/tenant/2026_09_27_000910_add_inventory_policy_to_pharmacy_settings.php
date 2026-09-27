<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_settings', function (Blueprint $table) {
            $table->json('inventory_policy')->nullable()->after('daily_closing');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_settings', function (Blueprint $table) {
            $table->dropColumn('inventory_policy');
        });
    }
};
