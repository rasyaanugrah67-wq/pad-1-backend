<?php

namespace App\Console\Commands;

use App\Services\LombaParticipantReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendLombaRemindersCommand extends Command
{
    protected $signature = 'whatsapp:send-lomba-reminders
                            {--dry-run : Simulasi tanpa mengirim WhatsApp atau mencatat log sebagai queued}
                            {--pendaftaran= : Jalankan hanya untuk satu ID pendaftaran tertentu}
                            {--lomba= : Jalankan hanya untuk satu ID lomba tertentu}
                            {--date= : Tanggal acuan opsional (YYYY-MM-DD, default hari ini Asia/Jakarta)}
                            {--milestone= : Filter milestone opsional (h_minus_1 atau h_0)}';

    protected $description = 'Mengirim pengingat WhatsApp kepada peserta lomba berstatus Diterima pada milestone H-1 dan H.';

    public function handle(LombaParticipantReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pendaftaranOption = $this->option('pendaftaran');
        $lombaOption = $this->option('lomba');
        $dateOption = $this->option('date');
        $milestoneOption = $this->option('milestone');

        $pendaftaranId = null;
        if ($pendaftaranOption !== null && $pendaftaranOption !== '') {
            if (!ctype_digit((string) $pendaftaranOption) || (int) $pendaftaranOption <= 0) {
                $this->error('Parameter --pendaftaran harus berupa angka ID positif.');

                return self::INVALID;
            }
            $pendaftaranId = (int) $pendaftaranOption;
        }

        $lombaId = null;
        if ($lombaOption !== null && $lombaOption !== '') {
            if (!ctype_digit((string) $lombaOption) || (int) $lombaOption <= 0) {
                $this->error('Parameter --lomba harus berupa angka ID positif.');

                return self::INVALID;
            }
            $lombaId = (int) $lombaOption;
        }

        $milestoneFilter = null;
        if ($milestoneOption !== null && $milestoneOption !== '') {
            $validMilestones = [
                LombaParticipantReminderService::MILESTONE_H_MINUS_1,
                LombaParticipantReminderService::MILESTONE_H_0,
            ];
            if (!in_array((string) $milestoneOption, $validMilestones, true)) {
                $this->error('Parameter --milestone hanya boleh bernilai h_minus_1 atau h_0.');

                return self::INVALID;
            }
            $milestoneFilter = (string) $milestoneOption;
        }

        $referenceDateTime = null;
        if ($dateOption !== null && $dateOption !== '') {
            try {
                $timezone = (string) config('services.whatsapp.lomba_reminder.timezone', 'Asia/Jakarta');
                $referenceDateTime = Carbon::createFromFormat('Y-m-d', (string) $dateOption, $timezone)->startOfDay();
            } catch (\Throwable) {
                $this->error('Format --date tidak valid. Gunakan format YYYY-MM-DD.');

                return self::INVALID;
            }
        }

        $summary = $service->sendReminders($referenceDateTime, $dryRun, $pendaftaranId, $lombaId, $milestoneFilter);

        if (!$summary['enabled']) {
            $this->warn('Pengingat peserta lomba WhatsApp sedang dinonaktifkan (WHATSAPP_LOMBA_REMINDER_ENABLED=false).');

            return self::SUCCESS;
        }

        $modeLabel = $dryRun ? 'DRY-RUN' : 'LIVE';
        $this->info(sprintf(
            '[%s] Pengingat peserta lomba (%s) | Eligible: %d | Queued: %d | Would Send: %d | Already Sent: %d | Skipped: %d | Failed: %d',
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
