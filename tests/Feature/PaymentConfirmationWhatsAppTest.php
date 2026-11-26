<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Iuran;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Periode;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappNotificationLog;
use App\Services\PaymentConfirmationWhatsAppService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentConfirmationWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private Akun $adminAkun;
    private Rt $rt;
    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['nama_role' => 'Admin']);
        $rw = Rw::firstOrCreate(['nomor_rw' => '93'], ['nama_rw' => 'RW 93']);
        $this->rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '93'], ['nama_rt' => 'RT 93']);
        $this->periode = Periode::firstOrCreate(
            ['tahun' => 2096],
            ['nama_kegiatan' => 'HUT RI Testing 2096', 'status' => 'Aktif']
        );

        $adminWarga = Warga::firstOrCreate(
            ['nik' => '9300000000000001'],
            [
                'id_rt' => $this->rt->id_rt,
                'nama' => 'Admin Verifikator WA',
                'no_hp' => '081200009301',
                'jenis_kelamin' => 'L',
                'status' => 'Aktif',
            ]
        );

        $this->adminAkun = Akun::firstOrCreate(
            ['username' => 'admin-verifikator-wa'],
            [
                'id_warga' => $adminWarga->id_warga,
                'id_role' => $adminRole->id_role,
                'password' => Hash::make('secret123'),
                'status' => 'Aktif',
            ]
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
            'test_number' => '6289900007777',
        ], $overrides));
    }

    private function createPendingPayment(array $wargaOverrides = [], array $iuranOverrides = []): Pembayaran
    {
        static $seq = 10;
        $nik = '930000000000' . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);

        $warga = Warga::create(array_merge([
            'id_rt' => $this->rt->id_rt,
            'nik' => $nik,
            'nama' => 'Warga Bayar ' . $seq,
            'no_hp' => '08129999' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ], $wargaOverrides));

        $iuran = Iuran::create(array_merge([
            'id_warga' => $warga->id_warga,
            'id_periode' => $this->periode->id_periode,
            'nama_iuran' => 'Iuran Kemerdekaan 2096',
            'nominal' => 50000,
            'jatuh_tempo' => '2026-08-15',
            'status' => 'Belum Bayar',
            'is_aktif' => true,
        ], $iuranOverrides));

        return Pembayaran::create([
            'id_iuran' => $iuran->id_iuran,
            'tanggal_bayar' => now(),
            'nominal' => $iuran->nominal,
            'metode' => 'QRIS',
            'status_verifikasi' => 'Menunggu',
        ]);
    }

    public function test_approved_payment_sends_single_whatsapp_to_test_number(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        $pembayaran = $this->createPendingPayment(['nama' => 'Slamet Riyadi', 'no_hp' => '081211112222']);

        Sanctum::actingAs($this->adminAkun);

        $response = $this->patchJson("/api/manajemen/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", [
            'keputusan' => 'Diterima',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_verifikasi', 'Diterima')
            ->assertJsonPath('data.status_iuran', 'Sudah Bayar');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request['target'] === '6289900007777'
                && str_contains($request['message'], 'Slamet Riyadi')
                && str_contains($request['message'], 'Iuran Kemerdekaan 2096')
                && str_contains($request['message'], 'Rp50.000')
                && str_contains($request['message'], 'Sudah Bayar');
        });

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'trigger_type' => PaymentConfirmationWhatsAppService::TRIGGER_TYPE,
            'reference_type' => 'pembayaran',
            'reference_id' => $pembayaran->id_pembayaran,
            'dedup_key' => 'payment_confirmed:' . $pembayaran->id_pembayaran,
            'delivery_state' => WhatsappNotificationLog::STATE_QUEUED,
            'is_test_mode' => true,
        ]);
    }

    public function test_rejected_payment_does_not_send_confirmation_whatsapp(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        $pembayaran = $this->createPendingPayment();

        Sanctum::actingAs($this->adminAkun);

        $this->patchJson("/api/manajemen/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", [
            'keputusan' => 'Ditolak',
        ])
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi', 'Ditolak')
            ->assertJsonPath('data.status_iuran', 'Belum Bayar');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('whatsapp_notification_logs', [
            'dedup_key' => 'payment_confirmed:' . $pembayaran->id_pembayaran,
        ]);
    }

    public function test_fonnte_failure_does_not_rollback_payment_verification(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => false,
                'reason' => 'device disconnected',
            ], 500),
        ]);

        $pembayaran = $this->createPendingPayment();

        Sanctum::actingAs($this->adminAkun);

        $this->patchJson("/api/manajemen/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", [
            'keputusan' => 'Diterima',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_verifikasi', 'Diterima')
            ->assertJsonPath('data.status_iuran', 'Sudah Bayar');

        $pembayaran->refresh();
        $this->assertSame('Diterima', $pembayaran->status_verifikasi);
        $this->assertSame('Sudah Bayar', $pembayaran->iuran->status);

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'dedup_key' => 'payment_confirmed:' . $pembayaran->id_pembayaran,
            'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
            'attempts' => 1,
        ]);
    }

    public function test_empty_phone_or_inactive_warga_does_not_receive_whatsapp(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        $p1 = $this->createPendingPayment(['no_hp' => '   ']);
        $p2 = $this->createPendingPayment(['status' => 'Nonaktif']);

        Sanctum::actingAs($this->adminAkun);

        $this->patchJson("/api/manajemen/pembayaran/{$p1->id_pembayaran}/verifikasi", ['keputusan' => 'Diterima'])->assertOk();
        $this->patchJson("/api/manajemen/pembayaran/{$p2->id_pembayaran}/verifikasi", ['keputusan' => 'Diterima'])->assertOk();

        Http::assertNothingSent();
    }

    public function test_deduplication_prevents_sending_twice_for_same_payment_and_skips_stale_processing(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        $pembayaran = $this->createPendingPayment();

        Sanctum::actingAs($this->adminAkun);
        $this->patchJson("/api/manajemen/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", ['keputusan' => 'Diterima'])->assertOk();

        // Pemanggilan ulang langsung ke service untuk id_pembayaran yang sama tidak boleh mengirim ulang.
        $service = app(PaymentConfirmationWhatsAppService::class);
        $second = $service->sendForApprovedPayment($pembayaran->fresh());

        $this->assertSame('already_handled', $second['action']);
        $this->assertSame('already_queued', $second['reason']);
        Http::assertSentCount(1);

        // Simulasikan log processing tertinggal pada pembayaran lain -> tidak boleh di-retry otomatis.
        $p2 = $this->createPendingPayment();
        $p2->update(['status_verifikasi' => 'Diterima', 'tanggal_verifikasi' => now()]);
        $p2->iuran->update(['status' => 'Sudah Bayar']);

        WhatsappNotificationLog::create([
            'trigger_type' => PaymentConfirmationWhatsAppService::TRIGGER_TYPE,
            'reference_type' => 'pembayaran',
            'reference_id' => $p2->id_pembayaran,
            'id_warga' => $p2->iuran->id_warga,
            'dedup_key' => $service->buildDedupKey($p2->id_pembayaran),
            'delivery_state' => WhatsappNotificationLog::STATE_PROCESSING,
            'is_test_mode' => true,
            'attempts' => 1,
        ]);

        $staleResult = $service->sendForApprovedPayment($p2->fresh());
        $this->assertSame('already_handled', $staleResult['action']);
        $this->assertSame('processing_requires_manual_check', $staleResult['reason']);
        Http::assertSentCount(1);
    }

    public function test_retry_failed_log_sends_whatsapp_without_repeating_payment_verification(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => false, 'reason' => 'temporary error'], 500)
                ->push(['status' => true, 'detail' => 'success! message in queue', 'process' => 'pending'], 200),
        ]);

        $pembayaran = $this->createPendingPayment();

        Sanctum::actingAs($this->adminAkun);
        $this->patchJson("/api/manajemen/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", ['keputusan' => 'Diterima'])->assertOk();

        $pembayaran->refresh();
        $originalVerifiedAt = $pembayaran->tanggal_verifikasi?->toIso8601String();
        $inAppNotifCountBefore = Notifikasi::where('id_warga', $pembayaran->iuran->id_warga)->count();

        // Jalankan command retry khusus log failed.
        $this->artisan('whatsapp:retry-payment-confirmations', [
            '--pembayaran' => $pembayaran->id_pembayaran,
        ])
            ->expectsOutputToContain('Queued: 1')
            ->assertExitCode(0);

        $pembayaran->refresh();
        $this->assertSame($originalVerifiedAt, $pembayaran->tanggal_verifikasi?->toIso8601String());
        $this->assertSame($inAppNotifCountBefore, Notifikasi::where('id_warga', $pembayaran->iuran->id_warga)->count());

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'dedup_key' => 'payment_confirmed:' . $pembayaran->id_pembayaran,
            'delivery_state' => WhatsappNotificationLog::STATE_QUEUED,
            'attempts' => 2,
        ]);
        Http::assertSentCount(2);
    }
}
