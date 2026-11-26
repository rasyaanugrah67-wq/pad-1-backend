<?php

namespace App\Console\Commands;

use App\Services\IuranReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendIuranRemindersCommand extends Command
{
    protected $signature = 'whatsapp:send-iuran-reminders
                            {--dry-run : Simulasi tanpa mengirim pesan WhatsApp atau mencatat log sebagai queued}
                            {--iuran= : Jalankan hanya untuk satu ID iuran tertentu}
                            {--date= : Tanggal acuan opsional (YYYY-MM-DD, default hari ini Asia/Jakarta)}';

    protected $description = 'Mengirim pengingat WhatsApp untuk iuran belum dibayar pada milestone H-3, H, dan H+7.';

    public function handle(IuranReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $iuranOption = $this->option('iuran');
        $dateOption = $this->option('date');

        $iuranId = null;
        if ($iuranOption !== null && $iuranOption !== '') {
            if (!ctype_digit((string) $iuranOption) || (int) $iuranOption <= 0) {
                $this->error('Parameter --iuran harus berupa angka ID positif.');

                return self::INVALID;
            }
            $iuranId = (int) $iuranOption;
        }

        $referenceDate = null;
        if ($dateOption !== null && $dateOption !== '') {
            try {
                $timezone = (string) config('services.whatsapp.iuran_reminder.timezone', 'Asia/Jakarta');
                $referenceDate = Carbon::createFromFormat('Y-m-d', (string) $dateOption, $timezone)->startOfDay();
            } catch (\Throwable) {
                $this->error('Format --date tidak valid. Gunakan format YYYY-MM-DD.');

                return self::INVALID;
            }
        }

        $summary = $service->sendReminders($referenceDate, $dryRun, $iuranId);

        if (!$summary['enabled']) {
            $this->warn('Pengingat iuran WhatsApp sedang dinonaktifkan (WHATSAPP_IURAN_REMINDER_ENABLED=false).');

            return self::SUCCESS;
        }

        $modeLabel = $dryRun ? 'DRY-RUN' : 'LIVE';
        $this->info(sprintf(
            '[%s] Pengingat iuran (%s) | Eligible: %d | Queued: %d | Would Send: %d | Already Sent: %d | Skipped: %d | Failed: %d',
            $modeLabel,
            $summary['reference_date'],
            $summary['eligible'],
            $summary['queued'],
            $summary['would_send'],
            $summary['already_sent'],
            $summary['skipped'],
            $summary['failed']
        ));

        return self::SUCCESS;
    }
}
