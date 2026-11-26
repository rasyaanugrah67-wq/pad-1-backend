<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotificationService
{
    public const STATE_QUEUED = 'queued';
    public const STATE_SKIPPED = 'skipped';
    public const STATE_FAILED = 'failed';

    public function __construct(
        private readonly ?WhatsappTemplateService $templates = null
    ) {
    }

    private function templateService(): WhatsappTemplateService
    {
        return $this->templates ?? app(WhatsappTemplateService::class);
    }

    private function sendFromTemplate(string $phoneNumber, string $templateKey, array $variables): array
    {
        $resolved = $this->templateService()->resolveMessage($templateKey, $variables);

        if (!$resolved['can_send'] || $resolved['message'] === null) {
            $isTestMode = (bool) config('services.whatsapp.test_mode', false);

            Log::info('WhatsApp notification skipped because template is inactive or unavailable.', [
                'template_key' => $templateKey,
                'reason' => $resolved['reason'],
                'is_test_mode' => $isTestMode,
            ]);

            return $this->result(
                false,
                self::STATE_SKIPPED,
                null,
                false,
                null,
                null,
                $resolved['reason'] ?? 'template_disabled',
                $isTestMode
            );
        }

        return $this->sendWithResult($phoneNumber, $resolved['message']);
    }

    public function send(
        string $phoneNumber,
        string $message
    ): bool {
        $result = $this->sendWithResult($phoneNumber, $message);

        return $result['success'];
    }

    /**
     * Mengirim pesan WhatsApp melalui Fonnte dan mengembalikan status terstruktur.
     *
     * Catatan: respons sukses dari Fonnte menandakan pesan berhasil masuk antrean
     * ("queued"), bukan jaminan pesan sudah diterima/dibaca di perangkat penerima.
     *
     * @return array{
     *     success: bool,
     *     state: string,
     *     http_status: int|null,
     *     provider_status: bool,
     *     provider_process: string|null,
     *     provider_detail: string|null,
     *     reason: string|null,
     *     is_test_mode: bool
     * }
     */
    public function sendWithResult(
        string $phoneNumber,
        string $message
    ): array {
        $isTestMode = (bool) config('services.whatsapp.test_mode', false);

        if (!config('services.whatsapp.enabled', false)) {
            Log::info('WhatsApp notification skipped because integration is disabled.', [
                'is_test_mode' => $isTestMode,
                'message_length' => mb_strlen($message),
            ]);

            return $this->result(false, self::STATE_SKIPPED, null, false, null, null, 'disabled', $isTestMode);
        }

        if ($isTestMode) {
            $targetNumber = $this->normalizePhoneNumber((string) config('services.whatsapp.test_number', ''));

            if ($targetNumber === '') {
                Log::warning('WhatsApp notification aborted because test mode is enabled without a test number.', [
                    'is_test_mode' => true,
                ]);

                return $this->result(false, self::STATE_FAILED, null, false, null, null, 'missing_test_number', true);
            }
        } else {
            $targetNumber = $this->normalizePhoneNumber($phoneNumber);

            if ($targetNumber === '') {
                Log::warning('WhatsApp notification skipped because phone number is empty.', [
                    'is_test_mode' => false,
                ]);

                return $this->result(false, self::STATE_FAILED, null, false, null, null, 'empty_phone_number', false);
            }
        }

        $token = trim((string) config('services.whatsapp.token', ''));
        $apiUrl = trim((string) config('services.whatsapp.api_url', 'https://api.fonnte.com/send'));

        if ($token === '' || $apiUrl === '') {
            Log::error('WhatsApp notification could not be sent because Fonnte configuration is incomplete.', [
                'has_token' => $token !== '',
                'has_api_url' => $apiUrl !== '',
                'is_test_mode' => $isTestMode,
            ]);

            return $this->result(false, self::STATE_FAILED, null, false, null, null, 'incomplete_configuration', $isTestMode);
        }

        try {
            $response = $this->httpClient($token)->post($apiUrl, [
                'target' => $targetNumber,
                'message' => $message,
            ]);

            $body = $response->json();
            $providerStatus = is_array($body) && ($body['status'] ?? false) === true;
            $providerProcess = is_array($body) && isset($body['process']) ? (string) $body['process'] : null;
            $providerDetail = is_array($body) && isset($body['detail']) ? $this->sanitizeForLog((string) $body['detail'], $token) : null;
            $providerReason = is_array($body) && isset($body['reason']) ? $this->sanitizeForLog((string) $body['reason'], $token) : null;

            if ($response->successful() && $providerStatus) {
                Log::info('WhatsApp notification queued via Fonnte.', [
                    'delivery_state' => self::STATE_QUEUED,
                    'http_status' => $response->status(),
                    'provider_process' => $providerProcess,
                    'provider_detail' => $providerDetail,
                    'is_test_mode' => $isTestMode,
                    'message_length' => mb_strlen($message),
                ]);

                return $this->result(
                    true,
                    self::STATE_QUEUED,
                    $response->status(),
                    true,
                    $providerProcess,
                    $providerDetail,
                    null,
                    $isTestMode
                );
            }

            Log::error('WhatsApp notification failed via Fonnte.', [
                'delivery_state' => self::STATE_FAILED,
                'http_status' => $response->status(),
                'provider_status' => $providerStatus,
                'provider_reason' => $providerReason,
                'is_test_mode' => $isTestMode,
            ]);

            return $this->result(
                false,
                self::STATE_FAILED,
                $response->status(),
                false,
                $providerProcess,
                $providerDetail,
                $providerReason ?? 'provider_rejected',
                $isTestMode
            );
        } catch (Throwable $exception) {
            Log::error('WhatsApp notification request threw an exception.', [
                'delivery_state' => self::STATE_FAILED,
                'exception' => $exception::class,
                'is_test_mode' => $isTestMode,
            ]);

            return $this->result(false, self::STATE_FAILED, null, false, null, null, 'exception', $isTestMode);
        }
    }

    /**
     * Informasi akun ketika Admin membuat akun warga.
     * Parameter $password dipertahankan opsional untuk kompatibilitas pemanggil lama,
     * namun tidak pernah dikirim dalam isi pesan maupun dicatat di log.
     */
    public function sendAccountCredential(
        string $phoneNumber,
        string $name,
        string $username,
        ?string $password = null
    ): bool {
        $result = $this->sendAccountCredentialWithResult(
            $phoneNumber,
            $name,
            $username,
            $password
        );

        return $result['success'];
    }

    /**
     * Informasi akun warga baru dengan hasil pengiriman terstruktur.
     * Password awal hanya digunakan sementara untuk rendering pesan WhatsApp dan
     * tidak pernah dicatat di log aplikasi maupun notification logs.
     */
    public function sendAccountCredentialWithResult(
        string $phoneNumber,
        string $name,
        string $username,
        ?string $password = null
    ): array {
        return $this->sendFromTemplate(
            $phoneNumber,
            WhatsappTemplateService::KEY_ACCOUNT_CREATED,
            [
                'nama' => $name,
                'username' => $username,
                'password_awal' => $password,
            ]
        );
    }

    /**
     * Pengingat iuran yang belum lunas.
     */
    public function sendIuranNotification(
        string $phoneNumber,
        string $name,
        float $nominal,
        string $dueDate,
        ?string $namaIuran = null
    ): bool {
        $result = $this->sendIuranNotificationWithResult(
            $phoneNumber,
            $name,
            $nominal,
            $dueDate,
            $namaIuran
        );

        return $result['success'];
    }

    /**
     * Pengingat iuran yang belum lunas dengan hasil terstruktur.
     */
    public function sendIuranNotificationWithResult(
        string $phoneNumber,
        string $name,
        float $nominal,
        string $dueDate,
        ?string $namaIuran = null,
        ?string $milestoneNote = null
    ): array {
        $formattedNominal = number_format($nominal, 0, ',', '.');

        return $this->sendFromTemplate(
            $phoneNumber,
            WhatsappTemplateService::KEY_IURAN_REMINDER,
            [
                'nama' => $name,
                'nama_iuran' => $namaIuran ?: 'Iuran Kegiatan 17 Agustus',
                'nominal' => $formattedNominal,
                'tanggal' => $dueDate,
                'keterangan' => $milestoneNote ?: 'Mohon melakukan pembayaran sebelum batas waktu.',
            ]
        );
    }

    /**
     * Konfirmasi pembayaran setelah Admin/Panitia memverifikasi pembayaran menjadi Diterima.
     */
    public function sendPaymentConfirmation(
        string $phoneNumber,
        string $name,
        float $nominal,
        ?string $namaIuran = null,
        ?string $verifiedAt = null
    ): bool {
        $result = $this->sendPaymentConfirmationWithResult(
            $phoneNumber,
            $name,
            $nominal,
            $namaIuran,
            $verifiedAt
        );

        return $result['success'];
    }

    /**
     * Konfirmasi pembayaran diterima dengan hasil pengiriman terstruktur.
     */
    public function sendPaymentConfirmationWithResult(
        string $phoneNumber,
        string $name,
        float $nominal,
        ?string $namaIuran = null,
        ?string $verifiedAt = null
    ): array {
        $formattedNominal = number_format($nominal, 0, ',', '.');
        $tanggal = $verifiedAt ? trim(explode(' ', $verifiedAt)[0] ?? $verifiedAt) : now('Asia/Jakarta')->format('d-m-Y');

        return $this->sendFromTemplate(
            $phoneNumber,
            WhatsappTemplateService::KEY_PAYMENT_CONFIRMED,
            [
                'nama' => $name,
                'nama_iuran' => $namaIuran ?: 'Iuran Kegiatan 17 Agustus',
                'nominal' => $formattedNominal,
                'tanggal' => $tanggal,
                'waktu' => $verifiedAt ?: now('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB',
            ]
        );
    }

    /**
     * Pengingat kepada warga yang terdaftar sebagai peserta lomba.
     */
    public function sendLombaParticipantReminder(
        string $phoneNumber,
        string $name,
        string $namaLomba,
        string $jadwal,
        ?string $lokasi = null
    ): bool {
        $result = $this->sendLombaParticipantReminderWithResult(
            $phoneNumber,
            $name,
            $namaLomba,
            $jadwal,
            $lokasi
        );

        return $result['success'];
    }

    /**
     * Pengingat peserta lomba dengan hasil pengiriman terstruktur.
     */
    public function sendLombaParticipantReminderWithResult(
        string $phoneNumber,
        string $name,
        string $namaLomba,
        string $jadwal,
        ?string $lokasi = null,
        ?string $milestoneNote = null
    ): array {
        $parts = explode(',', $jadwal, 2);
        $tanggal = trim($parts[0] ?? $jadwal);
        $waktu = isset($parts[1]) ? trim($parts[1]) : $jadwal;

        return $this->sendFromTemplate(
            $phoneNumber,
            WhatsappTemplateService::KEY_LOMBA_REMINDER,
            [
                'nama' => $name,
                'nama_lomba' => $namaLomba,
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'lokasi' => $lokasi ?: '-',
                'keterangan' => $milestoneNote ?: 'Mohon hadir tepat waktu.',
            ]
        );
    }

    /**
     * Undangan menonton lomba sekitar 30 menit sebelum pertandingan dimulai.
     */
    public function sendMatchWatchInvitation(
        string $phoneNumber,
        string $name,
        string $namaLomba,
        string $waktuMulai,
        ?string $lokasi = null,
        ?string $babak = null
    ): bool {
        $result = $this->sendMatchWatchInvitationWithResult(
            $phoneNumber,
            $name,
            $namaLomba,
            $waktuMulai,
            $lokasi,
            $babak
        );

        return $result['success'];
    }

    /**
     * Undangan menonton lomba dengan hasil pengiriman terstruktur.
     */
    public function sendMatchWatchInvitationWithResult(
        string $phoneNumber,
        string $name,
        string $namaLomba,
        string $waktuMulai,
        ?string $lokasi = null,
        ?string $babak = null,
        ?string $pesertaText = null,
        ?string $rwLabel = null,
        bool $isScheduleUpdate = false
    ): array {
        $wilayah = $rwLabel ?: 'RW 01';
        $pembuka = $isScheduleUpdate
            ? "Pembaruan jadwal pertandingan 17 Agustus {$wilayah}! Pertandingan akan segera dimulai:"
            : "Mari meramaikan dan menyaksikan pertandingan 17 Agustus {$wilayah} yang akan segera dimulai!";

        $parts = explode(',', $waktuMulai, 2);
        $tanggal = trim($parts[0] ?? $waktuMulai);
        $waktu = isset($parts[1]) ? trim($parts[1]) : $waktuMulai;

        return $this->sendFromTemplate(
            $phoneNumber,
            WhatsappTemplateService::KEY_MATCH_INVITATION,
            [
                'nama' => $name,
                'nama_lomba' => $namaLomba,
                'babak' => $babak ?: '-',
                'pertandingan' => $pesertaText ?: '-',
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'lokasi' => $lokasi ?: '-',
                'wilayah' => $wilayah,
                'pembuka' => $pembuka,
            ]
        );
    }

    public function normalizePhoneNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '620')) {
            return '62' . substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    private function httpClient(string $token): PendingRequest
    {
        $timeout = max(1, (int) config('services.whatsapp.timeout', 15));

        return Http::asForm()
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $token,
            ])
            ->timeout($timeout);
    }

    private function sanitizeForLog(string $value, string $token): string
    {
        if ($token !== '' && str_contains($value, $token)) {
            return str_replace($token, '[REDACTED]', $value);
        }

        return $value;
    }

    private function result(
        bool $success,
        string $state,
        ?int $httpStatus,
        bool $providerStatus,
        ?string $providerProcess,
        ?string $providerDetail,
        ?string $reason,
        bool $isTestMode
    ): array {
        return [
            'success' => $success,
            'state' => $state,
            'http_status' => $httpStatus,
            'provider_status' => $providerStatus,
            'provider_process' => $providerProcess,
            'provider_detail' => $providerDetail,
            'reason' => $reason,
            'is_test_mode' => $isTestMode,
        ];
    }
}
