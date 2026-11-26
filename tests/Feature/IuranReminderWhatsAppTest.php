<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Iuran;
use App\Models\Pembayaran;
use App\Models\Periode;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappNotificationLog;
use App\Services\IuranReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IuranReminderWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private Rt $rt;
    private Periode $periode;
    private Role $wargaRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wargaRole = Role::firstOrCreate(['nama_role' => 'Warga']);
        $rw = Rw::firstOrCreate(['nomor_rw' => '92'], ['nama_rw' => 'RW 92']);
        $this->rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '92'], ['nama_rt' => 'RT 92']);
        $this->periode = Periode::firstOrCreate(
            ['tahun' => 2097],
            ['nama_kegiatan' => 'HUT RI Testing 2097', 'status' => 'Aktif']
        );
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
            'test_number' => '6289900008888',
            'iuran_reminder' => [
                'enabled' => true,
                'time' => '08:00',
                'timezone' => 'Asia/Jakarta',
            ],
        ], $overrides));
    }

    private function createWarga(array $overrides = []): Warga
    {
        static $seq = 1;
        $nik = '920000000000' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);

        return Warga::create(array_merge([
            'id_rt' => $this->rt->id_rt,
            'nik' => $nik,
            'nama' => 'Warga Iuran ' . $seq,
            'no_hp' => '08123456' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ], $overrides));
    }

    private function createIuran(Warga $warga, array $overrides = []): Iuran
    {
        return Iuran::create(array_merge([
            'id_warga' => $warga->id_warga,
            'id_periode' => $this->periode->id_periode,
            'nama_iuran' => 'Iuran HUT RI 2097',
            'nominal' => 50000,
            'jatuh_tempo' => '2026-08-10',
            'status' => 'Belum Bayar',
            'is_aktif' => true,
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

    public function test_eligible_iuran_receives_reminders_only_on_h_minus_3_h_0_and_h_plus_7(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga(['nama' => 'Budi Layak', 'no_hp' => '081200001111']);
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10']);

        $service = app(IuranReminderService::class);

        // Jadwal non-milestone (H-7, H-1, H+1) tidak boleh mengirim pesan.
        $this->assertSame(0, $service->sendReminders(Carbon::parse('2026-08-03', 'Asia/Jakarta'), false, $iuran->id_iuran)['queued']);
        $this->assertSame(0, $service->sendReminders(Carbon::parse('2026-08-09', 'Asia/Jakarta'), false, $iuran->id_iuran)['queued']);
        $this->assertSame(0, $service->sendReminders(Carbon::parse('2026-08-11', 'Asia/Jakarta'), false, $iuran->id_iuran)['queued']);
        Http::assertNothingSent();

        // H-3 (2026-08-07), H (2026-08-10), dan H+7 (2026-08-17) mengirim tepat 1 pesan per milestone ke nomor testing.
        $r1 = $service->sendReminders(Carbon::parse('2026-08-07', 'Asia/Jakarta'), false, $iuran->id_iuran);
        $r2 = $service->sendReminders(Carbon::parse('2026-08-10', 'Asia/Jakarta'), false, $iuran->id_iuran);
        $r3 = $service->sendReminders(Carbon::parse('2026-08-17', 'Asia/Jakarta'), false, $iuran->id_iuran);

        $this->assertSame(1, $r1['queued']);
        $this->assertSame(1, $r2['queued']);
        $this->assertSame(1, $r3['queued']);

        Http::assertSentCount(3);
        Http::assertSent(fn ($req) => $req['target'] === '6289900008888' && str_contains($req['message'], 'Budi Layak'));

        $this->assertSame(3, WhatsappNotificationLog::where('reference_id', $iuran->id_iuran)
            ->where('delivery_state', WhatsappNotificationLog::STATE_QUEUED)
            ->count());
    }

    public function test_paid_iuran_does_not_receive_reminder(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga();
        $iuran = $this->createIuran($warga, [
            'jatuh_tempo' => '2026-08-10',
            'status' => 'Sudah Bayar',
        ]);

        $summary = app(IuranReminderService::class)->sendReminders(
            Carbon::parse('2026-08-10', 'Asia/Jakarta'),
            false,
            $iuran->id_iuran
        );

        $this->assertSame(0, $summary['eligible']);
        $this->assertSame(0, $summary['queued']);
        Http::assertNothingSent();
    }

    public function test_pending_payment_pauses_reminder(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga();
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10']);

        Pembayaran::create([
            'id_iuran' => $iuran->id_iuran,
            'tanggal_bayar' => now(),
            'nominal' => 50000,
            'metode' => 'QRIS',
            'status_verifikasi' => 'Menunggu',
        ]);

        $summary = app(IuranReminderService::class)->sendReminders(
            Carbon::parse('2026-08-10', 'Asia/Jakarta'),
            false,
            $iuran->id_iuran
        );

        $this->assertSame(0, $summary['eligible']);
        Http::assertNothingSent();
    }

    public function test_rejected_payment_remains_eligible_and_does_not_resend_old_milestone(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga();
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10']);

        $service = app(IuranReminderService::class);

        // Milestone H-3 terkirim lebih dulu.
        $this->assertSame(1, $service->sendReminders(Carbon::parse('2026-08-07', 'Asia/Jakarta'), false, $iuran->id_iuran)['queued']);

        // Pembayaran diajukan lalu ditolak.
        Pembayaran::create([
            'id_iuran' => $iuran->id_iuran,
            'tanggal_bayar' => now(),
            'nominal' => 50000,
            'metode' => 'QRIS',
            'status_verifikasi' => 'Ditolak',
        ]);

        // Milestone H-3 lama TIDAK boleh terkirim ulang.
        $retryOld = $service->sendReminders(Carbon::parse('2026-08-07', 'Asia/Jakarta'), false, $iuran->id_iuran);
        $this->assertSame(0, $retryOld['queued']);
        $this->assertSame(1, $retryOld['already_sent']);

        // Milestone berikutnya (H) TETAP layak dan terkirim.
        $nextMilestone = $service->sendReminders(Carbon::parse('2026-08-10', 'Asia/Jakarta'), false, $iuran->id_iuran);
        $this->assertSame(1, $nextMilestone['queued']);

        Http::assertSentCount(2);
    }

    public function test_inactive_warga_empty_phone_and_inactive_iuran_are_skipped(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $wargaNonaktif = $this->createWarga(['status' => 'Nonaktif']);
        $iuran1 = $this->createIuran($wargaNonaktif, ['jatuh_tempo' => '2026-08-10']);

        $wargaTanpaHp = $this->createWarga(['no_hp' => '   ']);
        $iuran2 = $this->createIuran($wargaTanpaHp, ['jatuh_tempo' => '2026-08-10']);

        $wargaAktif = $this->createWarga();
        $iuranNonaktif = $this->createIuran($wargaAktif, [
            'jatuh_tempo' => '2026-08-10',
            'is_aktif' => false,
        ]);

        $service = app(IuranReminderService::class);
        $today = Carbon::parse('2026-08-10', 'Asia/Jakarta');

        $this->assertSame(0, $service->sendReminders($today, false, $iuran1->id_iuran)['eligible']);
        $this->assertSame(0, $service->sendReminders($today, false, $iuran2->id_iuran)['eligible']);
        $this->assertSame(0, $service->sendReminders($today, false, $iuranNonaktif->id_iuran)['eligible']);
        Http::assertNothingSent();
    }

    public function test_duplicate_milestone_is_prevented_including_processing_state(): void
    {
        $this->configureWhatsApp();
        $this->fakeFonnteSuccess();

        $warga = $this->createWarga();
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10']);

        $service = app(IuranReminderService::class);
        $today = Carbon::parse('2026-08-10', 'Asia/Jakarta');

        $first = $service->sendReminders($today, false, $iuran->id_iuran);
        $second = $service->sendReminders($today, false, $iuran->id_iuran);

        $this->assertSame(1, $first['queued']);
        $this->assertSame(0, $second['queued']);
        $this->assertSame(1, $second['already_sent']);
        Http::assertSentCount(1);

        // Simulasikan milestone H+7 sedang dalam status processing oleh proses lain -> tidak boleh dikirim ganda.
        WhatsappNotificationLog::create([
            'trigger_type' => IuranReminderService::TRIGGER_TYPE,
            'reference_type' => 'iuran',
            'reference_id' => $iuran->id_iuran,
            'id_warga' => $warga->id_warga,
            'dedup_key' => $service->buildDedupKey($iuran->id_iuran, IuranReminderService::MILESTONE_H_PLUS_7),
            'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
            'is_test_mode' => true,
            'attempts' => 1,
        ]);

        $concurrent = $service->sendReminders(Carbon::parse('2026-08-17', 'Asia/Jakarta'), false, $iuran->id_iuran);
        $this->assertSame(0, $concurrent['queued']);
        $this->assertSame(1, $concurrent['already_sent']);
        Http::assertSentCount(1);
    }

    public function test_failed_reminder_can_be_retried_and_does_not_alter_iuran_data(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => false, 'reason' => 'device disconnected'], 500)
                ->push(['status' => true, 'detail' => 'success! message in queue', 'process' => 'pending'], 200),
        ]);

        $warga = $this->createWarga();
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10', 'nominal' => 75000]);

        $service = app(IuranReminderService::class);
        $today = Carbon::parse('2026-08-10', 'Asia/Jakarta');

        // Percobaan pertama gagal -> log berstatus failed dan data iuran tidak berubah.
        $first = $service->sendReminders($today, false, $iuran->id_iuran);
        $this->assertSame(1, $first['failed']);
        $this->assertSame(0, $first['queued']);

        $iuran->refresh();
        $this->assertSame('Belum Bayar', $iuran->status);
        $this->assertSame('75000.00', (string) $iuran->nominal);
        $this->assertTrue($iuran->is_aktif);

        $log = WhatsappNotificationLog::where('reference_id', $iuran->id_iuran)->first();
        $this->assertNotNull($log);
        $this->assertSame(WhatsappNotificationLog::STATE_FAILED, $log->delivery_state);
        $this->assertSame(1, $log->attempts);

        // Percobaan ulang berhasil -> status menjadi queued dengan attempts = 2.
        $retry = $service->sendReminders($today, false, $iuran->id_iuran);
        $this->assertSame(1, $retry['queued']);

        $log->refresh();
        $this->assertSame(WhatsappNotificationLog::STATE_QUEUED, $log->delivery_state);
        $this->assertSame(2, $log->attempts);
        $this->assertNotNull($log->sent_at);
    }

    public function test_dry_run_command_does_not_send_whatsapp_or_record_queued_log(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        $warga = $this->createWarga();
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10']);

        $this->artisan('whatsapp:send-iuran-reminders', [
            '--dry-run' => true,
            '--iuran' => $iuran->id_iuran,
            '--date' => '2026-08-10',
        ])
            ->expectsOutputToContain('[DRY-RUN] Pengingat iuran (2026-08-10) | Eligible: 1 | Queued: 0 | Would Send: 1')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('whatsapp_notification_logs', [
            'reference_id' => $iuran->id_iuran,
        ]);
    }

    public function test_warga_can_still_resubmit_payment_after_rejection(): void
    {
        $warga = $this->createWarga();
        $akun = Akun::create([
            'id_warga' => $warga->id_warga,
            'id_role' => $this->wargaRole->id_role,
            'username' => 'warga-resubmit-' . $warga->id_warga,
            'password' => Hash::make('secret123'),
            'status' => 'Aktif',
        ]);
        $iuran = $this->createIuran($warga, ['jatuh_tempo' => '2026-08-10', 'nominal' => 50000]);

        // Pembayaran sebelumnya ditolak oleh Admin/Panitia.
        Pembayaran::create([
            'id_iuran' => $iuran->id_iuran,
            'tanggal_bayar' => now()->subDay(),
            'nominal' => 50000,
            'metode' => 'QRIS',
            'status_verifikasi' => 'Ditolak',
        ]);

        Sanctum::actingAs($akun);

        // Uji bahwa endpoint bayar TIDAK mengunci warga dengan 409 Conflict setelah pembayaran Ditolak
        // (mencapai validasi input 422 secara normal tanpa memanggil Cloudinary eksternal).
        $response = $this->postJson("/api/warga/iuran/{$iuran->id_iuran}/pembayaran", [
            'metode' => 'QRIS',
            'nominal' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bukti_bayar']);
    }
}
