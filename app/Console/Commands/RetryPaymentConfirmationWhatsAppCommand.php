<?php

namespace App\Console\Commands;

use App\Services\PaymentConfirmationWhatsAppService;
use Illuminate\Console\Command;

class RetryPaymentConfirmationWhatsAppCommand extends Command
{
    protected $signature = 'whatsapp:retry-payment-confirmations
                            {--pembayaran= : Coba ulang hanya untuk satu ID pembayaran tertentu}
                            {--dry-run : Simulasi tanpa mengirim pesan WhatsApp}';

    protected $description = 'Mencoba ulang pengiriman WhatsApp konfirmasi pembayaran yang berstatus failed tanpa mengulang verifikasi pembayaran.';

    public function handle(PaymentConfirmationWhatsAppService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pembayaranOption = $this->option('pembayaran');

        $pembayaranId = null;
        if ($pembayaranOption !== null && $pembayaranOption !== '') {
            if (!ctype_digit((string) $pembayaranOption) || (int) $pembayaranOption <= 0) {
                $this->error('Parameter --pembayaran harus berupa angka ID positif.');

                return self::INVALID;
            }
            $pembayaranId = (int) $pembayaranOption;
        }

        $summary = $service->retryFailedConfirmations($pembayaranId, $dryRun);
        $modeLabel = $dryRun ? 'DRY-RUN' : 'LIVE';

        $this->info(sprintf(
            '[%s] Retry konfirmasi pembayaran WA | Failed Eligible: %d | Queued: %d | Would Retry: %d | Skipped: %d | Failed: %d | Stale Processing: %d',
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
