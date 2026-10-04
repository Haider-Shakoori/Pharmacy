<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_activations', function (Blueprint $table): void {
            $table->unsignedInteger('license_version')
                ->nullable()
                ->after('license_id')
                ->index();
        });

        DB::table('license_activations')
            ->orderBy('id')
            ->get(['id', 'license_id'])
            ->each(function (object $activation): void {
                $version = DB::table('licenses')
                    ->where('id', $activation->license_id)
                    ->value('version');

                if ($version !== null) {
                    DB::table('license_activations')
                        ->where('id', $activation->id)
                        ->update(['license_version' => (int) $version]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('license_activations', function (Blueprint $table): void {
            $table->dropIndex(['license_version']);
            $table->dropColumn('license_version');
        });
    }
};
