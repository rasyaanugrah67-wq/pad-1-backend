<?php

namespace Tests\Feature;

use App\Models\JadwalLomba;
use App\Models\KategoriLomba;
use App\Models\Lomba;
use App\Models\Pendaftaran;
use App\Models\Periode;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappNotificationLog;
use App\Services\LombaParticipantReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LombaParticipantReminderWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private Rt $rt;
    private Periode $periode;
    private KategoriLomba $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $rw = Rw::firstOrCreate(['nomor_rw' => '94'], ['nama_rw' => 'RW 94']);
        $this->rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '94'], ['nama_rt' => 'RT 94']);
        $this->periode = Periode::firstOrCreate(
            ['tahun' => 2095],
            ['nama_kegiatan' => 'HUT RI Testing 2095', 'status' => 'Aktif']
        );
        $this->kategori = KategoriLomba::firstOrCreate(['nama_kategori' => 'Kategori Test WA']);
    }

    private function configureWhatsApp(array $overrides = []): void
    {
        Config::set('services.whatsapp', array_merge([
            'enabled' => true,
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'token' => 'fake-fonnte-token',
            'timeout' => 15,
            'test_mode' => true,
            'test_number' => '6289900006666',
            'lomba_reminder' => [
                'enabled' => true,
                'time_h1' => '08:00',
                'time_h0' => '07:00',
                'timezone' => 'Asia/Jakarta',
            ],
        ], $overrides));
    }

    private function fakeFonnteSuccess(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);
    }

    private function createWarga(array $overrides = []): Warga
    {
        static $seq = 1;
        $nik = '940000000000' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);

        return Warga::create(array_merge([
            'id_rt' => $this->rt->id_rt,
            'nik' => $nik,
            'nama' => 'Peserta Lomba ' . $seq,
            'no_hp' => '08127777' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ], $overrides));
    }

    private function createLomba(array $overrides = [], bool $isArsip = false): Lomba
    {
        $lomba = Lomba::create(array_merge([
            'id_periode' => $this->periode->id_periode,
            'id_kategori' => $this->kategori->id_kategori,
            'nama_lomba' => 'Tarik Tambang Test WA',
            'jenis_pendaftaran' => 'Individu',
            'status' => 'Dibuka',
        ], $overrides));

        if ($isArsip) {
            $lomba->is_arsip = true;
            $lomba->save();
        }

        return $lomba;
    }

    private function createJadwal(Lomba $lomba, array $overrides = []): JadwalLomba
    {
        return JadwalLomba::create(array_merge([
            'id_lomba' => $lomba->id_lomba,
            'tanggal' => '2026-08-17',
            'waktu_mulai' => '09:00:00',
            'waktu_selesai' => '11:00:00',
            'lokasi' => 'Lapangan RW 01',
        ], $overrides));
    }

    private function createPendaftaran(Warga $warga, Lomba $lomba, string $status = 'Diterima'): Pendaftaran
    {
        return Pendaftaran::create([
            'id_warga' => $warga->id_warga,
            'id_lomba' => $lomba->id_lomba,
            'tanggal_daftar' => now(),
            'status' => $status,
        ]);
    }

    public function test_only_diterima_pendaftaran_receives_reminders_on_h_minus_1_and_h_0_via_test_mode(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $lomba = $this->createLomba(['nama_lomba' => 'Balap Karung']);
        $this->createJadwal($lomba, ['tanggal' => '2026-08-17', 'waktu_mulai' => '09:00:00', 'waktu_selesai' => '11:00:00']);

        $wargaDiterima = $this->createWarga(['nama' => 'Andi Peserta', 'no_hp' => '081233334444']);
        $pDiterima = $this->createPendaftaran($wargaDiterima, $lomba, 'Diterima');
        $this->createPendaftaran($this->createWarga(), $lomba, 'Menunggu');
        $this->createPendaftaran($this->createWarga(), $lomba, 'Ditolak');
        $this->createPendaftaran($this->createWarga(), $lomba, 'Dibatalkan');

        $service = app(LombaParticipantReminderService::class);

        // Non-milestone (H-2 dan H+1) tidak mengirim pesan.
        $this->assertSame(0, $service->sendReminders(Carbon::parse('2026-08-15 08:00:00', 'Asia/Jakarta'), false, null, $lomba->id_lomba)['queued']);
        $this->assertSame(0, $service->sendReminders(Carbon::parse('2026-08-18 07:00:00', 'Asia/Jakarta'), false, null, $lomba->id_lomba)['queued']);
        Http::assertNothingSent();

        // H-1 (08:00 WIB) dan H (07:00 WIB) hanya mengirim kepada pendaftaran Diterima ke WHATSAPP_TEST_NUMBER.
        $h1 = $service->sendReminders(Carbon::parse('2026-08-16 08:00:00', 'Asia/Jakarta'), false, null, $lomba->id_lomba, LombaParticipantReminderService::MILESTONE_H_MINUS_1);
        $h0 = $service->sendReminders(Carbon::parse('2026-08-17 07:00:00', 'Asia/Jakarta'), false, null, $lomba->id_lomba, LombaParticipantReminderService::MILESTONE_H_0);

        $this->assertSame(1, $h1['queued']);
        $this->assertSame(1, $h0['queued']);

        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req['target'] === '6289900006666'
            && str_contains($req['message'], 'Andi Peserta')
            && str_contains($req['message'], 'Balap Karung')
            && str_contains($req['message'], 'Lapangan RW 01'));

        $this->assertSame(2, WhatsappNotificationLog::where('trigger_type', LombaParticipantReminderService::TRIGGER_TYPE)
            ->where('reference_id', $pDiterima->id_pendaftaran)
            ->where('delivery_state', WhatsappNotificationLog::STATE_QUEUED)
            ->count());
    }

    public function test_ineligible_warga_archived_lomba_and_finished_schedule_are_excluded(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $lombaAktif = $this->createLomba();
        $this->createJadwal($lombaAktif, ['tanggal' => '2026-08-17', 'waktu_mulai' => '09:00:00', 'waktu_selesai' => '11:00:00']);

        $pNonaktif = $this->createPendaftaran($this->createWarga(['status' => 'Nonaktif']), $lombaAktif, 'Diterima');
        $pNoHpKosong = $this->createPendaftaran($this->createWarga(['no_hp' => '']), $lombaAktif, 'Diterima');
        $pNoHpInvalid = $this->createPendaftaran($this->createWarga(['no_hp' => '123']), $lombaAktif, 'Diterima');

        $lombaArsip = $this->createLomba(['nama_lomba' => 'Lomba Arsip'], true);
        $this->createJadwal($lombaArsip, ['tanggal' => '2026-08-17', 'waktu_mulai' => '09:00:00', 'waktu_selesai' => '11:00:00']);
        $pArsip = $this->createPendaftaran($this->createWarga(), $lombaArsip, 'Diterima');

        $service = app(LombaParticipantReminderService::class);
        $pagiHariH = Carbon::parse('2026-08-17 07:00:00', 'Asia/Jakarta');

        $this->assertSame(0, $service->sendReminders($pagiHariH, false, $pNonaktif->id_pendaftaran)['queued']);
        $this->assertSame(0, $service->sendReminders($pagiHariH, false, $pNoHpKosong->id_pendaftaran)['queued']);
        $this->assertSame(0, $service->sendReminders($pagiHariH, false, $pNoHpInvalid->id_pendaftaran)['queued']);
        $this->assertSame(0, $service->sendReminders($pagiHariH, false, $pArsip->id_pendaftaran)['queued']);

        // Jadwal sudah selesai pada pukul 12:00 WIB (waktu_selesai 11:00:00) -> tidak boleh dikirim.
        $pValid = $this->createPendaftaran($this->createWarga(), $lombaAktif, 'Diterima');
        $siangLewat = Carbon::parse('2026-08-17 12:00:00', 'Asia/Jakarta');
        $this->assertSame(0, $service->sendReminders($siangLewat, false, $pValid->id_pendaftaran)['queued']);

        Http::assertNothingSent();
    }

    public function test_deduplication_and_schedule_change_handling(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $lomba = $this->createLomba(['nama_lomba' => 'Panjat Pinang']);
        $jadwal = $this->createJadwal($lomba, [
            'tanggal' => '2026-08-17',
            'waktu_mulai' => '09:00:00',
            'waktu_selesai' => '11:00:00',
            'lokasi' => 'Lapangan Utama',
        ]);
        $pendaftaran = $this->createPendaftaran($this->createWarga(), $lomba, 'Diterima');

        $service = app(LombaParticipantReminderService::class);
        $h1Time = Carbon::parse('2026-08-16 08:00:00', 'Asia/Jakarta');

        // Kirim pertama berhasil, kirim kedua untuk jadwal sama dicegah.
        $first = $service->sendReminders($h1Time, false, $pendaftaran->id_pendaftaran);
        $second = $service->sendReminders($h1Time, false, $pendaftaran->id_pendaftaran);

        $this->assertSame(1, $first['queued']);
        $this->assertSame(0, $second['queued']);
        $this->assertSame(1, $second['already_sent']);
        Http::assertSentCount(1);

        // Jadwal diubah (waktu_mulai & lokasi baru) -> fingerprint berubah -> kirim pengingat dengan jadwal terbaru.
        $jadwal->update([
            'waktu_mulai' => '13:30:00',
            'waktu_selesai' => '15:00:00',
            'lokasi' => 'Lapangan Selatan',
        ]);

        $afterChange = $service->sendReminders($h1Time, false, $pendaftaran->id_pendaftaran);
        $this->assertSame(1, $afterChange['queued']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => str_contains($req['message'], '13:30 - 15:00 WIB')
            && str_contains($req['message'], 'Lapangan Selatan'));

        // Jalankan ulang setelah perubahan jadwal -> tidak boleh duplikat lagi.
        $afterChangeAgain = $service->sendReminders($h1Time, false, $pendaftaran->id_pendaftaran);
        $this->assertSame(0, $afterChangeAgain['queued']);
        $this->assertSame(1, $afterChangeAgain['already_sent']);
        Http::assertSentCount(2);
    }

    public function test_multiple_schedules_in_same_lomba_are_handled_independently(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $lomba = $this->createLomba(['nama_lomba' => 'Futsal RW 01']);
        $this->createJadwal($lomba, [
            'tanggal' => '2026-08-17',
            'waktu_mulai' => '08:00:00',
            'waktu_selesai' => '10:00:00',
            'lokasi' => 'Lapangan A',
        ]);
        $this->createJadwal($lomba, [
            'tanggal' => '2026-08-17',
            'waktu_mulai' => '15:00:00',
            'waktu_selesai' => '17:00:00',
            'lokasi' => 'Lapangan B',
        ]);
        $pendaftaran = $this->createPendaftaran($this->createWarga(), $lomba, 'Diterima');

        $service = app(LombaParticipantReminderService::class);
        $summary = $service->sendReminders(
            Carbon::parse('2026-08-17 07:00:00', 'Asia/Jakarta'),
            false,
            $pendaftaran->id_pendaftaran
        );

        $this->assertSame(2, $summary['eligible']);
        $this->assertSame(2, $summary['queued']);
        Http::assertSentCount(2);
    }

    public function test_provider_failure_is_logged_as_failed_without_altering_data_and_can_be_retried(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => false, 'reason' => 'device disconnected'], 500)
                ->push(['status' => true, 'detail' => 'success! message in queue', 'process' => 'pending'], 200),
        ]);

        $lomba = $this->createLomba();
        $jadwal = $this->createJadwal($lomba, ['tanggal' => '2026-08-17']);
        $pendaftaran = $this->createPendaftaran($this->createWarga(), $lomba, 'Diterima');

        $service = app(LombaParticipantReminderService::class);
        $ref = Carbon::parse('2026-08-17 07:00:00', 'Asia/Jakarta');

        $first = $service->sendReminders($ref, false, $pendaftaran->id_pendaftaran);
        $this->assertSame(1, $first['failed']);

        $pendaftaran->refresh();
        $jadwal->refresh();
        $this->assertSame('Diterima', $pendaftaran->status);
        $this->assertSame('2026-08-17', $jadwal->tanggal->toDateString());

        $retry = $service->sendReminders($ref, false, $pendaftaran->id_pendaftaran);
        $this->assertSame(1, $retry['queued']);

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'trigger_type' => LombaParticipantReminderService::TRIGGER_TYPE,
            'reference_id' => $pendaftaran->id_pendaftaran,
            'delivery_state' => WhatsappNotificationLog::STATE_QUEUED,
            'attempts' => 2,
        ]);
    }

    public function test_command_supports_dry_run_pendaftaran_lomba_and_date_filters(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        $lomba = $this->createLomba();
        $this->createJadwal($lomba, ['tanggal' => '2026-08-17']);
        $pendaftaran = $this->createPendaftaran($this->createWarga(), $lomba, 'Diterima');

        $this->artisan('whatsapp:send-lomba-reminders', [
            '--dry-run' => true,
            '--pendaftaran' => $pendaftaran->id_pendaftaran,
            '--lomba' => $lomba->id_lomba,
            '--date' => '2026-08-16',
        ])
            ->expectsOutputToContain('[DRY-RUN] Pengingat peserta lomba (2026-08-16) | Eligible: 1 | Queued: 0 | Would Send: 1')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('whatsapp_notification_logs', [
            'trigger_type' => LombaParticipantReminderService::TRIGGER_TYPE,
            'reference_id' => $pendaftaran->id_pendaftaran,
        ]);
    }
}
