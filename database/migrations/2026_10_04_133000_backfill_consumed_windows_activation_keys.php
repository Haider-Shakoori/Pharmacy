<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('licenses')
            ->select('id')
            ->whereNull('windows_activation_key_consumed_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $license): void {
                $activation = DB::table('license_activations')
                    ->select(['id', 'activated_at'])
                    ->where('license_id', $license->id)
                    ->where('platform', 'windows')
                    ->whereNull('revoked_at')
                    ->orderByDesc('activated_at')
                    ->first();

                if ($activation === null) {
                    return;
                }

                DB::table('licenses')
                    ->where('id', $license->id)
                    ->update([
                        'windows_activation_key_consumed_at' => $activation->activated_at ?? now(),
                        'windows_activation_id' => $activation->id,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('licenses')
            ->whereNull('windows_activation_key_hash')
            ->update([
                'windows_activation_key_consumed_at' => null,
                'windows_activation_id' => null,
                'updated_at' => now(),
            ]);
    }
};
