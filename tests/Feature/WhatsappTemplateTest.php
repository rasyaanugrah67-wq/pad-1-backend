<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppNotificationService;
use App\Services\WhatsappTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsappTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private Akun $adminAkun;
    private Akun $panitiaAkun;
    private Akun $wargaAkun;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['nama_role' => 'Admin']);
        $panitiaRole = Role::firstOrCreate(['nama_role' => 'Panitia']);
        $wargaRole = Role::firstOrCreate(['nama_role' => 'Warga']);

        $rw = Rw::firstOrCreate(['nomor_rw' => '90'], ['nama_rw' => 'RW 90']);
        $rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '90'], ['nama_rt' => 'RT 90']);

        $w1 = Warga::firstOrCreate(['nik' => '9000000000000001'], ['id_rt' => $rt->id_rt, 'nama' => 'Admin Tpl', 'jenis_kelamin' => 'L', 'status' => 'Aktif']);
        $w2 = Warga::firstOrCreate(['nik' => '9000000000000002'], ['id_rt' => $rt->id_rt, 'nama' => 'Panitia Tpl', 'jenis_kelamin' => 'L', 'status' => 'Aktif']);
        $w3 = Warga::firstOrCreate(['nik' => '9000000000000003'], ['id_rt' => $rt->id_rt, 'nama' => 'Warga Tpl', 'jenis_kelamin' => 'P', 'status' => 'Aktif']);

        $this->adminAkun = Akun::firstOrCreate(['username' => 'admin-wa-tpl'], ['id_warga' => $w1->id_warga, 'id_role' => $adminRole->id_role, 'password' => Hash::make('secret123'), 'status' => 'Aktif']);
        $this->panitiaAkun = Akun::firstOrCreate(['username' => 'panitia-wa-tpl'], ['id_warga' => $w2->id_warga, 'id_role' => $panitiaRole->id_role, 'password' => Hash::make('secret123'), 'status' => 'Aktif']);
        $this->wargaAkun = Akun::firstOrCreate(['username' => 'warga-wa-tpl'], ['id_warga' => $w3->id_warga, 'id_role' => $wargaRole->id_role, 'password' => Hash::make('secret123'), 'status' => 'Aktif']);
    }

    private function configureWhatsApp(): void
    {
        Config::set('services.whatsapp', [
            'enabled' => true,
            'provider' => 'fonnte',
            'api_url' => 'https://api.fonnte.com/send',
            'token' => 'fake-fonnte-token',
            'timeout' => 15,
            'test_mode' => true,
            'test_number' => '6289900004444',
        ]);
    }

    public function test_only_admin_can_access_whatsapp_template_endpoints(): void
    {
        // Guest -> 401
        $this->getJson('/api/admin/whatsapp-templates')->assertUnauthorized();
        $this->putJson('/api/admin/whatsapp-templates/account_created', [])->assertUnauthorized();

        // Panitia -> 403
        Sanctum::actingAs($this->panitiaAkun);
        $this->getJson('/api/admin/whatsapp-templates')->assertForbidden();
        $this->putJson('/api/admin/whatsapp-templates/account_created', [
            'title' => 'Edit',
            'body' => 'Halo {nama}',
        ])->assertForbidden();
        $this->patchJson('/api/admin/whatsapp-templates/account_created/status', ['is_active' => false])->assertForbidden();
        $this->postJson('/api/admin/whatsapp-templates/account_created/preview')->assertForbidden();

        // Warga -> 403
        Sanctum::actingAs($this->wargaAkun);
        $this->getJson('/api/admin/whatsapp-templates')->assertForbidden();
        $this->putJson('/api/admin/whatsapp-templates/account_created', [
            'title' => 'Edit',
            'body' => 'Halo {nama}',
        ])->assertForbidden();
    }

    public function test_admin_can_list_view_update_toggle_and_preview_templates(): void
    {
        Sanctum::actingAs($this->adminAkun);

        // List seluruh 5 template
        $list = $this->getJson('/api/admin/whatsapp-templates')->assertOk();
        $list->assertJsonPath('success', true);
        $this->assertCount(5, $list->json('data'));

        // Detail 1 template
        $this->getJson('/api/admin/whatsapp-templates/iuran_reminder')
            ->assertOk()
            ->assertJsonPath('data.key', 'iuran_reminder')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'key', 'title', 'body', 'is_active', 'allowed_placeholders', 'sample_data', 'preview'],
            ]);

        // Update judul, isi, dan status
        $this->putJson('/api/admin/whatsapp-templates/iuran_reminder', [
            'title' => 'Pengingat Iuran Custom',
            'body' => 'Halo {nama}, tagihan {nama_iuran} Rp{nominal} jatuh tempo {tanggal}. {keterangan}',
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Pengingat Iuran Custom')
            ->assertJsonPath('data.updated_by', $this->adminAkun->username);

        // Preview dengan isi custom
        $preview = $this->postJson('/api/admin/whatsapp-templates/iuran_reminder/preview', [
            'body' => 'Hai {nama}, mohon bayar {nama_iuran} sebesar Rp{nominal} sebelum {tanggal}.',
        ])->assertOk();

        $rendered = $preview->json('data.rendered_message');
        $this->assertStringContainsString('Budi Santoso', $rendered);
        $this->assertStringContainsString('Rp50.000', $rendered);
        $this->assertStringNotContainsString('{nama}', $rendered);
        $this->assertStringNotContainsString('{nominal}', $rendered);

        // Toggle nonaktif lalu aktifkan kembali
        $this->patchJson('/api/admin/whatsapp-templates/iuran_reminder/status', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->patchJson('/api/admin/whatsapp-templates/iuran_reminder/status', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        // Key tidak dikenal -> 404
        $this->getJson('/api/admin/whatsapp-templates/unknown_key')->assertNotFound();
    }

    public function test_invalid_placeholder_is_rejected_and_raw_placeholder_never_leaks(): void
    {
        Sanctum::actingAs($this->adminAkun);

        // {password_awal} diizinkan pada account_created
        $this->putJson('/api/admin/whatsapp-templates/account_created', [
            'title' => 'Informasi Akun Warga Baru',
            'body' => 'Halo {nama}! Username: {username}, Password awal: {password_awal}',
        ])->assertOk();

        // Placeholder yang tidak terdaftar seperti {password} atau {kode_rahasia} tetap ditolak 422
        $this->putJson('/api/admin/whatsapp-templates/account_created', [
            'title' => 'Akun Baru',
            'body' => 'Halo {nama}, username: {username}, password: {password}',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        // Preview dengan placeholder tidak valid juga ditolak 422
        $this->postJson('/api/admin/whatsapp-templates/payment_confirmed/preview', [
            'body' => 'Halo {nama}, kode {tidak_ada}',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        // Proteksi berlapis pada renderText() agar placeholder mentah tidak pernah terkirim
        $rendered = app(WhatsappTemplateService::class)->renderText(
            WhatsappTemplateService::KEY_ACCOUNT_CREATED,
            'Halo {nama}, user {username}, pass {password_awal} {hack_placeholder}',
            ['nama' => 'Budi', 'username' => 'BudiSantoso', 'password_awal' => 'WargaDemo2026!']
        );
        $this->assertStringNotContainsString('{', $rendered);
        $this->assertStringNotContainsString('}', $rendered);
    }

    public function test_inactive_template_prevents_sending_while_active_template_uses_db_content(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        // Nonaktifkan template account_created -> tidak boleh mengirim HTTP request
        WhatsappTemplate::where('key', 'account_created')->update(['is_active' => false]);

        $wa = app(WhatsAppNotificationService::class);
        $this->assertFalse($wa->sendAccountCredential('081234567890', 'Budi', 'budi.01'));
        Http::assertNothingSent();

        // Aktifkan dan ubah isi template di DB -> trigger otomatis memakai teks terbaru dari DB
        WhatsappTemplate::where('key', 'account_created')->update([
            'is_active' => true,
            'body' => 'Selamat datang {nama}! Akun PAD Anda ({username}) sudah siap digunakan.',
        ]);

        $this->assertTrue($wa->sendAccountCredential('081234567890', 'Budi', 'budi.01'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => $req['target'] === '6289900004444'
            && $req['message'] === 'Selamat datang Budi! Akun PAD Anda (budi.01) sudah siap digunakan.');
    }

    public function test_all_five_triggers_render_from_database_templates_without_raw_placeholders(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'detail' => 'success! message in queue',
                'process' => 'pending',
            ], 200),
        ]);

        $wa = app(WhatsAppNotificationService::class);

        $this->assertTrue($wa->sendAccountCredential('081200000001', 'Budi', 'budi01'));
        $this->assertTrue($wa->sendIuranNotification('081200000001', 'Budi', 50000, '10-08-2026', 'Iuran HUT RI'));
        $this->assertTrue($wa->sendPaymentConfirmation('081200000001', 'Budi', 50000, 'Iuran HUT RI', '05-08-2026 10:00 WIB'));
        $this->assertTrue($wa->sendLombaParticipantReminder('081200000001', 'Budi', 'Tarik Tambang', '17-08-2026, 09:00 WIB', 'Lapangan RW 01'));
        $this->assertTrue($wa->sendMatchWatchInvitation('081200000001', 'Budi', 'Futsal', '17-08-2026, 15:30 WIB', 'Lapangan RW 01', 'Final'));

        Http::assertSentCount(5);
        Http::assertSent(function ($req) {
            return $req['target'] === '6289900004444'
                && !preg_match('/\{[a-zA-Z0-9_]+\}/', (string) $req['message']);
        });
    }
}
