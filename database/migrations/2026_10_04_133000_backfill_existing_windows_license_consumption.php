<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('licenses')
            ->whereNull('windows_activation_key_hash')
            ->orderBy('id')
            ->get([
                'id',
                'key_hash',
                'key_hint',
                'generated_at',
            ])
            ->each(function (object $license): void {
                DB::table('licenses')
                    ->where('id', $license->id)
                    ->update([
                        'windows_activation_key_hash' => $license->key_hash,
                        'windows_activation_key_hint' => $license->key_hint,
                        'windows_activation_key_generated_at' => $license->generated_at,
                    ]);
            });

        DB::table('licenses')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $license): void {
                $firstWindowsActivation = DB::table('license_activations')
                    ->where('license_id', $license->id)
                    ->where('platform', 'windows')
                    ->orderBy('activated_at')
                    ->orderBy('id')
                    ->first([
                        'id',
                        'activated_at',
                    ]);

                if ($firstWindowsActivation === null) {
                    return;
                }

                DB::table('licenses')
                    ->where('id', $license->id)
                    ->whereNull('windows_activation_key_consumed_at')
                    ->update([
                        'windows_activation_key_consumed_at' => $firstWindowsActivation->activated_at ?? now(),
                        'windows_activation_id' => $firstWindowsActivation->id,
                    ]);
            });
    }

    public function down(): void
    {
        // This is an irreversible safety backfill. Once an existing Windows key
        // has been identified as consumed, rolling that state back would make a
        // previously used activation key reusable.
    }
};
