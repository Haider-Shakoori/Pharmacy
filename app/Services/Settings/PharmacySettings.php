<?php

namespace App\Services\Settings;

use App\Models\Tenant;

class PharmacySettings
{
    public const DEFAULTS = [
        'profile' => [
            'phone' => null,
            'address' => null,
            'receipt_footer' => null,
        ],
        'daily_closing' => [
            'business_day_rollover_time' => '00:00',
            'opening_cash_mode' => 'carry_forward',
            'require_counted_cash' => true,
            'variance_note_threshold' => 0,
            'allow_reopen' => true,
            'require_close_before_next_day' => true,
            'block_online_sales_after_close' => true,
            'warn_unsynced_devices_before_close' => true,
        ],
    ];

    public function all(Tenant $tenant): array
    {
        return array_replace_recursive(
            self::DEFAULTS,
            $tenant->settings ?? [],
        );
    }

    public function profile(Tenant $tenant): array
    {
        return $this->all($tenant)['profile'];
    }

    public function dailyClosing(Tenant $tenant): array
    {
        return $this->all($tenant)['daily_closing'];
    }

    public function persist(
        Tenant $tenant,
        array $profile,
        array $dailyClosing,
    ): void {
        $tenant->settings = array_replace_recursive(
            $tenant->settings ?? [],
            [
                'profile' => $profile,
                'daily_closing' => $dailyClosing,
            ],
        );

        $tenant->save();
    }
}
