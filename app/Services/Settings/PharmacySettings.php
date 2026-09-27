<?php

namespace App\Services\Settings;

use App\Models\PharmacySetting;
use App\Models\Tenant;
use Closure;

class PharmacySettings
{
    public const DAILY_CLOSING_DEFAULTS = [
        'business_day_rollover_time' => '00:00',
        'opening_cash_mode' => 'carry_forward',
        'require_counted_cash' => true,
        'variance_note_threshold' => 0,
        'allow_reopen' => true,
        'require_close_before_next_day' => true,
        'block_online_sales_after_close' => true,
        'warn_unsynced_devices_before_close' => true,
    ];

    public function record(Tenant $tenant): PharmacySetting
    {
        return $this->inside($tenant, function () use ($tenant): PharmacySetting {
            $business = $tenant->business;

            return PharmacySetting::query()->firstOrCreate(
                ['id' => 1],
                [
                    'display_name' => $business?->pharmacy_name,
                    'timezone' => $business?->default_timezone ?? 'Asia/Kabul',
                    'locale' => $business?->default_locale ?? 'en',
                    'currency' => $business?->billing_currency ?? 'AFN',
                    'daily_closing' => self::DAILY_CLOSING_DEFAULTS,
                ],
            );
        });
    }

    public function profile(Tenant $tenant): array
    {
        $record = $this->record($tenant);

        return [
            'name' => $record->display_name ?? $tenant->name,
            'phone' => $record->phone,
            'address' => $record->address,
            'receipt_footer' => $record->receipt_footer,
            'timezone' => $record->timezone,
            'locale' => $record->locale,
            'currency' => $record->currency,
        ];
    }

    public function dailyClosing(Tenant $tenant): array
    {
        $settings = array_replace(
            self::DAILY_CLOSING_DEFAULTS,
            $this->record($tenant)->daily_closing ?? [],
        );
        $settings['variance_note_threshold'] = (float) $settings['variance_note_threshold'];

        return $settings;
    }

    public function timezone(Tenant $tenant): string
    {
        return $this->record($tenant)->timezone;
    }

    public function locale(Tenant $tenant): string
    {
        return $this->record($tenant)->locale;
    }

    public function persist(Tenant $tenant, array $profile, array $dailyClosing): void
    {
        $this->inside($tenant, function () use ($tenant, $profile, $dailyClosing): void {
            $record = $this->record($tenant);
            $record->fill([
                'display_name' => $profile['name'],
                'phone' => $profile['phone'],
                'address' => $profile['address'],
                'receipt_footer' => $profile['receipt_footer'],
                'timezone' => $profile['timezone'],
                'locale' => $profile['locale'],
                'daily_closing' => array_replace(self::DAILY_CLOSING_DEFAULTS, $dailyClosing),
            ])->save();
        });
    }

    private function inside(Tenant $tenant, Closure $callback): mixed
    {
        if (tenancy()->initialized && (string) tenant('id') === (string) $tenant->getTenantKey()) {
            return $callback();
        }

        return $tenant->run($callback);
    }
}
