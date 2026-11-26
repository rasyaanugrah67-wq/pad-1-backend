<?php

namespace App\Services;

use App\Models\WhatsappTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappTemplateService
{
    public const KEY_ACCOUNT_CREATED = 'account_created';
    public const KEY_IURAN_REMINDER = 'iuran_reminder';
    public const KEY_PAYMENT_CONFIRMED = 'payment_confirmed';
    public const KEY_LOMBA_REMINDER = 'lomba_reminder';
    public const KEY_MATCH_INVITATION = 'match_invitation';

    /**
     * Definisi default, placeholder yang diizinkan, dan contoh data preview untuk 5 template.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            self::KEY_ACCOUNT_CREATED => [
                'title' => 'Informasi Akun Warga Baru',
                'description' => 'Dikirim otomatis setelah Admin menambahkan data warga dan akun login baru.',
                'body' => "Halo {nama}!\n\nAkun aplikasi 17-an Mobile Anda telah berhasil dibuat oleh Admin.\n\nBerikut informasi login akun Anda:\n\nUsername: {username}\nPassword awal: {password_awal}\n\nSilakan login menggunakan informasi tersebut.\n\nDemi keamanan akun, mohon segera ganti password awal melalui menu Profil setelah berhasil login.\n\nTerima kasih.\nAdmin PAD",
                'placeholders' => ['nama', 'username', 'password_awal'],
                'sample_data' => [
                    'nama' => 'Budi Santoso',
                    'username' => 'BudiSantoso',
                    'password_awal' => 'ContohPassword123!',
                ],
            ],
            self::KEY_IURAN_REMINDER => [
                'title' => 'Pengingat Iuran Belum Lunas',
                'description' => 'Dikirim otomatis pada H-3, H, dan H+7 jatuh tempo kepada warga yang iurannya belum lunas.',
                'body' => "Halo Bapak/Ibu {nama},\n\nKami mengingatkan bahwa tagihan {nama_iuran} Anda belum lunas.\n\nNominal: Rp{nominal}\nBatas pembayaran: {tanggal}\nKeterangan: {keterangan}\n\nMohon segera melakukan pembayaran dan mengunggah bukti pembayaran melalui Website Perlombaan 17 Agustus sebelum batas waktu.\n\nTerima kasih atas kerja samanya.",
                'placeholders' => ['nama', 'nama_iuran', 'nominal', 'tanggal', 'keterangan'],
                'sample_data' => [
                    'nama' => 'Budi Santoso',
                    'nama_iuran' => 'Iuran HUT RI 2026',
                    'nominal' => '50.000',
                    'tanggal' => '10-08-2026',
                    'keterangan' => 'Pengingat H-3 sebelum jatuh tempo pembayaran.',
                ],
            ],
            self::KEY_PAYMENT_CONFIRMED => [
                'title' => 'Konfirmasi Pembayaran Diterima',
                'description' => 'Dikirim otomatis setelah Admin atau Panitia memverifikasi pembayaran menjadi Diterima.',
                'body' => "Halo Bapak/Ibu {nama},\n\nTerima kasih, pembayaran {nama_iuran} Anda telah diverifikasi dan diterima oleh Panitia.\n\nNominal: Rp{nominal}\nStatus iuran: Sudah Bayar\nWaktu verifikasi: {waktu}\n\nTerima kasih atas partisipasi dan dukungan Anda untuk kegiatan 17 Agustus.",
                'placeholders' => ['nama', 'nama_iuran', 'nominal', 'tanggal', 'waktu'],
                'sample_data' => [
                    'nama' => 'Siti Aminah',
                    'nama_iuran' => 'Iuran HUT RI 2026',
                    'nominal' => '50.000',
                    'tanggal' => '05-08-2026',
                    'waktu' => '05-08-2026 10:15 WIB',
                ],
            ],
            self::KEY_LOMBA_REMINDER => [
                'title' => 'Pengingat Peserta Lomba',
                'description' => 'Dikirim otomatis pada H-1 dan hari H kepada peserta lomba yang pendaftarannya berstatus Diterima.',
                'body' => "Halo {nama},\n\nIni adalah pengingat bahwa Anda terdaftar sebagai peserta lomba 17 Agustus:\n\nLomba: {nama_lomba}\nTanggal: {tanggal}\nWaktu: {waktu}\nLokasi: {lokasi}\nKeterangan: {keterangan}\n\nMohon hadir tepat waktu dan persiapkan diri dengan baik. Sampai jumpa di arena lomba!",
                'placeholders' => ['nama', 'nama_lomba', 'tanggal', 'waktu', 'lokasi', 'keterangan'],
                'sample_data' => [
                    'nama' => 'Andi Pratama',
                    'nama_lomba' => 'Tarik Tambang',
                    'tanggal' => '17-08-2026',
                    'waktu' => '09:00 - 11:00 WIB',
                    'lokasi' => 'Lapangan RW 01',
                    'keterangan' => 'Pengingat H-1 sebelum pelaksanaan lomba.',
                ],
            ],
            self::KEY_MATCH_INVITATION => [
                'title' => 'Undangan Menonton Pertandingan',
                'description' => 'Dikirim otomatis sekitar 30 menit sebelum pertandingan dimulai kepada seluruh warga aktif RW penyelenggara.',
                'body' => "Halo Bapak/Ibu {nama},\n\n{pembuka}\n\nLomba: {nama_lomba}\nBabak: {babak}\nPertandingan: {pertandingan}\nJadwal: {tanggal}, {waktu}\nLokasi: {lokasi}\n\nMari ajak keluarga dan tetangga untuk meramaikan serta mendukung para peserta!",
                'placeholders' => ['nama', 'nama_lomba', 'babak', 'pertandingan', 'tanggal', 'waktu', 'lokasi', 'wilayah', 'pembuka'],
                'sample_data' => [
                    'nama' => 'Budi Santoso',
                    'nama_lomba' => 'Futsal Sarung',
                    'babak' => 'Final',
                    'pertandingan' => 'Tim RT 01 vs Tim RT 02',
                    'tanggal' => '17-08-2026',
                    'waktu' => '15:30 WIB',
                    'lokasi' => 'Lapangan RW 01',
                    'wilayah' => 'RW 01',
                    'pembuka' => 'Mari meramaikan dan menyaksikan pertandingan 17 Agustus RW 01 yang akan segera dimulai!',
                ],
            ],
        ];
    }

    public function isValidKey(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /**
     * @return array<int, string>
     */
    public function allowedPlaceholders(string $key): array
    {
        return $this->definitions()[$key]['placeholders'] ?? [];
    }

    /**
     * Mencari placeholder {xxx} yang tidak terdaftar untuk template key tertentu.
     *
     * @return array<int, string>
     */
    public function findInvalidPlaceholders(string $key, string $text): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $text, $matches);
        $found = array_unique($matches[1] ?? []);
        $allowed = $this->allowedPlaceholders($key);

        return array_values(array_diff($found, $allowed));
    }

    /**
     * Memastikan 5 template default tersedia di database dan mengembalikannya terurut.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function allTemplates(): Collection
    {
        $definitions = $this->definitions();
        $existing = WhatsappTemplate::with('updater:id_akun,username')
            ->whereIn('key', array_keys($definitions))
            ->get()
            ->keyBy('key');

        $result = [];
        foreach ($definitions as $key => $def) {
            $template = $existing->get($key);
            if (!$template) {
                $template = WhatsappTemplate::firstOrCreate(
                    ['key' => $key],
                    [
                        'title' => $def['title'],
                        'body' => $def['body'],
                        'description' => $def['description'],
                        'is_active' => true,
                    ]
                );
            }

            $result[] = $this->formatTemplate($template);
        }

        return collect($result);
    }

    public function findByKey(string $key): ?WhatsappTemplate
    {
        if (!$this->isValidKey($key)) {
            return null;
        }

        $def = $this->definitions()[$key];

        return WhatsappTemplate::with('updater:id_akun,username')->firstOrCreate(
            ['key' => $key],
            [
                'title' => $def['title'],
                'body' => $def['body'],
                'description' => $def['description'],
                'is_active' => true,
            ]
        );
    }

    public function formatTemplate(WhatsappTemplate $template): array
    {
        $def = $this->definitions()[$template->key] ?? [
            'placeholders' => [],
            'sample_data' => [],
        ];

        return [
            'id' => $template->id,
            'key' => $template->key,
            'title' => $template->title,
            'body' => $template->body,
            'description' => $template->description,
            'is_active' => (bool) $template->is_active,
            'allowed_placeholders' => array_map(fn (string $p) => '{' . $p . '}', $def['placeholders']),
            'sample_data' => $def['sample_data'],
            'preview' => $this->renderText($template->key, $template->body, $def['sample_data']),
            'updated_by' => $template->updater?->username,
            'updated_at' => $template->updated_at,
        ];
    }

    public function preview(string $key, ?string $customTitle = null, ?string $customBody = null): ?array
    {
        $template = $this->findByKey($key);
        if (!$template) {
            return null;
        }

        $def = $this->definitions()[$key];
        $title = $customTitle !== null ? $customTitle : $template->title;
        $body = $customBody !== null ? $customBody : $template->body;

        return [
            'key' => $key,
            'title' => $title,
            'is_active' => (bool) $template->is_active,
            'allowed_placeholders' => array_map(fn (string $p) => '{' . $p . '}', $def['placeholders']),
            'sample_data' => $def['sample_data'],
            'rendered_message' => $this->renderText($key, $body, $def['sample_data']),
        ];
    }

    /**
     * Mengambil dan merender pesan dari database untuk dikirim oleh trigger WhatsApp.
     *
     * - Jika template ada di DB dengan is_active = false -> can_send = false (blokir pengiriman).
     * - Jika tabel/record tidak tersedia (mis. sebelum migration) -> fallback aman ke default
     *   agar transaksi utama tidak pernah gagal.
     *
     * @param  array<string, string|null>  $variables
     * @return array{can_send: bool, reason: string|null, message: string|null}
     */
    public function resolveMessage(string $key, array $variables): array
    {
        $def = $this->definitions()[$key] ?? null;
        if (!$def) {
            return [
                'can_send' => false,
                'reason' => 'unknown_template',
                'message' => null,
            ];
        }

        $body = $def['body'];

        try {
            $template = WhatsappTemplate::where('key', $key)->first();

            if ($template) {
                if (!$template->is_active) {
                    return [
                        'can_send' => false,
                        'reason' => 'template_disabled',
                        'message' => null,
                    ];
                }

                if (trim((string) $template->body) !== '') {
                    $body = (string) $template->body;
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to load WhatsApp template from database; falling back to default template.', [
                'template_key' => $key,
                'exception' => $exception::class,
            ]);
        }

        return [
            'can_send' => true,
            'reason' => null,
            'message' => $this->renderText($key, $body, $variables),
        ];
    }

    /**
     * Mengisi seluruh placeholder yang diizinkan dan memastikan tidak ada placeholder mentah tersisa.
     *
     * @param  array<string, string|null>  $variables
     */
    public function renderText(string $key, string $templateBody, array $variables): string
    {
        $allowed = $this->allowedPlaceholders($key);
        $replacements = [];

        foreach ($allowed as $placeholder) {
            $value = trim((string) ($variables[$placeholder] ?? ''));
            $replacements['{' . $placeholder . '}'] = $value !== '' ? $value : '-';
        }

        $rendered = strtr($templateBody, $replacements);

        // Proteksi akhir: pastikan tidak ada placeholder mentah {xxx} yang terkirim ke warga.
        $rendered = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $rendered) ?? $rendered;

        // Rapikan spasi/baris kosong berlebih jika ada.
        $rendered = preg_replace("/\n{3,}/", "\n\n", $rendered) ?? $rendered;

        return trim($rendered);
    }
}
