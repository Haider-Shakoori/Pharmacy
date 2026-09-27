<?php

namespace Tests\Unit\DailyClosing;

use App\Models\Tenant;
use App\Services\DailyClosing\BusinessDateResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDateResolverTest extends TestCase
{
    #[Test]
    public function transactions_before_rollover_belong_to_previous_business_date(): void
    {
        $tenant = new Tenant([
            'name' => 'Night Pharmacy',
            'slug' => 'night',
            'timezone' => 'Asia/Kabul',
            'settings' => [
                'daily_closing' => [
                    'business_day_rollover_time' => '03:00',
                ],
            ],
        ]);

        $resolver = app(BusinessDateResolver::class);

        $this->assertSame(
            '2026-09-27',
            $resolver->resolve($tenant, '2026-09-28 02:15:00 Asia/Kabul'),
        );

        $this->assertSame(
            '2026-09-28',
            $resolver->resolve($tenant, '2026-09-28 03:00:00 Asia/Kabul'),
        );
    }
}
