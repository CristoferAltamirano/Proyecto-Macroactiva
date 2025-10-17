<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Mensual: día 1 a las 02:00 (genera cobros, intereses, avisos y respaldo)
        $schedule->command('macroactiva:monthly')
            ->monthlyOn(1, '02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/schedule_monthly.log'));

        // Respaldo diario a las 03:00 (por seguridad adicional)
        $schedule->call(function () {
            \App\Services\BackupService::dump();
        })->dailyAt('03:00')
          ->withoutOverlapping()
          ->onOneServer()
          ->appendOutputTo(storage_path('logs/schedule_backup.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
