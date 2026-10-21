<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['medicines', 'customers'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('desktop_last_modified_at')->nullable();
                $table->timestamp('desktop_last_sync_server_at')->nullable();
                $table->string('desktop_last_sync_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['medicines', 'customers'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['desktop_last_modified_at', 'desktop_last_sync_server_at', 'desktop_last_sync_hash']);
            });
        }
    }
};
