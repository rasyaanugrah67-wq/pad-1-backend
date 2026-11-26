<?php

namespace App\Services;

use App\Models\JadwalLomba;
use App\Models\Pendaftaran;
use App\Models\WhatsappNotificationLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LombaParticipantReminderService
{
    public const TRIGGER_TYPE = 'lomba_reminder';

    public const MILESTONE_H_MINUS_1 = 'h_minus_1';
    public const MILESTONE_H_0 = 'h_0';

    private const MILESTONE_OFFSETS = [
        1 => self::MILESTONE_H_MINUS_1,
        0 => self::MILESTONE_H_0,
    ];

    private const MILESTONE_NOTES = [
        self::MILESTONE_H_MINUS_1 => 'Pengingat H-1 sebelum pelaksanaan lomba.',
        self::MILESTONE_H_0 => 'Pelaksanaan lomba dijadwalkan hari ini.',
    ];

    public function __construct(
        private readonly WhatsAppNotificationService $whatsApp
    ) {
    }

    /**
     * Menjalankan proses pengingat WhatsApp peserta lomba untuk milestone H-1 dan H.
     *
     * @return array{
     *     enabled: bool,
     *     dry_run: bool,
     *     reference_date: string,
     *     milestone_filter: string|null,
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
        ?Carbon $referenceDateTime = null,
        bool $dryRun = false,
        ?int $pendaftaranId = null,
        ?int $lombaId = null,
        ?string $milestoneFilter = null
    ): array {
        $timezone = (string) config('services.whatsapp.lomba_reminder.timezone', 'Asia/Jakarta');
        $now = $referenceDateTime
            ? $referenceDateTime->copy()->setTimezone($timezone)
            : Carbon::now($timezone);
        $today = $now->copy()->startOfDay();

        $summary = [
            'enabled' => (bool) config('services.whatsapp.lomba_reminder.enabled', true),
            'dry_run' => $dryRun,
            'reference_date' => $today->toDateString(),
            'milestone_filter' => $milestoneFilter,
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

        $pendaftaranList = $this->findEligiblePendaftaran($today, $pendaftaranId, $lombaId, $milestoneFilter);

        foreach ($pendaftaranList as $pendaftaran) {
            $normalizedPhone = $this->whatsApp->normalizePhoneNumber((string) ($pendaftaran->warga?->no_hp ?? ''));
            if (strlen($normalizedPhone) < 8) {
                continue;
            }

            $jadwals = $pendaftaran->lomba?->jadwal ?? collect();

            foreach ($jadwals as $jadwal) {
                $milestone = $this->resolveMilestone($today, $jadwal->tanggal);
                if ($milestone === null) {
                    continue;
                }

                if ($milestoneFilter !== null && $milestone !== $milestoneFilter) {
                    continue;
                }

                if (!$this->isScheduleStillUpcoming($now, $jadwal, $timezone)) {
                    continue;
                }

                $summary['eligible']++;

                $dedupKey = $this->buildDedupKey((int) $pendaftaran->id_pendaftaran, $milestone, $jadwal);

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
                            'id_pendaftaran' => $pendaftaran->id_pendaftaran,
                            'id_lomba' => $pendaftaran->id_lomba,
                            'id_jadwal' => $jadwal->id_jadwal,
                            'milestone' => $milestone,
                            'action' => 'already_sent',
                        ];
                    } else {
                        $summary['would_send']++;
                        $summary['details'][] = [
                            'id_pendaftaran' => $pendaftaran->id_pendaftaran,
                            'id_lomba' => $pendaftaran->id_lomba,
                            'id_jadwal' => $jadwal->id_jadwal,
                            'milestone' => $milestone,
                            'action' => 'would_send',
                        ];
                    }

                    continue;
                }

                $log = $this->claimLog($pendaftaran, $dedupKey);

                if ($log === null) {
                    $summary['already_sent']++;
                    $summary['details'][] = [
                        'id_pendaftaran' => $pendaftaran->id_pendaftaran,
                        'id_lomba' => $pendaftaran->id_lomba,
                        'id_jadwal' => $jadwal->id_jadwal,
                        'milestone' => $milestone,
                        'action' => 'already_sent',
                    ];
                    continue;
                }

                try {
                    $result = $this->whatsApp->sendLombaParticipantReminderWithResult(
                        (string) $pendaftaran->warga->no_hp,
                        (string) $pendaftaran->warga->nama,
                        (string) $pendaftaran->lomba->nama_lomba,
                        $this->formatScheduleText($jadwal),
                        $jadwal->lokasi ? trim((string) $jadwal->lokasi) : null,
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
                        'id_pendaftaran' => $pendaftaran->id_pendaftaran,
                        'id_lomba' => $pendaftaran->id_lomba,
                        'id_jadwal' => $jadwal->id_jadwal,
                        'milestone' => $milestone,
                        'action' => $state,
                        'reason' => $result['reason'] ?? null,
                    ];
                } catch (Throwable $exception) {
                    $log->update([
                        'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
                        'error_reason' => 'unexpected_exception',
                    ]);

                    Log::error('Unexpected error while processing lomba participant WhatsApp reminder.', [
                        'id_pendaftaran' => $pendaftaran->id_pendaftaran,
                        'id_jadwal' => $jadwal->id_jadwal,
                        'milestone' => $milestone,
                        'exception' => $exception::class,
                    ]);

                    $summary['failed']++;
                    $summary['details'][] = [
                        'id_pendaftaran' => $pendaftaran->id_pendaftaran,
                        'id_lomba' => $pendaftaran->id_lomba,
                        'id_jadwal' => $jadwal->id_jadwal,
                        'milestone' => $milestone,
                        'action' => WhatsappNotificationLog::STATE_FAILED,
                        'reason' => 'unexpected_exception',
                    ];
                }
            }
        }

        return $summary;
    }

    public function resolveMilestone(Carbon $today, ?Carbon $tanggalJadwal): ?string
    {
        if (!$tanggalJadwal) {
            return null;
        }

        $todayDate = Carbon::createFromFormat('Y-m-d', $today->toDateString(), 'UTC')->startOfDay();
        $scheduleDate = Carbon::createFromFormat('Y-m-d', $tanggalJadwal->toDateString(), 'UTC')->startOfDay();
        $diffDays = (int) $todayDate->diffInDays($scheduleDate, false);

        return self::MILESTONE_OFFSETS[$diffDays] ?? null;
    }

    /**
     * Fingerprint jadwal mencakup tanggal, waktu mulai, waktu selesai, dan lokasi.
     */
    public function buildScheduleFingerprint(JadwalLomba $jadwal): string
    {
        $raw = implode('|', [
            $jadwal->tanggal ? $jadwal->tanggal->toDateString() : '',
            trim((string) ($jadwal->waktu_mulai ?? '')),
            trim((string) ($jadwal->waktu_selesai ?? '')),
            mb_strtolower(trim((string) ($jadwal->lokasi ?? ''))),
        ]);

        return substr(sha1($raw), 0, 12);
    }

    public function buildDedupKey(int $idPendaftaran, string $milestone, JadwalLomba $jadwal): string
    {
        return sprintf(
            '%s:%d:%s:%s',
            self::TRIGGER_TYPE,
            $idPendaftaran,
            $milestone,
            $this->buildScheduleFingerprint($jadwal)
        );
    }

    /**
     * Memastikan jadwal belum selesai terhadap waktu acuan sekarang.
     */
    public function isScheduleStillUpcoming(Carbon $now, JadwalLomba $jadwal, string $timezone): bool
    {
        if (!$jadwal->tanggal) {
            return false;
        }

        $scheduleDateStr = $jadwal->tanggal->toDateString();
        $todayStr = $now->toDateString();

        if ($scheduleDateStr > $todayStr) {
            return true;
        }

        if ($scheduleDateStr < $todayStr) {
            return false;
        }

        // Pada hari H: jika waktu_selesai ada dan sudah lewat, lomba sudah selesai.
        if (!empty($jadwal->waktu_selesai)) {
            $endTime = Carbon::parse($scheduleDateStr . ' ' . $jadwal->waktu_selesai, $timezone);
            if ($now->greaterThanOrEqualTo($endTime)) {
                return false;
            }
        } elseif (!empty($jadwal->waktu_mulai)) {
            $startTime = Carbon::parse($scheduleDateStr . ' ' . $jadwal->waktu_mulai, $timezone);
            if ($now->greaterThan($startTime)) {
                return false;
            }
        }

        return true;
    }

    private function formatScheduleText(JadwalLomba $jadwal): string
    {
        $dateText = $jadwal->tanggal ? $jadwal->tanggal->format('d-m-Y') : '-';
        $start = $jadwal->waktu_mulai ? substr((string) $jadwal->waktu_mulai, 0, 5) : null;
        $end = $jadwal->waktu_selesai ? substr((string) $jadwal->waktu_selesai, 0, 5) : null;

        if ($start && $end) {
            return "{$dateText}, {$start} - {$end} WIB";
        }

        if ($start) {
            return "{$dateText}, {$start} WIB";
        }

        return $dateText;
    }

    /**
     * @return Collection<int, Pendaftaran>
     */
    private function findEligiblePendaftaran(
        Carbon $today,
        ?int $pendaftaranId,
        ?int $lombaId,
        ?string $milestoneFilter
    ): Collection {
        $targetDates = [];
        if ($milestoneFilter === null || $milestoneFilter === self::MILESTONE_H_MINUS_1) {
            $targetDates[] = $today->copy()->addDay()->toDateString();
        }
        if ($milestoneFilter === null || $milestoneFilter === self::MILESTONE_H_0) {
            $targetDates[] = $today->toDateString();
        }

        if (empty($targetDates)) {
            return collect();
        }

        return Pendaftaran::with([
            'warga:id_warga,nama,no_hp,status',
            'lomba' => function ($q) use ($targetDates) {
                $q->where('is_arsip', false)
                    ->with([
                        'jadwal' => function ($jq) use ($targetDates) {
                            $jq->whereIn('tanggal', $targetDates)
                                ->orderBy('tanggal')
                                ->orderBy('waktu_mulai')
                                ->orderBy('id_jadwal');
                        },
                    ]);
            },
        ])
            ->where('status', 'Diterima')
            ->when($pendaftaranId !== null, fn ($q) => $q->where('id_pendaftaran', $pendaftaranId))
            ->when($lombaId !== null, fn ($q) => $q->where('id_lomba', $lombaId))
            ->whereHas('warga', function ($q) {
                $q->where('status', 'Aktif')
                    ->whereNotNull('no_hp')
                    ->whereRaw("TRIM(no_hp) <> ''");
            })
            ->whereHas('lomba', function ($q) use ($targetDates) {
                $q->where('is_arsip', false)
                    ->whereHas('jadwal', fn ($jq) => $jq->whereIn('tanggal', $targetDates));
            })
            ->orderBy('id_pendaftaran')
            ->get();
    }

    private function claimLog(Pendaftaran $pendaftaran, string $dedupKey): ?WhatsappNotificationLog
    {
        try {
            return DB::transaction(function () use ($pendaftaran, $dedupKey) {
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
                    'reference_type' => 'pendaftaran',
                    'reference_id' => $pendaftaran->id_pendaftaran,
                    'id_warga' => $pendaftaran->id_warga,
                    'dedup_key' => $dedupKey,
                    'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                    'is_test_mode' => (bool) config('services.whatsapp.test_mode', false),
                    'attempts' => 1,
                ]);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return null;
            }

            throw $exception;
        }
    }
}
