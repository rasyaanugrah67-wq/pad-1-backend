<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\WhatsappNotificationLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountCreatedWhatsAppService
{
    public const TRIGGER_TYPE = 'account_created';
    public const DEFAULT_INITIAL_PASSWORD = 'WargaDemo2026!';

    public function __construct(
        private readonly WhatsAppNotificationService $whatsApp
    ) {
    }

    public function buildDedupKey(int $idAkun): string
    {
        return sprintf('%s:%d', self::TRIGGER_TYPE, $idAkun);
    }

    /**
     * Mengirim informasi akun warga baru via WhatsApp dengan pencatatan anti-duplikasi
     * pada tabel whatsapp_notification_logs.
     *
     * Password teks biasa hanya digunakan sementara saat mengirim pesan WhatsApp
     * dan tidak pernah disimpan di notification logs maupun log aplikasi.
     *
     * @return array{
     *     action: string,
     *     reason: string|null,
     *     id_akun: int
     * }
     */
    public function sendForNewAccount(Akun $akun, ?string $initialPassword = null): array
    {
        $akun->refresh()->load('warga:id_warga,nama,no_hp,status');
        $warga = $akun->warga;

        if (!$warga || $warga->status !== 'Aktif' || $akun->status !== 'Aktif') {
            return [
                'action' => 'ignored',
                'reason' => 'account_or_warga_inactive',
                'id_akun' => (int) $akun->id_akun,
            ];
        }

        $normalizedPhone = $this->whatsApp->normalizePhoneNumber((string) ($warga->no_hp ?? ''));
        if (strlen($normalizedPhone) < 8) {
            return [
                'action' => 'ignored',
                'reason' => 'invalid_or_empty_phone',
                'id_akun' => (int) $akun->id_akun,
            ];
        }

        $dedupKey = $this->buildDedupKey((int) $akun->id_akun);
        $claim = $this->claimLog($akun, (int) $warga->id_warga, $dedupKey);

        if ($claim['log'] === null) {
            return [
                'action' => 'already_handled',
                'reason' => $claim['reason'],
                'id_akun' => (int) $akun->id_akun,
            ];
        }

        /** @var WhatsappNotificationLog $log */
        $log = $claim['log'];

        // Untuk retry: gunakan password awal default hanya jika password akun belum diubah oleh warga.
        $passwordToSend = $initialPassword;
        if ($passwordToSend === null && Hash::check(self::DEFAULT_INITIAL_PASSWORD, (string) $akun->password)) {
            $passwordToSend = self::DEFAULT_INITIAL_PASSWORD;
        }

        try {
            $result = $this->whatsApp->sendAccountCredentialWithResult(
                (string) $warga->no_hp,
                (string) $warga->nama,
                (string) $akun->username,
                $passwordToSend
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
                'id_akun' => (int) $akun->id_akun,
            ];
        } catch (Throwable $exception) {
            $log->update([
                'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
                'error_reason' => 'unexpected_exception',
            ]);

            Log::error('Unexpected error while dispatching account created WhatsApp notification.', [
                'id_akun' => $akun->id_akun,
                'id_warga' => $warga->id_warga,
                'exception' => $exception::class,
            ]);

            return [
                'action' => WhatsappNotificationLog::STATE_FAILED,
                'reason' => 'unexpected_exception',
                'id_akun' => (int) $akun->id_akun,
            ];
        }
    }

    /**
     * Mencoba ulang pengiriman informasi akun HANYA untuk log berstatus failed.
     * Log berstatus processing tertinggal tidak di-retry otomatis demi mencegah duplikasi.
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
    public function retryFailedNotifications(?int $akunId = null, bool $dryRun = false): array
    {
        $staleProcessingCount = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
            ->where('delivery_state', WhatsappNotificationLog::STATE_PROCESSING)
            ->when($akunId !== null, fn ($q) => $q->where('reference_id', $akunId))
            ->count();

        $failedLogs = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
            ->where('delivery_state', WhatsappNotificationLog::STATE_FAILED)
            ->when($akunId !== null, fn ($q) => $q->where('reference_id', $akunId))
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

            $akun = Akun::find($failedLog->reference_id);
            if (!$akun) {
                $summary['ignored']++;
                continue;
            }

            $res = $this->sendForNewAccount($akun);
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
     * @return array{log: ?WhatsappNotificationLog, reason: ?string}
     */
    private function claimLog(Akun $akun, int $idWarga, string $dedupKey): array
    {
        try {
            return DB::transaction(function () use ($akun, $idWarga, $dedupKey) {
                $existing = WhatsappNotificationLog::where('dedup_key', $dedupKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->delivery_state === WhatsappNotificationLog::STATE_QUEUED) {
                        return ['log' => null, 'reason' => 'already_queued'];
                    }

                    if ($existing->delivery_state === WhatsappNotificationLog::STATE_PROCESSING) {
                        Log::warning('Account created WhatsApp log is still in processing state; skipping automatic retry to prevent duplicates.', [
                            'id_akun' => $akun->id_akun,
                            'log_id' => $existing->id,
                        ]);

                        return ['log' => null, 'reason' => 'processing_requires_manual_check'];
                    }

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
                    'reference_type' => 'akun',
                    'reference_id' => $akun->id_akun,
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
