<?php

namespace App\Support\Commercial;

final class DarmaltoonLaunchPricing
{
    public const PLAN_CODE = 'DARMALTOON_STANDARD_ANNUAL';

    public const FOUNDING_LIMIT = 10;

    public const FOUNDING_FIRST_YEAR = '5000.00';

    public const REGULAR_FIRST_YEAR = '7000.00';

    public const ANNUAL_RENEWAL = '5000.00';

    public const CURRENCY = 'AFN';

    public static function firstYearPrice(int $confirmedFounders): string
    {
        return $confirmedFounders < self::FOUNDING_LIMIT
            ? self::FOUNDING_FIRST_YEAR
            : self::REGULAR_FIRST_YEAR;
    }

    public static function normaliseAmount(string $amount): string
    {
        // Avoid float arithmetic for monetary comparisons.
        if (! preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/', $amount)) {
            return '';
        }

        [$whole, $cents] = array_pad(explode('.', $amount, 2), 2, '');

        return $whole.'.'.str_pad($cents, 2, '0');
    }
}
