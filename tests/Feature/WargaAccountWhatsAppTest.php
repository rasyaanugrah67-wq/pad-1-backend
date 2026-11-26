<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappNotificationLog;
use App\Services\AccountCreatedWhatsAppService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WargaAccountWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private Akun $adminAkun;
    private Rt $rt;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['nama_role' => 'Admin']);
        Role::firstOrCreate(['nama_role' => 'Warga']);

        $rw = Rw::firstOrCreate(['nomor_rw' => '91'], ['nama_rw' => 'RW 91']);
        $this->rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '91'], ['nama_rt' => 'RT 91']);

        $adminWarga = Warga::firstOrCreate(
            ['nik' => '9100000000000001'],
            [
                'id_rt' => $this->rt->id_rt,
                'nama' => 'Admin Test WA',
                'no_hp' => '081200000001',
                'jenis_kelamin' => 'L',
                'status' => 'Aktif',
            ]
        );

        $this->adminAkun = Akun::firstOrCreate(
            ['username' => 'admin-test-wa-account'],
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
            'test_number' => '6289900009999',
        ], $overrides));
    }

    public function test_username_format_preserves_capitalization_removes_spaces_and_dots_and_handles_duplicates(): void
    {
        $this->configureWhatsApp(['enabled' => false]);
        Http::fake();

        // Akun lama dengan format titik tetap tidak berubah.
        $wargaRole = Role::where('nama_role', 'Warga')->firstOrFail();
        $wargaLama = Warga::create([
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000099',
            'nama' => 'Akun Lama',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ]);
        $akunLama = Akun::create([
            'id_warga' => $wargaLama->id_warga,
            'id_role' => $wargaRole->id_role,
            'username' => 'akun.lama',
            'password' => Hash::make('oldsecret'),
            'status' => 'Aktif',
        ]);

        Sanctum::actingAs($this->adminAkun);

        $r1 = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000021',
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
        ])->assertCreated();
        $this->assertSame('BudiSantoso', $r1->json('data.akun.username'));

        $r2 = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000022',
            'nama' => 'RASYA ANUGRAH',
            'jenis_kelamin' => 'L',
        ])->assertCreated();
        $this->assertSame('RASYAANUGRAH', $r2->json('data.akun.username'));

        $r3 = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000023',
            'nama' => 'Dr. Siti. Aminah',
            'jenis_kelamin' => 'P',
        ])->assertCreated();
        $this->assertSame('DrSitiAminah', $r3->json('data.akun.username'));

        // Nama duplikat Budi Santoso -> BudiSantoso2
        $r4 = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000024',
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
        ])->assertCreated();
        $this->assertSame('BudiSantoso2', $r4->json('data.akun.username'));

        // Akun lama tidak berubah sama sekali.
        $this->assertSame('akun.lama', $akunLama->fresh()->username);
    }

    public function test_creating_warga_hashes_password_sends_whatsapp_with_template_and_hides_password_from_api_and_logs(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        Sanctum::actingAs($this->adminAkun);

        $response = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000010',
            'nama' => 'Warga Uji Fonnte',
            'no_hp' => '081234567899',
            'jenis_kelamin' => 'L',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warga.nama', 'Warga Uji Fonnte')
            ->assertJsonPath('data.akun.username', 'WargaUjiFonnte')
            ->assertJsonMissingPath('data.akun.password_awal');

        $expectedPassword = AccountCreatedWhatsAppService::DEFAULT_INITIAL_PASSWORD;
        $this->assertStringNotContainsString($expectedPassword, (string) $response->getContent());

        // Password tersimpan dalam bentuk hash di database.
        $idAkun = $response->json('data.akun.id_akun');
        $akun = Akun::findOrFail($idAkun);
        $this->assertNotSame($expectedPassword, $akun->password);
        $this->assertTrue(Hash::check($expectedPassword, $akun->password));

        // WhatsApp menerima username dan password awal melalui template account_created.
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($expectedPassword) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request['target'] === '6289900009999'
                && str_contains($request['message'], 'Warga Uji Fonnte')
                && str_contains($request['message'], 'Username: WargaUjiFonnte')
                && str_contains($request['message'], 'Password awal: ' . $expectedPassword)
                && str_contains($request['message'], 'menu Profil')
                && !str_contains($request['message'], '{password_awal}');
        });

        // Password tidak pernah disimpan pada whatsapp_notification_logs.
        $log = WhatsappNotificationLog::where('dedup_key', 'account_created:' . $idAkun)->first();
        $this->assertNotNull($log);
        $this->assertSame(WhatsappNotificationLog::STATE_QUEUED, $log->delivery_state);
        $this->assertSame(1, $log->attempts);
        $this->assertTrue($log->is_test_mode);
        $this->assertStringNotContainsString($expectedPassword, json_encode($log->toArray()));
    }

    public function test_admin_editing_template_does_not_affect_username_generator(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        Sanctum::actingAs($this->adminAkun);

        // Admin mengubah isi template account_created.
        $this->putJson('/api/admin/whatsapp-templates/account_created', [
            'title' => 'Akun Baru Custom',
            'body' => 'Selamat datang {nama}. Login: {username} / {password_awal}.',
            'is_active' => true,
        ])->assertOk();

        // Pembuatan warga tetap mengikuti aturan generator username backend.
        $res = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000030',
            'nama' => 'Siti Aminah',
            'no_hp' => '081234567890',
            'jenis_kelamin' => 'P',
        ])->assertCreated();

        $this->assertSame('SitiAminah', $res->json('data.akun.username'));
        Http::assertSent(fn ($req) => $req['message'] === 'Selamat datang Siti Aminah. Login: SitiAminah / ' . AccountCreatedWhatsAppService::DEFAULT_INITIAL_PASSWORD . '.');
    }

    public function test_creating_warga_still_succeeds_when_fonnte_fails_and_can_be_retried(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => false, 'reason' => 'device disconnected'], 500)
                ->push(['status' => true, 'detail' => 'success! message in queue', 'process' => 'pending'], 200),
        ]);

        Sanctum::actingAs($this->adminAkun);

        $response = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000011',
            'nama' => 'Warga Tetap Sukses',
            'no_hp' => '081234567898',
            'jenis_kelamin' => 'P',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $idAkun = $response->json('data.akun.id_akun');
        $expectedPassword = AccountCreatedWhatsAppService::DEFAULT_INITIAL_PASSWORD;

        $this->assertDatabaseHas('warga', ['nik' => '9100000000000011']);
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'dedup_key' => 'account_created:' . $idAkun,
            'delivery_state' => WhatsappNotificationLog::STATE_FAILED,
            'attempts' => 1,
        ]);

        // Dry-run retry tidak mengirim pesan.
        $this->artisan('whatsapp:retry-account-notifications', [
            '--akun' => $idAkun,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Would Retry: 1')
            ->assertExitCode(0);
        Http::assertSentCount(1);

        // Retry nyata berhasil -> status menjadi queued dengan attempts = 2.
        $this->artisan('whatsapp:retry-account-notifications', [
            '--akun' => $idAkun,
        ])
            ->expectsOutputToContain('Queued: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'dedup_key' => 'account_created:' . $idAkun,
            'delivery_state' => WhatsappNotificationLog::STATE_QUEUED,
            'attempts' => 2,
        ]);
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => $req['target'] === '6289900009999'
            && str_contains($req['message'], 'WargaTetapSukses')
            && str_contains($req['message'], $expectedPassword));
    }

    public function test_deduplication_prevents_resending_queued_or_stale_processing_account_notification(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        Sanctum::actingAs($this->adminAkun);

        $response = $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000013',
            'nama' => 'Warga Anti Duplikat',
            'no_hp' => '081234567895',
            'jenis_kelamin' => 'L',
        ])->assertCreated();

        $akun = Akun::findOrFail($response->json('data.akun.id_akun'));
        $service = app(AccountCreatedWhatsAppService::class);

        $second = $service->sendForNewAccount($akun);
        $this->assertSame('already_handled', $second['action']);
        $this->assertSame('already_queued', $second['reason']);
        Http::assertSentCount(1);

        WhatsappNotificationLog::where('dedup_key', 'account_created:' . $akun->id_akun)
            ->update(['delivery_state' => WhatsappNotificationLog::STATE_PROCESSING]);

        $stale = $service->sendForNewAccount($akun);
        $this->assertSame('already_handled', $stale['action']);
        $this->assertSame('processing_requires_manual_check', $stale['reason']);
        Http::assertSentCount(1);
    }

    public function test_no_whatsapp_sent_when_validation_or_creation_fails(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        Sanctum::actingAs($this->adminAkun);

        $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '123',
            'nama' => 'Warga Gagal',
            'no_hp' => '081234567897',
            'jenis_kelamin' => 'L',
        ])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_no_whatsapp_sent_when_warga_has_no_phone_number(): void
    {
        $this->configureWhatsApp();
        Http::fake();

        Sanctum::actingAs($this->adminAkun);

        $this->postJson('/api/admin/warga', [
            'id_rt' => $this->rt->id_rt,
            'nik' => '9100000000000012',
            'nama' => 'Warga Tanpa HP',
            'jenis_kelamin' => 'L',
        ])->assertCreated();

        Http::assertNothingSent();
    }
}
