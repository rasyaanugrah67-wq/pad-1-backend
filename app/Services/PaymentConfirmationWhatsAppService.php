<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\WhatsappNotificationLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentConfirmationWhatsAppService
{
    public const TRIGGER_TYPE = 'payment_confirmed';

    public function __construct(
        private readonly WhatsAppNotificationService $whatsApp
    ) {
    }

    public function buildDedupKey(int $idPembayaran): string
    {
        return sprintf('%s:%d', self::TRIGGER_TYPE, $idPembayaran);
    }

    /**
     * Mengirim konfirmasi WhatsApp untuk pembayaran yang sudah diverifikasi Diterima.
     * Harus dipanggil setelah transaksi DB verifikasi selesai commit.
     *
     * @return array{
     *     action: string,
     *     reason: string|null,
     *     id_pembayaran: int
     * }
     */
    public function sendForApprovedPayment(Pembayaran $pembayaran): array
    {
        $pembayaran->refresh()->load([
            'iuran.warga:id_warga,nama,no_hp,status',
            'iuran.tagihan:id_tagihan,nama_tagihan',
            'iuran.periode:id_periode,tahun',
        ]);

        $iuran = $pembayaran->iuran;
        $warga = $iuran?->warga;

        if ($pembayaran->status_verifikasi !== 'Diterima' || !$iuran || $iuran->status !== 'Sudah Bayar') {
            return [
                'action' => 'ignored',
                'reason' => 'payment_not_approved',
                'id_pembayaran' => (int) $pembayaran->id_pembayaran,
            ];
        }

        if (!$warga || $warga->status !== 'Aktif' || trim((string) $warga->no_hp) === '') {
            return [
                'action' => 'ignored',
                'reason' => 'warga_ineligible',
                'id_pembayaran' => (int) $pembayaran->id_pembayaran,
            ];
        }

        $dedupKey = $this->buildDedupKey((int) $pembayaran->id_pembayaran);
        $claim = $this->claimLog($pembayaran, $warga->id_warga, $dedupKey);

        if ($claim['log'] === null) {
            return [
                'action' => 'already_handled',
                'reason' => $claim['reason'],
                'id_pembayaran' => (int) $pembayaran->id_pembayaran,
            ];
        }

        /** @var WhatsappNotificationLog $log */
        $log = $claim['log'];

        try {
            $verifiedAt = $pembayaran->tanggal_verifikasi
                ? $pembayaran->tanggal_verifikasi->copy()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB'
                : null;

            $result = $this->whatsApp->sendPaymentConfirmationWithResult(
                (string) $warga->no_hp,
                (string) $warga->nama,
                (float) $pembayaran->nominal,
                $iuran->nama_iuran,
                $verifiedAt
            );

            $state = match ($result['state']) {
                WhatsAppNotificationService::STATE_QUEUED => WhatsappNotificationLog::STATE_QUEUED,
                WhatsAppNotificationService::STATE_SKIPPED => WhatsappNotificationLog::STATE_SKIPPED,
                default => WhatsappNotificationLog::STATE_FAILED,
            };

            $log->update([
                'delivery_state' => $state,
                'is_test_mode' => (bool) ($result['is_test_mode'] ?? false),
                'provider_process' => $result['provider_process'] ?? null,
                'provider_detail' => $result['provider_detail'] ?? null,
                'error_reason' => $result['reason'] ?? null,
                'sent_at' => $state === WhatsappNotificationLog::STATE_QUEUED ? now() : null,
            ]);

            return [
                'action' => $state,
                'reason' => $result['reason'] ?? null,
                'id_pembayaran' => (int) $pembayaran->id_pembayaran,
            ];
        } catch (Throwable $exception) {
            $log->update([
                'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
                'error_reason' => 'unexpected_exception',
            ]);

            Log::error('Unexpected error while dispatching WhatsApp payment confirmation.', [
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'exception' => $exception::class,
            ]);

            return [
                'action' => WhatsappNotificationLog::STATE_FAILED,
                'reason' => 'unexpected_exception',
                'id_pembayaran' => (int) $pembayaran->id_pembayaran,
            ];
        }
    }

    /**
     * Mencoba ulang pengiriman konfirmasi WhatsApp HANYA untuk log berstatus failed,
     * tanpa mengulang atau mengubah data verifikasi pembayaran.
     *
     * Log berstatus processing yang tertinggal TIDAK di-retry otomatis karena ada
     * risiko request sebelumnya sebenarnya sudah diterima Fonnte.
     *
     * @return array{
     *     dry_run: bool,
     *     eligible_failed: int,
     *     queued: int,
     *     skipped: int,
     *     failed: int,
     *     ignored: int,
     *     would_retry: int,
     *     stale_processing: int
     * }
     */
    public function retryFailedConfirmations(?int $pembayaranId = null, bool $dryRun = false): array
    {
        $staleProcessingCount = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
            ->where('delivery_state', WhatsappNotificationLog::STATE_PROCESSING)
            ->when($pembayaranId !== null, fn ($q) => $q->where('reference_id', $pembayaranId))
            ->count();

        $failedLogs = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
            ->where('delivery_state', WhatsappNotificationLog::STATE_FAILED)
            ->when($pembayaranId !== null, fn ($q) => $q->where('reference_id', $pembayaranId))
            ->orderBy('id')
            ->get();

        $summary = [
            'dry_run' => $dryRun,
            'eligible_failed' => $failedLogs->count(),
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'ignored' => 0,
            'would_retry' => 0,
            'stale_processing' => $staleProcessingCount,
        ];

        foreach ($failedLogs as $failedLog) {
            if ($dryRun) {
                $summary['would_retry']++;
                continue;
            }

            $pembayaran = Pembayaran::find($failedLog->reference_id);

            if (!$pembayaran) {
                $summary['ignored']++;
                continue;
            }

            $res = $this->sendForApprovedPayment($pembayaran);
            $action = $res['action'];

            if (isset($summary[$action])) {
                $summary[$action]++;
            } else {
                $summary['ignored']++;
            }
        }

        return $summary;
    }

    /**
     * Klaim atomik dedup_key untuk mencegah pengiriman ganda.
     * Status processing yang tertinggal tidak di-retry otomatis demi mencegah duplikasi.
     *
     * @return array{log: ?WhatsappNotificationLog, reason: ?string}
     */
    private function claimLog(Pembayaran $pembayaran, int $idWarga, string $dedupKey): array
    {
        try {
            return DB::transaction(function () use ($pembayaran, $idWarga, $dedupKey) {
                $existing = WhatsappNotificationLog::where('dedup_key', $dedupKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->delivery_state === WhatsappNotificationLog::STATE_QUEUED) {
                        return ['log' => null, 'reason' => 'already_queued'];
                    }

                    if ($existing->delivery_state === WhatsappNotificationLog::STATE_PROCESSING) {
                        Log::warning('Payment confirmation WhatsApp log is still in processing state; skipping automatic retry to prevent duplicates.', [
                            'id_pembayaran' => $pembayaran->id_pembayaran,
                            'log_id' => $existing->id,
                        ]);

                        return ['log' => null, 'reason' => 'processing_requires_manual_check'];
                    }

                    // Hanya failed atau skipped yang boleh dicoba ulang.
                    $existing->update([
                        'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                        'attempts' => $existing->attempts + 1,
                        'error_reason' => null,
                        'provider_process' => null,
                        'provider_detail' => null,
                    ]);

                    return ['log' => $existing->fresh(), 'reason' => null];
                }

                $log = WhatsappNotificationLog::create([
                    'trigger_type' => self::TRIGGER_TYPE,
                    'reference_type' => 'pembayaran',
                    'reference_id' => $pembayaran->id_pembayaran,
                    'id_warga' => $idWarga,
                    'dedup_key' => $dedupKey,
                    'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                    'is_test_mode' => (bool) config('services.whatsapp.test_mode', false),
                    'attempts' => 1,
                ]);

                return ['log' => $log, 'reason' => null];
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return ['log' => null, 'reason' => 'concurrent_claim'];
            }

            throw $exception;
        }
    }
}
