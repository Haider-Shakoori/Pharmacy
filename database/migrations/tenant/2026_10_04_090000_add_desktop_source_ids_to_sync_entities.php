<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->string('desktop_source_id', 64)
                ->nullable()
                ->unique();
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('desktop_source_id', 64)
                ->nullable()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropUnique(['desktop_source_id']);
            $table->dropColumn('desktop_source_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['desktop_source_id']);
            $table->dropColumn('desktop_source_id');
        });
    }
};
