<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $iuranTime = (string) config('services.whatsapp.iuran_reminder.time', '08:00');
        $iuranTimezone = (string) config('services.whatsapp.iuran_reminder.timezone', 'Asia/Jakarta');

        $schedule->command('whatsapp:send-iuran-reminders')
            ->dailyAt($iuranTime)
            ->timezone($iuranTimezone)
            ->withoutOverlapping();

        $lombaTimezone = (string) config('services.whatsapp.lomba_reminder.timezone', 'Asia/Jakarta');
        $lombaTimeH0 = (string) config('services.whatsapp.lomba_reminder.time_h0', '07:00');
        $lombaTimeH1 = (string) config('services.whatsapp.lomba_reminder.time_h1', '08:00');

        $schedule->command('whatsapp:send-lomba-reminders --milestone=h_0')
            ->dailyAt($lombaTimeH0)
            ->timezone($lombaTimezone)
            ->withoutOverlapping();

        $schedule->command('whatsapp:send-lomba-reminders --milestone=h_minus_1')
            ->dailyAt($lombaTimeH1)
            ->timezone($lombaTimezone)
            ->withoutOverlapping();

        $matchTimezone = (string) config('services.whatsapp.match_invitation.timezone', 'Asia/Jakarta');

        $schedule->command('whatsapp:send-match-invitations')
            ->everyFiveMinutes()
            ->timezone($matchTimezone)
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
