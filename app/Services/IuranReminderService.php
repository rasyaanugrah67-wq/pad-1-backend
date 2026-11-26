<?php

namespace App\Services;

use App\Models\Iuran;
use App\Models\WhatsappNotificationLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IuranReminderService
{
    public const TRIGGER_TYPE = 'iuran_reminder';

    public const MILESTONE_H_MINUS_3 = 'h_minus_3';
    public const MILESTONE_H_0 = 'h_0';
    public const MILESTONE_H_PLUS_7 = 'h_plus_7';

    private const MILESTONE_OFFSETS = [
        3 => self::MILESTONE_H_MINUS_3,
        0 => self::MILESTONE_H_0,
        -7 => self::MILESTONE_H_PLUS_7,
    ];

    private const MILESTONE_NOTES = [
        self::MILESTONE_H_MINUS_3 => 'Pengingat H-3 sebelum jatuh tempo pembayaran.',
        self::MILESTONE_H_0 => 'Hari ini adalah batas akhir jatuh tempo pembayaran.',
        self::MILESTONE_H_PLUS_7 => 'Pengingat H+7 melewati tanggal jatuh tempo pembayaran.',
    ];

    public function __construct(
        private readonly WhatsAppNotificationService $whatsApp
    ) {
    }

    /**
     * Menjalankan proses pengingat iuran belum dibayar untuk 3 milestone: H-3, H, dan H+7.
     *
     * @return array{
     *     enabled: bool,
     *     dry_run: bool,
     *     reference_date: string,
     *     eligible: int,
     *     queued: int,
     *     skipped: int,
     *     failed: int,
     *     already_sent: int,
     *     would_send: int,
     *     details: array<int, array<string, mixed>>
     * }
     */
    public function sendReminders(
        ?Carbon $referenceDate = null,
        bool $dryRun = false,
        ?int $iuranId = null
    ): array {
        $timezone = (string) config('services.whatsapp.iuran_reminder.timezone', 'Asia/Jakarta');
        $today = ($referenceDate ? $referenceDate->copy()->setTimezone($timezone) : Carbon::today($timezone))->startOfDay();

        $summary = [
            'enabled' => (bool) config('services.whatsapp.iuran_reminder.enabled', true),
            'dry_run' => $dryRun,
            'reference_date' => $today->toDateString(),
            'eligible' => 0,
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'already_sent' => 0,
            'would_send' => 0,
            'details' => [],
        ];

        if (!$summary['enabled']) {
            return $summary;
        }

        $eligibleIuran = $this->findEligibleIuran($today, $iuranId);
        $summary['eligible'] = $eligibleIuran->count();

        foreach ($eligibleIuran as $iuran) {
            $milestone = $this->resolveMilestone($today, $iuran->jatuh_tempo);

            if ($milestone === null) {
                continue;
            }

            $dedupKey = $this->buildDedupKey((int) $iuran->id_iuran, $milestone);

            if ($dryRun) {
                $alreadyLocked = WhatsappNotificationLog::where('dedup_key', $dedupKey)
                    ->whereIn('delivery_state', [
                        WhatsappNotificationLog::STATE_QUEUED,
                        WhatsappNotificationLog::STATE_PROCESSING,
                    ])
                    ->exists();

                if ($alreadyLocked) {
                    $summary['already_sent']++;
                    $summary['details'][] = [
                        'id_iuran' => $iuran->id_iuran,
                        'milestone' => $milestone,
                        'action' => 'already_sent',
                    ];
                } else {
                    $summary['would_send']++;
                    $summary['details'][] = [
                        'id_iuran' => $iuran->id_iuran,
                        'milestone' => $milestone,
                        'action' => 'would_send',
                    ];
                }

                continue;
            }

            $log = $this->claimLog($iuran, $dedupKey);

            if ($log === null) {
                $summary['already_sent']++;
                $summary['details'][] = [
                    'id_iuran' => $iuran->id_iuran,
                    'milestone' => $milestone,
                    'action' => 'already_sent',
                ];
                continue;
            }

            try {
                $result = $this->whatsApp->sendIuranNotificationWithResult(
                    (string) $iuran->warga->no_hp,
                    (string) $iuran->warga->nama,
                    (float) $iuran->nominal,
                    $iuran->jatuh_tempo->toDateString(),
                    $iuran->nama_iuran,
                    self::MILESTONE_NOTES[$milestone] ?? null
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

                $summary[$state]++;
                $summary['details'][] = [
                    'id_iuran' => $iuran->id_iuran,
                    'milestone' => $milestone,
                    'action' => $state,
                    'reason' => $result['reason'] ?? null,
                ];
            } catch (Throwable $exception) {
                $log->update([
                    'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
                    'error_reason' => 'unexpected_exception',
                ]);

                Log::error('Unexpected error while processing iuran WhatsApp reminder.', [
                    'id_iuran' => $iuran->id_iuran,
                    'milestone' => $milestone,
                    'exception' => $exception::class,
                ]);

                $summary['failed']++;
                $summary['details'][] = [
                    'id_iuran' => $iuran->id_iuran,
                    'milestone' => $milestone,
                    'action' => WhatsappNotificationLog::STATE_FAILED,
                    'reason' => 'unexpected_exception',
                ];
            }
        }

        return $summary;
    }

    public function resolveMilestone(Carbon $today, Carbon $jatuhTempo): ?string
    {
        $todayDate = Carbon::createFromFormat('Y-m-d', $today->toDateString(), 'UTC')->startOfDay();
        $dueDate = Carbon::createFromFormat('Y-m-d', $jatuhTempo->toDateString(), 'UTC')->startOfDay();
        $diffDays = (int) $todayDate->diffInDays($dueDate, false);

        return self::MILESTONE_OFFSETS[$diffDays] ?? null;
    }

    public function buildDedupKey(int $idIuran, string $milestone): string
    {
        return sprintf('%s:%d:%s', self::TRIGGER_TYPE, $idIuran, $milestone);
    }

    /**
     * Mengambil daftar iuran yang memenuhi seluruh kriteria penerima pada 3 tanggal milestone.
     *
     * @return Collection<int, Iuran>
     */
    private function findEligibleIuran(Carbon $today, ?int $iuranId = null): Collection
    {
        $targetDates = [
            $today->copy()->addDays(3)->toDateString(), // H-3
            $today->toDateString(),                      // H
            $today->copy()->subDays(7)->toDateString(), // H+7
        ];

        return Iuran::with(['warga:id_warga,nama,no_hp,status', 'tagihan:id_tagihan,nama_tagihan', 'periode:id_periode,tahun'])
            ->where('status', 'Belum Bayar')
            ->where('is_aktif', true)
            ->whereIn('jatuh_tempo', $targetDates)
            ->when($iuranId !== null, fn ($q) => $q->where('id_iuran', $iuranId))
            ->whereHas('warga', function ($q) {
                $q->where('status', 'Aktif')
                    ->whereNotNull('no_hp')
                    ->whereRaw("TRIM(no_hp) <> ''");
            })
            ->whereDoesntHave('pembayaran', function ($q) {
                $q->where('status_verifikasi', 'Menunggu');
            })
            ->orderBy('id_iuran')
            ->get();
    }

    /**
     * Mengklaim dedup_key secara atomik dengan status processing agar dua proses
     * tidak dapat mengirim milestone yang sama secara bersamaan.
     */
    private function claimLog(Iuran $iuran, string $dedupKey): ?WhatsappNotificationLog
    {
        try {
            return DB::transaction(function () use ($iuran, $dedupKey) {
                $existing = WhatsappNotificationLog::where('dedup_key', $dedupKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (in_array($existing->delivery_state, [
                        WhatsappNotificationLog::STATE_QUEUED,
                        WhatsappNotificationLog::STATE_PROCESSING,
                    ], true)) {
                        return null;
                    }

                    // Status failed atau skipped boleh dicoba ulang.
                    $existing->update([
                        'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                        'attempts' => $existing->attempts + 1,
                        'error_reason' => null,
                        'provider_process' => null,
                        'provider_detail' => null,
                    ]);

                    return $existing->fresh();
                }

                return WhatsappNotificationLog::create([
                    'trigger_type' => self::TRIGGER_TYPE,
                    'reference_type' => 'iuran',
                    'reference_id' => $iuran->id_iuran,
                    'id_warga' => $iuran->id_warga,
                    'dedup_key' => $dedupKey,
                    'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                    'is_test_mode' => (bool) config('services.whatsapp.test_mode', false),
                    'attempts' => 1,
                ]);
            });
        } catch (QueryException $exception) {
            // Proteksi race condition pada unique constraint dedup_key (SQLSTATE 23000).
            if ((string) $exception->getCode() === '23000') {
                return null;
            }

            throw $exception;
        }
    }
}
