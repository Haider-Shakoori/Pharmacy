<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire-trials')->hourly();

Schedule::command('pharmacy:provisioning:retry --pending-only')->everyFiveMinutes()->withoutOverlapping();

if ((bool) config('backup.schedule_enabled', false)) {
    Schedule::command('pharmacy:backup:create')
        ->dailyAt((string) config('backup.schedule_time', '02:15'))
        ->withoutOverlapping();

    Schedule::command('pharmacy:backup:prune')
        ->dailyAt((string) config('backup.prune_time', '03:15'))
        ->withoutOverlapping();
}
