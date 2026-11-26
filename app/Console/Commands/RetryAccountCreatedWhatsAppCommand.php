<?php

namespace App\Console\Commands;

use App\Services\AccountCreatedWhatsAppService;
use Illuminate\Console\Command;

class RetryAccountCreatedWhatsAppCommand extends Command
{
    protected $signature = 'whatsapp:retry-account-notifications
                            {--akun= : Coba ulang hanya untuk satu ID akun tertentu}
                            {--dry-run : Simulasi tanpa mengirim pesan WhatsApp}';

    protected $description = 'Mencoba ulang pengiriman WhatsApp informasi akun warga baru yang berstatus failed tanpa membuat ulang akun atau mengirim password.';

    public function handle(AccountCreatedWhatsAppService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $akunOption = $this->option('akun');

        $akunId = null;
        if ($akunOption !== null && $akunOption !== '') {
            if (!ctype_digit((string) $akunOption) || (int) $akunOption <= 0) {
                $this->error('Parameter --akun harus berupa angka ID positif.');

                return self::INVALID;
            }
            $akunId = (int) $akunOption;
        }

        $summary = $service->retryFailedNotifications($akunId, $dryRun);
        $modeLabel = $dryRun ? 'DRY-RUN' : 'LIVE';

        $this->info(sprintf(
            '[%s] Retry informasi akun WA | Failed Eligible: %d | Queued: %d | Would Retry: %d | Skipped: %d | Failed: %d | Stale Processing: %d',
            $modeLabel,
            $summary['eligible_failed'],
            $summary['queued'],
            $summary['would_retry'],
            $summary['skipped'],
            $summary['failed'],
            $summary['stale_processing']
        ));

        if ($summary['stale_processing'] > 0) {
            $this->warn('Terdapat log berstatus processing yang tidak di-retry otomatis. Periksa status pengiriman di dashboard Fonnte sebelum mengambil tindakan manual.');
        }

        return self::SUCCESS;
    }
}
