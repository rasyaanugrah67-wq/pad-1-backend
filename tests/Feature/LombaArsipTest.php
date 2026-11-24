<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Lomba;
use App\Models\Role;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LombaArsipTest extends TestCase
{
    use DatabaseTransactions;

    protected Akun $adminAkun;
    protected Akun $panitiaAkun;
    protected Akun $wargaAkun;
    protected Lomba $lomba;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['nama_role' => 'Admin']);
        $panitiaRole = Role::firstOrCreate(['nama_role' => 'Panitia']);
        $wargaRole = Role::firstOrCreate(['nama_role' => 'Warga']);

        $warga1 = Warga::firstOrCreate(
            ['nik' => '9999999999999901'],
            [
                'id_rt' => 1,
                'nama' => 'Testing Admin Warga',
                'no_hp' => '081299999901',
                'alamat' => 'RT 01',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '1990-01-01',
                'status' => 'Aktif',
            ]
        );

        $warga2 = Warga::firstOrCreate(
            ['nik' => '9999999999999902'],
            [
                'id_rt' => 1,
                'nama' => 'Testing Panitia Warga',
                'no_hp' => '081299999902',
                'alamat' => 'RT 01',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '1990-01-01',
                'status' => 'Aktif',
            ]
        );

        $warga3 = Warga::firstOrCreate(
            ['nik' => '9999999999999903'],
            [
                'id_rt' => 1,
                'nama' => 'Testing Warga Biasa',
                'no_hp' => '081299999903',
                'alamat' => 'RT 01',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '1990-01-01',
                'status' => 'Aktif',
            ]
        );

        $this->adminAkun = Akun::firstOrCreate(
            ['username' => 'test_admin_arsip'],
            [
                'id_warga' => $warga1->id_warga,
                'id_role' => $adminRole->id_role,
                'password' => bcrypt('password123'),
                'status' => 'Aktif',
            ]
        );
        $this->adminAkun->id_role = $adminRole->id_role;
        $this->adminAkun->status = 'Aktif';
        $this->adminAkun->save();

        $this->panitiaAkun = Akun::firstOrCreate(
            ['username' => 'test_panitia_arsip'],
            [
                'id_warga' => $warga2->id_warga,
                'id_role' => $panitiaRole->id_role,
                'password' => bcrypt('password123'),
                'status' => 'Aktif',
            ]
        );
        $this->panitiaAkun->id_role = $panitiaRole->id_role;
        $this->panitiaAkun->status = 'Aktif';
        $this->panitiaAkun->save();

        $this->wargaAkun = Akun::firstOrCreate(
            ['username' => 'test_warga_arsip'],
            [
                'id_warga' => $warga3->id_warga,
                'id_role' => $wargaRole->id_role,
                'password' => bcrypt('password123'),
                'status' => 'Aktif',
            ]
        );
        $this->wargaAkun->id_role = $wargaRole->id_role;
        $this->wargaAkun->status = 'Aktif';
        $this->wargaAkun->save();

        // Cari atau buat lomba aktif untuk pengujian
        $this->lomba = Lomba::firstOrCreate(
            ['nama_lomba' => 'Lomba Testing Arsip'],
            [
                'id_periode' => 1,
                'id_kategori' => 1,
                'jenis_pendaftaran' => 'Individu',
                'status' => 'Dibuka',
                'deskripsi' => 'Deskripsi testing arsip',
            ]
        );
        $this->lomba->is_arsip = false;
        $this->lomba->save();
    }

    public function test_unauthenticated_user_cannot_access_arsip_endpoints(): void
    {
        $this->getJson('/api/lomba/manage/arsip')->assertUnauthorized();
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertUnauthorized();
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/pulihkan")->assertUnauthorized();
    }

    public function test_warga_cannot_access_arsip_endpoints(): void
    {
        Sanctum::actingAs($this->wargaAkun);

        $this->getJson('/api/lomba/manage/arsip')->assertForbidden();
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertForbidden();
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/pulihkan")->assertForbidden();
    }

    public function test_admin_can_archive_lomba(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $response = $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lomba berhasil diarsipkan.',
                'data' => [
                    'id_lomba' => $this->lomba->id_lomba,
                    'is_arsip' => true,
                ],
            ]);

        $this->assertDatabaseHas('lomba', [
            'id_lomba' => $this->lomba->id_lomba,
            'is_arsip' => 1,
        ]);
    }

    public function test_panitia_can_archive_lomba(): void
    {
        Sanctum::actingAs($this->panitiaAkun);

        $response = $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lomba berhasil diarsipkan.',
            ]);

        $this->assertDatabaseHas('lomba', [
            'id_lomba' => $this->lomba->id_lomba,
            'is_arsip' => 1,
        ]);
    }

    public function test_archived_lomba_does_not_appear_in_active_management_list(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertOk();

        $response = $this->getJson('/api/lomba/manage')->assertOk();

        $activeIds = collect($response->json('data'))->pluck('id_lomba')->all();
        $this->assertNotContains($this->lomba->id_lomba, $activeIds);
    }

    public function test_archived_lomba_appears_in_archive_management_list(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertOk();

        $response = $this->getJson('/api/lomba/manage/arsip')->assertOk();

        $archiveIds = collect($response->json('data'))->pluck('id_lomba')->all();
        $this->assertContains($this->lomba->id_lomba, $archiveIds);
    }

    public function test_archived_lomba_does_not_appear_in_public_or_warga_list_and_detail(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertOk();

        // Public GET /api/lomba
        $publicList = $this->getJson('/api/lomba')->assertOk();
        $publicIds = collect($publicList->json('data'))->pluck('id_lomba')->all();
        $this->assertNotContains($this->lomba->id_lomba, $publicIds);

        // Public GET /api/warga/lomba
        $wargaList = $this->getJson('/api/warga/lomba')->assertOk();
        $wargaIds = collect($wargaList->json('data'))->pluck('id_lomba')->all();
        $this->assertNotContains($this->lomba->id_lomba, $wargaIds);

        // Public GET /api/lomba/{id} should return 404
        $this->getJson("/api/lomba/{$this->lomba->id_lomba}")->assertNotFound();
    }

    public function test_admin_and_panitia_can_restore_lomba(): void
    {
        // Arsipkan dulu
        $this->lomba->is_arsip = true;
        $this->lomba->save();

        // Admin pulihkan
        Sanctum::actingAs($this->adminAkun);
        $response = $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/pulihkan");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lomba berhasil dipulihkan.',
                'data' => [
                    'id_lomba' => $this->lomba->id_lomba,
                    'is_arsip' => false,
                ],
            ]);

        $this->assertDatabaseHas('lomba', [
            'id_lomba' => $this->lomba->id_lomba,
            'is_arsip' => 0,
        ]);

        // Lomba kembali muncul di daftar aktif
        $activeList = $this->getJson('/api/lomba/manage')->assertOk();
        $activeIds = collect($activeList->json('data'))->pluck('id_lomba')->all();
        $this->assertContains($this->lomba->id_lomba, $activeIds);

        // Lomba tidak ada di daftar arsip
        $archiveList = $this->getJson('/api/lomba/manage/arsip')->assertOk();
        $archiveIds = collect($archiveList->json('data'))->pluck('id_lomba')->all();
        $this->assertNotContains($this->lomba->id_lomba, $archiveIds);
    }

    public function test_relations_are_preserved_when_archiving_and_restoring(): void
    {
        Sanctum::actingAs($this->adminAkun);

        // Tambah jadwal dummy
        $jadwal = $this->lomba->jadwal()->create([
            'tanggal' => '2026-08-17',
            'waktu_mulai' => '08:00',
            'waktu_selesai' => '10:00',
            'lokasi' => 'Lapangan RT 01',
        ]);

        // Arsipkan
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertOk();

        // Pastikan jadwal tetap ada
        $this->assertDatabaseHas('jadwal_lomba', [
            'id_jadwal' => $jadwal->id_jadwal,
            'id_lomba' => $this->lomba->id_lomba,
        ]);

        // Pulihkan
        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/pulihkan")->assertOk();

        // Pastikan jadwal tetap ada
        $this->assertDatabaseHas('jadwal_lomba', [
            'id_jadwal' => $jadwal->id_jadwal,
            'id_lomba' => $this->lomba->id_lomba,
        ]);
    }

    public function test_non_existent_lomba_returns_404(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $this->patchJson('/api/lomba/manage/999999/arsip')->assertNotFound();
        $this->patchJson('/api/lomba/manage/999999/pulihkan')->assertNotFound();
    }

    public function test_cannot_archive_already_archived_lomba(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip")->assertOk();

        // Arsipkan lagi -> 422
        $response = $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/arsip");
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Lomba sudah berada dalam arsip.',
            ]);
    }

    public function test_cannot_restore_already_active_lomba(): void
    {
        Sanctum::actingAs($this->adminAkun);

        // Lomba masih aktif -> pulihkan menghasilkan 422
        $response = $this->patchJson("/api/lomba/manage/{$this->lomba->id_lomba}/pulihkan");
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Lomba tidak berada dalam arsip atau sudah aktif.',
            ]);
    }
}
