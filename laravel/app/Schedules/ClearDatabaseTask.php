<?php

namespace App\Schedules;

use Illuminate\Console\Scheduling\Schedule;

class ClearDatabaseTask extends Schedule
{
    public function __invoke(Schedule $schedule): void
    {
        $schedule->command('app:clear-database')->hourly();
    }
}
