<?php

namespace App\Services;

use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappNotificationLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trigger 5: Undangan menonton lomba sekitar 30 menit sebelum pertandingan dimulai.
 *
 * Mekanisme anti-duplikasi & perubahan jadwal terkendali:
 * 1. Setiap pasangan (id_pertandingan, id_warga, schedule_hash) memiliki dedup_key unik:
 *    `match_invitation:{id_pertandingan}:{id_warga}:{schedule_hash}`.
 *    Jika jadwal tidak berubah, status `queued` atau `processing` mencegah pengiriman ulang.
 * 2. Jika jadwal diubah SEBELUM undangan pertama dikirim, belum ada log `queued`, sehingga
 *    undangan otomatis memakai jadwal terbaru saat memasuki jendela 30 menit.
 * 3. Jika jadwal diubah SETELAH undangan pertama sempat terkirim (`queued`), sistem
 *    mengizinkan pembaruan jadwal secara terkendali maksimal `max_schedule_updates` kali
 *    (default 1 kali pembaruan -> total maksimal 2 undangan `queued` per pertandingan per warga:
 *    1 undangan awal + 1 pembaruan jadwal). Perubahan jadwal berikutnya yang melebihi batas
 *    tersebut ditahan (`max_updates_reached`) agar warga tidak menerima pesan berulang kali.
 */
class MatchWatchInvitationService
{
    public const TRIGGER_TYPE = 'match_invitation';

    public function __construct(
        private readonly WhatsAppNotificationService $whatsApp
    ) {
    }

    /**
     * Menjalankan pengiriman undangan menonton lomba untuk pertandingan dalam jendela 30 menit.
     *
     * @return array{
     *     enabled: bool,
     *     dry_run: bool,
     *     reference_time: string,
     *     rw_number: string,
     *     rw_found: bool,
     *     matches_eligible: int,
     *     recipients_eligible: int,
     *     queued: int,
     *     skipped: int,
     *     failed: int,
     *     already_sent: int,
     *     max_updates_blocked: int,
     *     would_send: int,
     *     details: array<int, array<string, mixed>>
     * }
     */
    public function sendInvitations(
        ?Carbon $referenceTime = null,
        bool $dryRun = false,
        ?int $pertandinganId = null,
        ?int $lombaId = null,
        ?int $wargaId = null
    ): array {
        $timezone = (string) config('services.whatsapp.match_invitation.timezone', 'Asia/Jakarta');
        $now = $referenceTime
            ? $referenceTime->copy()->setTimezone($timezone)
            : Carbon::now($timezone);

        $rwNumber = trim((string) config('services.whatsapp.match_invitation.rw_number', '01'));
        $leadMinutes = max(1, (int) config('services.whatsapp.match_invitation.lead_minutes', 30));
        $windowMinutes = max(0, (int) config('services.whatsapp.match_invitation.window_minutes', 5));
        $maxScheduleUpdates = max(0, (int) config('services.whatsapp.match_invitation.max_schedule_updates', 1));
        $maxTotalQueuedPerRecipient = 1 + $maxScheduleUpdates;

        $summary = [
            'enabled' => (bool) config('services.whatsapp.match_invitation.enabled', true),
            'dry_run' => $dryRun,
            'reference_time' => $now->format('Y-m-d H:i:s'),
            'rw_number' => $rwNumber,
            'rw_found' => false,
            'matches_eligible' => 0,
            'recipients_eligible' => 0,
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'already_sent' => 0,
            'max_updates_blocked' => 0,
            'would_send' => 0,
            'details' => [],
        ];

        if (!$summary['enabled']) {
            return $summary;
        }

        $rw = $this->resolveHostRw($rwNumber);
        if (!$rw) {
            Log::warning('Match watch invitation skipped because host RW was not found.', [
                'rw_number' => $rwNumber,
            ]);

            return $summary;
        }

        $summary['rw_found'] = true;
        $rwLabel = $rw->nama_rw ?: ('RW ' . $rw->nomor_rw);

        $matches = $this->findEligibleMatches($now, $leadMinutes, $windowMinutes, $pertandinganId, $lombaId);
        $summary['matches_eligible'] = $matches->count();

        if ($matches->isEmpty()) {
            return $summary;
        }

        $recipients = $this->findEligibleRecipients($rw->id_rw, $wargaId);
        $summary['recipients_eligible'] = $recipients->count();

        if ($recipients->isEmpty()) {
            return $summary;
        }

        foreach ($matches as $match) {
            $waktuMulai = Carbon::parse((string) $match->waktu_mulai, $timezone);
            $lokasi = $this->resolveMatchLocation($match, $waktuMulai);
            $babak = $match->babak ? trim((string) $match->babak) : null;
            $pesertaText = $this->formatPesertaText($match->peserta_1 ?? null, $match->peserta_2 ?? null);
            $scheduleHash = $this->buildScheduleFingerprint($waktuMulai, $lokasi, $babak);
            $waktuMulaiFormatted = $waktuMulai->format('d-m-Y, H:i') . ' WIB';

            foreach ($recipients as $warga) {
                $dedupKey = $this->buildDedupKey((int) $match->id_pertandingan, (int) $warga->id_warga, $scheduleHash);

                if ($dryRun) {
                    $dryStatus = $this->evaluateDryRunState(
                        (int) $match->id_pertandingan,
                        (int) $warga->id_warga,
                        $dedupKey,
                        $maxTotalQueuedPerRecipient
                    );

                    $summary[$dryStatus]++;
                    $summary['details'][] = [
                        'id_pertandingan' => (int) $match->id_pertandingan,
                        'id_warga' => (int) $warga->id_warga,
                        'action' => $dryStatus,
                    ];
                    continue;
                }

                $claim = $this->claimLog(
                    (int) $match->id_pertandingan,
                    (int) $warga->id_warga,
                    $dedupKey,
                    $maxTotalQueuedPerRecipient
                );

                if ($claim['log'] === null) {
                    $reason = $claim['reason'];
                    if ($reason === 'max_updates_reached') {
                        $summary['max_updates_blocked']++;
                    } else {
                        $summary['already_sent']++;
                    }

                    $summary['details'][] = [
                        'id_pertandingan' => (int) $match->id_pertandingan,
                        'id_warga' => (int) $warga->id_warga,
                        'action' => $reason,
                    ];
                    continue;
                }

                /** @var WhatsappNotificationLog $log */
                $log = $claim['log'];
                $isScheduleUpdate = $claim['prior_queued_count'] > 0;

                // Isolasi kegagalan per warga agar kegagalan 1 nomor tidak menghentikan penerima lainnya.
                try {
                    $result = $this->whatsApp->sendMatchWatchInvitationWithResult(
                        (string) $warga->no_hp,
                        (string) $warga->nama,
                        (string) $match->nama_lomba,
                        $waktuMulaiFormatted,
                        $lokasi,
                        $babak,
                        $pesertaText,
                        $rwLabel,
                        $isScheduleUpdate
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
                        'id_pertandingan' => (int) $match->id_pertandingan,
                        'id_warga' => (int) $warga->id_warga,
                        'action' => $state,
                        'is_schedule_update' => $isScheduleUpdate,
                        'reason' => $result['reason'] ?? null,
                    ];
                } catch (Throwable $exception) {
                    $log->update([
                        'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
                        'error_reason' => 'unexpected_exception',
                    ]);

                    Log::error('Unexpected error while dispatching match watch invitation WhatsApp.', [
                        'id_pertandingan' => (int) $match->id_pertandingan,
                        'id_warga' => (int) $warga->id_warga,
                        'exception' => $exception::class,
                    ]);

                    $summary['failed']++;
                    $summary['details'][] = [
                        'id_pertandingan' => (int) $match->id_pertandingan,
                        'id_warga' => (int) $warga->id_warga,
                        'action' => WhatsappNotificationLog::STATE_FAILED,
                        'reason' => 'unexpected_exception',
                    ];
                }
            }
        }

        return $summary;
    }

    public function buildScheduleFingerprint(Carbon $waktuMulai, ?string $lokasi, ?string $babak): string
    {
        $raw = implode('|', [
            $waktuMulai->format('Y-m-d H:i'),
            mb_strtolower(trim((string) ($lokasi ?? ''))),
            mb_strtolower(trim((string) ($babak ?? ''))),
        ]);

        return substr(sha1($raw), 0, 12);
    }

    public function buildDedupKey(int $idPertandingan, int $idWarga, string $scheduleHash): string
    {
        return sprintf('%s:%d:%d:%s', self::TRIGGER_TYPE, $idPertandingan, $idWarga, $scheduleHash);
    }

    private function resolveHostRw(string $rwNumber): ?Rw
    {
        if ($rwNumber === '') {
            return null;
        }

        $exact = Rw::where('nomor_rw', $rwNumber)->first();
        if ($exact) {
            return $exact;
        }

        // Toleransi perbedaan leading zero (mis. "01" vs "001" vs "1").
        $normalizedTarget = ltrim($rwNumber, '0') ?: '0';

        return Rw::all()->first(function (Rw $rw) use ($normalizedTarget) {
            $candidate = ltrim(trim((string) $rw->nomor_rw), '0') ?: '0';

            return $candidate === $normalizedTarget;
        });
    }

    /**
     * Mengambil pertandingan Dijadwalkan milik lomba tidak diarsipkan yang berada
     * di jendela [now + (lead - window), now + (lead + window)].
     *
     * Menggunakan DB::table('pertandingan') agar aman terhadap case-sensitivity Linux/Wasmer.
     */
    private function findEligibleMatches(
        Carbon $now,
        int $leadMinutes,
        int $windowMinutes,
        ?int $pertandinganId,
        ?int $lombaId
    ): Collection {
        $windowStart = $now->copy()->addMinutes(max(1, $leadMinutes - $windowMinutes));
        $windowEnd = $now->copy()->addMinutes($leadMinutes + $windowMinutes);

        return DB::table('pertandingan as p')
            ->join('lomba as l', 'p.id_lomba', '=', 'l.id_lomba')
            ->leftJoin('jadwal_lomba as j', 'p.id_jadwal', '=', 'j.id_jadwal')
            ->where('p.status', 'Dijadwalkan')
            ->where('l.is_arsip', false)
            ->whereBetween('p.waktu_mulai', [
                $windowStart->format('Y-m-d H:i:s'),
                $windowEnd->format('Y-m-d H:i:s'),
            ])
            ->when($pertandinganId !== null, fn ($q) => $q->where('p.id_pertandingan', $pertandinganId))
            ->when($lombaId !== null, fn ($q) => $q->where('p.id_lomba', $lombaId))
            ->select(
                'p.id_pertandingan',
                'p.id_lomba',
                'p.id_jadwal',
                'p.peserta_1',
                'p.peserta_2',
                'p.babak',
                'p.waktu_mulai',
                'p.waktu_selesai',
                'p.status',
                'l.nama_lomba',
                'j.lokasi as jadwal_lokasi'
            )
            ->orderBy('p.waktu_mulai')
            ->orderBy('p.id_pertandingan')
            ->get();
    }

    /**
     * Mengambil seluruh warga aktif dengan nomor HP valid di seluruh RT pada RW penyelenggara.
     * Filter --warga=ID tetap melewati validasi wilayah RW dan status aktif.
     *
     * @return Collection<int, Warga>
     */
    private function findEligibleRecipients(int $idRw, ?int $wargaId): Collection
    {
        return Warga::where('status', 'Aktif')
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(no_hp) <> ''")
            ->whereHas('rt', fn ($q) => $q->where('id_rw', $idRw))
            ->when($wargaId !== null, fn ($q) => $q->where('id_warga', $wargaId))
            ->orderBy('id_warga')
            ->get(['id_warga', 'id_rt', 'nama', 'no_hp', 'status'])
            ->filter(function (Warga $warga) {
                $normalized = $this->whatsApp->normalizePhoneNumber((string) $warga->no_hp);

                return strlen($normalized) >= 8;
            })
            ->values();
    }

    private function resolveMatchLocation(object $match, Carbon $waktuMulai): ?string
    {
        if (!empty($match->jadwal_lokasi) && trim((string) $match->jadwal_lokasi) !== '') {
            return trim((string) $match->jadwal_lokasi);
        }

        // Fallback pelengkap ke jadwal_lomba milik lomba pada tanggal yang sama jika id_jadwal null.
        $fallbackLokasi = DB::table('jadwal_lomba')
            ->where('id_lomba', $match->id_lomba)
            ->where('tanggal', $waktuMulai->toDateString())
            ->whereNotNull('lokasi')
            ->whereRaw("TRIM(lokasi) <> ''")
            ->orderBy('waktu_mulai')
            ->value('lokasi');

        return $fallbackLokasi ? trim((string) $fallbackLokasi) : null;
    }

    private function formatPesertaText(?string $peserta1, ?string $peserta2): ?string
    {
        $p1 = trim((string) ($peserta1 ?? ''));
        $p2 = trim((string) ($peserta2 ?? ''));

        if ($p1 !== '' && $p2 !== '') {
            return "{$p1} vs {$p2}";
        }

        return $p1 !== '' ? $p1 : ($p2 !== '' ? $p2 : null);
    }

    private function evaluateDryRunState(
        int $idPertandingan,
        int $idWarga,
        string $dedupKey,
        int $maxTotalQueuedPerRecipient
    ): string {
        $sameScheduleLocked = WhatsappNotificationLog::where('dedup_key', $dedupKey)
            ->whereIn('delivery_state', [
                WhatsappNotificationLog::STATE_QUEUED,
                WhatsappNotificationLog::STATE_PROCESSING,
            ])
            ->exists();

        if ($sameScheduleLocked) {
            return 'already_sent';
        }

        $priorQueuedOrProcessing = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
            ->where('reference_type', 'pertandingan')
            ->where('reference_id', $idPertandingan)
            ->where('id_warga', $idWarga)
            ->whereIn('delivery_state', [
                WhatsappNotificationLog::STATE_QUEUED,
                WhatsappNotificationLog::STATE_PROCESSING,
            ])
            ->count();

        if ($priorQueuedOrProcessing >= $maxTotalQueuedPerRecipient) {
            return 'max_updates_blocked';
        }

        return 'would_send';
    }

    /**
     * Klaim atomik per (id_pertandingan, id_warga, schedule_hash) yang aman terhadap
     * eksekusi bersamaan dan membatasi jumlah pembaruan jadwal terkendali.
     *
     * @return array{log: ?WhatsappNotificationLog, reason: ?string, prior_queued_count: int}
     */
    private function claimLog(
        int $idPertandingan,
        int $idWarga,
        string $dedupKey,
        int $maxTotalQueuedPerRecipient
    ): array {
        try {
            return DB::transaction(function () use ($idPertandingan, $idWarga, $dedupKey, $maxTotalQueuedPerRecipient) {
                $existing = WhatsappNotificationLog::where('dedup_key', $dedupKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (in_array($existing->delivery_state, [
                        WhatsappNotificationLog::STATE_QUEUED,
                        WhatsappNotificationLog::STATE_PROCESSING,
                    ], true)) {
                        return ['log' => null, 'reason' => 'already_sent', 'prior_queued_count' => 0];
                    }

                    // Hitung prior queued dari jadwal lain untuk mengetahui apakah ini pembaruan jadwal.
                    $priorQueuedOther = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
                        ->where('reference_type', 'pertandingan')
                        ->where('reference_id', $idPertandingan)
                        ->where('id_warga', $idWarga)
                        ->where('id', '!=', $existing->id)
                        ->whereIn('delivery_state', [
                            WhatsappNotificationLog::STATE_QUEUED,
                            WhatsappNotificationLog::STATE_PROCESSING,
                        ])
                        ->lockForUpdate()
                        ->count();

                    if ($priorQueuedOther >= $maxTotalQueuedPerRecipient) {
                        return ['log' => null, 'reason' => 'max_updates_reached', 'prior_queued_count' => $priorQueuedOther];
                    }

                    $existing->update([
                        'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                        'attempts' => $existing->attempts + 1,
                        'error_reason' => null,
                        'provider_process' => null,
                        'provider_detail' => null,
                    ]);

                    return ['log' => $existing->fresh(), 'reason' => null, 'prior_queued_count' => $priorQueuedOther];
                }

                // Cek batas maksimal pengiriman terkendali untuk pertandingan & warga ini.
                $priorQueuedCount = WhatsappNotificationLog::where('trigger_type', self::TRIGGER_TYPE)
                    ->where('reference_type', 'pertandingan')
                    ->where('reference_id', $idPertandingan)
                    ->where('id_warga', $idWarga)
                    ->whereIn('delivery_state', [
                        WhatsappNotificationLog::STATE_QUEUED,
                        WhatsappNotificationLog::STATE_PROCESSING,
                    ])
                    ->lockForUpdate()
                    ->count();

                if ($priorQueuedCount >= $maxTotalQueuedPerRecipient) {
                    return ['log' => null, 'reason' => 'max_updates_reached', 'prior_queued_count' => $priorQueuedCount];
                }

                $log = WhatsappNotificationLog::create([
                    'trigger_type' => self::TRIGGER_TYPE,
                    'reference_type' => 'pertandingan',
                    'reference_id' => $idPertandingan,
                    'id_warga' => $idWarga,
                    'dedup_key' => $dedupKey,
                    'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
                    'is_test_mode' => (bool) config('services.whatsapp.test_mode', false),
                    'attempts' => 1,
                ]);

                return ['log' => $log, 'reason' => null, 'prior_queued_count' => $priorQueuedCount];
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return ['log' => null, 'reason' => 'concurrent_claim', 'prior_queued_count' => 0];
            }

            throw $exception;
        }
    }
}
