<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LogoHutRiController;
use App\Models\Akun;
use App\Models\LogoHutRi;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class LogoHutRiTest extends TestCase
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

        $rw = Rw::firstOrCreate(['nomor_rw' => '89'], ['nama_rw' => 'RW 89']);
        $rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '89'], ['nama_rt' => 'RT 89']);

        $w1 = Warga::firstOrCreate(['nik' => '8900000000000001'], ['id_rt' => $rt->id_rt, 'nama' => 'Admin Logo', 'jenis_kelamin' => 'L', 'status' => 'Aktif']);
        $w2 = Warga::firstOrCreate(['nik' => '8900000000000002'], ['id_rt' => $rt->id_rt, 'nama' => 'Panitia Logo', 'jenis_kelamin' => 'L', 'status' => 'Aktif']);
        $w3 = Warga::firstOrCreate(['nik' => '8900000000000003'], ['id_rt' => $rt->id_rt, 'nama' => 'Warga Logo', 'jenis_kelamin' => 'P', 'status' => 'Aktif']);

        $this->adminAkun = Akun::firstOrCreate(['username' => 'admin-logo-hut'], ['id_warga' => $w1->id_warga, 'id_role' => $adminRole->id_role, 'password' => Hash::make('secret123'), 'status' => 'Aktif']);
        $this->panitiaAkun = Akun::firstOrCreate(['username' => 'panitia-logo-hut'], ['id_warga' => $w2->id_warga, 'id_role' => $panitiaRole->id_role, 'password' => Hash::make('secret123'), 'status' => 'Aktif']);
        $this->wargaAkun = Akun::firstOrCreate(['username' => 'warga-logo-hut'], ['id_warga' => $w3->id_warga, 'id_role' => $wargaRole->id_role, 'password' => Hash::make('secret123'), 'status' => 'Aktif']);
    }

    private function mockCloudinaryOnController(string $returnUrl = 'https://res.cloudinary.com/demo/image/upload/v1700000000/pad/logo-hut-ri/logo.png'): void
    {
        $mock = Mockery::mock(LogoHutRiController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('uploadToCloudinary')->andReturn([
            'secure_url' => $returnUrl,
            'public_id' => 'pad/logo-hut-ri/logo',
        ]);
        $mock->shouldReceive('deleteCloudinaryImageByUrl')->andReturnNull();
        $mock->shouldReceive('destroyCloudinaryByPublicId')->andReturnNull();

        $this->app->instance(LogoHutRiController::class, $mock);
    }

    public function test_only_admin_can_manage_logo_hut_ri_while_active_endpoint_is_public(): void
    {
        // Publik dapat mengakses GET /api/logo-hut-ri/aktif tanpa login.
        $this->getJson('/api/logo-hut-ri/aktif')->assertOk()->assertJsonPath('success', true);

        // Guest -> 401
        $this->getJson('/api/admin/logo-hut-ri')->assertUnauthorized();
        $this->postJson('/api/admin/logo-hut-ri', [])->assertUnauthorized();

        // Panitia -> 403
        Sanctum::actingAs($this->panitiaAkun);
        $this->getJson('/api/admin/logo-hut-ri')->assertForbidden();
        $this->postJson('/api/admin/logo-hut-ri', [])->assertForbidden();

        // Warga -> 403
        Sanctum::actingAs($this->wargaAkun);
        $this->getJson('/api/admin/logo-hut-ri')->assertForbidden();
    }

    public function test_admin_can_create_list_show_update_and_delete_logo_with_unique_year(): void
    {
        $this->mockCloudinaryOnController('https://res.cloudinary.com/demo/image/upload/v1700000000/pad/logo-hut-ri/logo-2026.png');
        Sanctum::actingAs($this->adminAkun);

        // Tambah logo tahun 2091 (tahun isolasi agar tidak bentrok dengan data lokal 2026/2027)
        $create = $this->post('/api/admin/logo-hut-ri', [
            'tahun' => 2091,
            'nama_logo' => 'Logo HUT RI ke-81',
            'gambar' => UploadedFile::fake()->image('logo-2091.png', 200, 200),
            'status' => 'Aktif',
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tahun', 2091)
            ->assertJsonPath('data.nama_logo', 'Logo HUT RI ke-81')
            ->assertJsonPath('data.status', 'Aktif')
            ->assertJsonPath('data.gambar', 'https://res.cloudinary.com/demo/image/upload/v1700000000/pad/logo-hut-ri/logo-2026.png');

        $idLogo = $create->json('data.id_logo');

        // Tahun duplikat ditolak 422
        $this->post('/api/admin/logo-hut-ri', [
            'tahun' => 2091,
            'nama_logo' => 'Duplikat 2091',
            'gambar' => UploadedFile::fake()->image('dup.png', 100, 100),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tahun']);

        // Daftar & detail
        $this->getJson('/api/admin/logo-hut-ri')->assertOk()->assertJsonPath('success', true);
        $this->getJson("/api/admin/logo-hut-ri/{$idLogo}")
            ->assertOk()
            ->assertJsonPath('data.id_logo', $idLogo);

        // Update nama_logo via PUT
        $this->putJson("/api/admin/logo-hut-ri/{$idLogo}", [
            'nama_logo' => 'Logo HUT RI ke-81 (Revisi)',
        ])
            ->assertOk()
            ->assertJsonPath('data.nama_logo', 'Logo HUT RI ke-81 (Revisi)');

        // Hapus logo
        $this->deleteJson("/api/admin/logo-hut-ri/{$idLogo}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('logo_hut_ri', ['id_logo' => $idLogo]);
        $this->getJson("/api/admin/logo-hut-ri/{$idLogo}")->assertNotFound();
    }

    public function test_activating_new_logo_deactivates_previous_without_deleting_it_and_updates_public_endpoint(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $logo2026 = LogoHutRi::create([
            'tahun' => 2081,
            'nama_logo' => 'Logo HUT RI ke-81',
            'gambar' => 'https://res.cloudinary.com/demo/image/upload/v1/pad/logo-hut-ri/2081.png',
            'status' => 'Aktif',
        ]);

        $logo2027 = LogoHutRi::create([
            'tahun' => 2082,
            'nama_logo' => 'Logo HUT RI ke-82',
            'gambar' => 'https://res.cloudinary.com/demo/image/upload/v1/pad/logo-hut-ri/2082.png',
            'status' => 'Nonaktif',
        ]);

        // Aktifkan logo 2082
        $this->patchJson("/api/admin/logo-hut-ri/{$logo2027->id_logo}/aktif")
            ->assertOk()
            ->assertJsonPath('data.status', 'Aktif');

        // Logo 2081 tetap tersimpan di database, hanya berubah menjadi Nonaktif.
        $this->assertDatabaseHas('logo_hut_ri', [
            'id_logo' => $logo2026->id_logo,
            'tahun' => 2081,
            'status' => 'Nonaktif',
        ]);

        // Pastikan hanya ada tepat 1 logo aktif di tabel.
        $this->assertSame(1, LogoHutRi::where('status', 'Aktif')->count());

        // Endpoint publik mengembalikan logo 2082 yang sedang aktif.
        $this->getJson('/api/logo-hut-ri/aktif')
            ->assertOk()
            ->assertJsonPath('data.id_logo', $logo2027->id_logo)
            ->assertJsonPath('data.tahun', 2082);
    }

    public function test_cloudinary_public_id_extraction_is_accurate(): void
    {
        $controller = new LogoHutRiController();

        $this->assertSame(
            'pad/logo-hut-ri/logo-2026',
            $controller->extractCloudinaryPublicId('https://res.cloudinary.com/demo/image/upload/v1795527259/pad/logo-hut-ri/logo-2026.png')
        );
        $this->assertNull($controller->extractCloudinaryPublicId('https://example.com/res.cloudinary.com/image/upload/test.png'));
        $this->assertNull($controller->extractCloudinaryPublicId(null));
    }
}
