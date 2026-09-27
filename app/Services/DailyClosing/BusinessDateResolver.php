<?php

namespace App\Services\DailyClosing;

use App\Models\Tenant;
use App\Services\Settings\PharmacySettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class BusinessDateResolver
{
    public function __construct(
        private readonly PharmacySettings $settings,
    ) {}

    public function resolve(
        Tenant $tenant,
        DateTimeInterface|string|null $at = null,
    ): string {
        $local = $at === null
            ? CarbonImmutable::now($tenant->timezone)
            : CarbonImmutable::parse($at, $tenant->timezone)->setTimezone($tenant->timezone);

        [$hour, $minute] = array_map(
            'intval',
            explode(':', $this->settings->dailyClosing($tenant)['business_day_rollover_time']),
        );

        $rollover = $local->startOfDay()->setTime($hour, $minute);

        if ($local->lessThan($rollover)) {
            $local = $local->subDay();
        }

        return $local->toDateString();
    }
}
