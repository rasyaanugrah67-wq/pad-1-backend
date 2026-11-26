<?php

namespace App\Console\Commands;

use App\Services\MatchWatchInvitationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMatchInvitationsCommand extends Command
{
    protected $signature = 'whatsapp:send-match-invitations
                            {--dry-run : Simulasi tanpa mengirim WhatsApp atau menulis log}
                            {--pertandingan= : Jalankan hanya untuk satu ID pertandingan tertentu}
                            {--lomba= : Jalankan hanya untuk satu ID lomba tertentu}
                            {--warga= : Pengujian terarah untuk satu ID warga tertentu (tetap melewati validasi RW dan status)}
                            {--now= : Waktu acuan opsional (YYYY-MM-DD HH:MM, default waktu sekarang Asia/Jakarta)}';

    protected $description = 'Mengirim undangan menonton lomba via WhatsApp sekitar 30 menit sebelum pertandingan dimulai kepada seluruh warga aktif RW penyelenggara.';

    public function handle(MatchWatchInvitationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $pertandinganId = $this->parsePositiveIntOption('pertandingan');
        if ($pertandinganId === false) {
            return self::INVALID;
        }

        $lombaId = $this->parsePositiveIntOption('lomba');
        if ($lombaId === false) {
            return self::INVALID;
        }

        $wargaId = $this->parsePositiveIntOption('warga');
        if ($wargaId === false) {
            return self::INVALID;
        }

        $referenceTime = null;
        $nowOption = $this->option('now');
        if ($nowOption !== null && $nowOption !== '') {
            try {
                $timezone = (string) config('services.whatsapp.match_invitation.timezone', 'Asia/Jakarta');
                $referenceTime = Carbon::parse((string) $nowOption, $timezone);
            } catch (\Throwable) {
                $this->error('Format --now tidak valid. Gunakan format YYYY-MM-DD HH:MM.');

                return self::INVALID;
            }
        }

        $summary = $service->sendInvitations($referenceTime, $dryRun, $pertandinganId, $lombaId, $wargaId);

        if (!$summary['enabled']) {
            $this->warn('Undangan menonton lomba WhatsApp sedang dinonaktifkan (WHATSAPP_MATCH_INVITATION_ENABLED=false).');

            return self::SUCCESS;
        }

        if (!$summary['rw_found']) {
            $this->warn(sprintf('RW penyelenggara (%s) tidak ditemukan di database.', $summary['rw_number']));

            return self::SUCCESS;
        }

        $modeLabel = $dryRun ? 'DRY-RUN' : 'LIVE';
        $this->info(sprintf(
            '[%s] Undangan menonton lomba (%s | RW %s) | Matches: %d | Recipients: %d | Queued: %d | Would Send: %d | Already Sent: %d | Max Updates Blocked: %d | Skipped: %d | Failed: %d',
            $modeLabel,
            $summary['reference_time'],
            $summary['rw_number'],
            $summary['matches_eligible'],
            $summary['recipients_eligible'],
            $summary['queued'],
            $summary['would_send'],
            $summary['already_sent'],
            $summary['max_updates_blocked'],
            $summary['skipped'],
            $summary['failed']
        ));

        return self::SUCCESS;
    }

    private function parsePositiveIntOption(string $name): int|null|false
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }

        if (!ctype_digit((string) $value) || (int) $value <= 0) {
            $this->error("Parameter --{$name} harus berupa angka ID positif.");

            return false;
        }

        return (int) $value;
    }
}
