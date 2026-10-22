<?php

namespace App\Support\Commercial;

/**
 * The owner-approved Darmaltoon standard annual offer.
 *
 * This defines a commercial policy, not collected revenue. The initial and
 * renewal prices must be applied by an audited invoicing flow before launch.
 * Do not treat the plan's single price column as a renewal price.
 */
final class DarmaltoonStandardOffer
{
    public const PLAN_CODE = 'DARMALTOON_STANDARD_ANNUAL';

    public const FIRST_YEAR_AFN = '5000.00';

    public const RENEWAL_AFN = '3000.00';

    public const WINDOWS_DEVICES = 1;

    public const ANDROID_DEVICES = 2;

    public const BRANCHES = 1;

    /**
     * Attributes for a *new*, initially inactive, explicit opt-in plan.
     *
     * Offline lease grace is not the annual subscription duration.
     */
    public static function planAttributes(): array
    {
        return [
            'name' => 'Darmaltoon Standard (1 Windows + 2 Android)',
            'code' => self::PLAN_CODE,
            'description' => 'One pharmacy location, one Windows PC and two Android POS devices. AFN 5,000 initial annual term; AFN 3,000 annual renewal (handled separately by commercial billing).',
            'price' => self::FIRST_YEAR_AFN,
            'currency' => 'AFN',
            'billing_period' => 'yearly',
            'max_users' => null,
            'max_android_devices' => self::ANDROID_DEVICES,
            'max_windows_devices' => self::WINDOWS_DEVICES,
            'max_branches' => self::BRANCHES,
            'offline_grace_days' => (int) config('pharmacy.offline.default_license_grace_days', 7),
            'features' => [],
            // Do not offer this commercially until mixed-device sync and
            // first-year/renewal billing have passed their launch gates.
            'is_active' => false,
            'sort_order' => 10,
        ];
    }
}
