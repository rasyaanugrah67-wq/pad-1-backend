<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Iuran;
use App\Models\Pembayaran;
use App\Models\Periode;
use App\Models\Role;
use App\Models\TagihanIuran;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagihanIuranTest extends TestCase
{
    use DatabaseTransactions;

    protected Akun $adminAkun;
    protected Akun $panitiaAkun;
    protected Akun $wargaAkun;
    protected Akun $wargaAkun2;
    protected Periode $periode;
    protected Warga $warga;
    protected Warga $warga2;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole   = Role::firstOrCreate(['nama_role' => 'Admin']);
        $panitiaRole = Role::firstOrCreate(['nama_role' => 'Panitia']);
        $wargaRole   = Role::firstOrCreate(['nama_role' => 'Warga']);

        // Gunakan id_rt=1 (asumsi RT 1 selalu ada di DB lokal)
        $this->warga = Warga::firstOrCreate(
            ['nik' => '8888888888888801'],
            [
                'id_rt'          => 1,
                'nama'           => 'Testing Warga Tagihan',
                'no_hp'          => '081288888801',
                'alamat'         => 'RT 01',
                'jenis_kelamin'  => 'L',
                'tanggal_lahir'  => '1990-01-01',
                'status'         => 'Aktif',
            ]
        );
        $this->warga->status = 'Aktif';
        $this->warga->save();

        $this->warga2 = Warga::firstOrCreate(
            ['nik' => '8888888888888802'],
            [
                'id_rt'          => 1,
                'nama'           => 'Testing Warga Tagihan 2',
                'no_hp'          => '081288888802',
                'alamat'         => 'RT 01',
                'jenis_kelamin'  => 'P',
                'tanggal_lahir'  => '1992-02-02',
                'status'         => 'Aktif',
            ]
        );
        $this->warga2->status = 'Aktif';
        $this->warga2->save();

        $wargaAdminData = Warga::firstOrCreate(
            ['nik' => '8888888888888803'],
            [
                'id_rt'          => 1,
                'nama'           => 'Testing Admin Tagihan',
                'no_hp'          => '081288888803',
                'alamat'         => 'RT 01',
                'jenis_kelamin'  => 'L',
                'tanggal_lahir'  => '1988-01-01',
                'status'         => 'Aktif',
            ]
        );

        $wargaPanitiaData = Warga::firstOrCreate(
            ['nik' => '8888888888888804'],
            [
                'id_rt'          => 1,
                'nama'           => 'Testing Panitia Tagihan',
                'no_hp'          => '081288888804',
                'alamat'         => 'RT 01',
                'jenis_kelamin'  => 'L',
                'tanggal_lahir'  => '1989-01-01',
                'status'         => 'Aktif',
            ]
        );

        $this->adminAkun = Akun::firstOrCreate(
            ['username' => 'test_admin_tagihan'],
            [
                'id_warga' => $wargaAdminData->id_warga,
                'id_role'  => $adminRole->id_role,
                'password' => bcrypt('password123'),
                'status'   => 'Aktif',
            ]
        );
        $this->adminAkun->id_role = $adminRole->id_role;
        $this->adminAkun->status  = 'Aktif';
        $this->adminAkun->save();

        $this->panitiaAkun = Akun::firstOrCreate(
            ['username' => 'test_panitia_tagihan'],
            [
                'id_warga' => $wargaPanitiaData->id_warga,
                'id_role'  => $panitiaRole->id_role,
                'password' => bcrypt('password123'),
                'status'   => 'Aktif',
            ]
        );
        $this->panitiaAkun->id_role = $panitiaRole->id_role;
        $this->panitiaAkun->status  = 'Aktif';
        $this->panitiaAkun->save();

        $this->wargaAkun = Akun::firstOrCreate(
            ['username' => 'test_warga_tagihan1'],
            [
                'id_warga' => $this->warga->id_warga,
                'id_role'  => $wargaRole->id_role,
                'password' => bcrypt('password123'),
                'status'   => 'Aktif',
            ]
        );
        $this->wargaAkun->id_role = $wargaRole->id_role;
        $this->wargaAkun->status  = 'Aktif';
        $this->wargaAkun->save();

        $this->wargaAkun2 = Akun::firstOrCreate(
            ['username' => 'test_warga_tagihan2'],
            [
                'id_warga' => $this->warga2->id_warga,
                'id_role'  => $wargaRole->id_role,
                'password' => bcrypt('password123'),
                'status'   => 'Aktif',
            ]
        );
        $this->wargaAkun2->id_role = $wargaRole->id_role;
        $this->wargaAkun2->status  = 'Aktif';
        $this->wargaAkun2->save();

        // Gunakan periode ID 1 (asumsi selalu ada)
        $this->periode = Periode::first();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Buat tagihan master dan iuran per warga. */
    private function buatTagihanDenganIuran(array $overrides = [], array $wargaIds = []): TagihanIuran
    {
        $tagihan = TagihanIuran::create(array_merge([
            'id_periode'   => $this->periode->id_periode,
            'nama_tagihan' => 'Iuran Test Otomatis',
            'nominal'      => 50000,
            'jatuh_tempo'  => now()->addDays(30)->toDateString(),
            'keterangan'   => 'Keterangan test',
            'target'       => empty($wargaIds) ? 'Semua' : 'Tertentu',
            'is_aktif'     => true,
            'created_by'   => $this->adminAkun->id_akun,
        ], $overrides));

        $ids = empty($wargaIds) ? [$this->warga->id_warga, $this->warga2->id_warga] : $wargaIds;

        foreach ($ids as $idWarga) {
            Iuran::create([
                'id_tagihan'  => $tagihan->id_tagihan,
                'id_warga'    => $idWarga,
                'id_periode'  => $tagihan->id_periode,
                'nama_iuran'  => $tagihan->nama_tagihan,
                'nominal'     => $tagihan->nominal,
                'jatuh_tempo' => $tagihan->jatuh_tempo,
                'keterangan'  => $tagihan->keterangan,
                'status'      => 'Belum Bayar',
                'is_aktif'    => true,
            ]);
        }

        return $tagihan;
    }

    // ─── 1. Autentikasi & Hak Akses ────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_tagihan_endpoints(): void
    {
        $this->getJson('/api/manajemen/tagihan')->assertUnauthorized();
        $this->postJson('/api/manajemen/tagihan', [])->assertUnauthorized();
    }

    public function test_warga_cannot_access_tagihan_manajemen(): void
    {
        Sanctum::actingAs($this->wargaAkun);

        $this->getJson('/api/manajemen/tagihan')->assertForbidden();
        $this->postJson('/api/manajemen/tagihan', [])->assertForbidden();
    }

    // ─── 2. Admin & Panitia bisa melihat daftar tagihan ────────────────────

    public function test_admin_dapat_melihat_daftar_tagihan(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $this->buatTagihanDenganIuran();

        $response = $this->getJson('/api/manajemen/tagihan');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['data', 'total', 'per_page', 'current_page'],
            ]);
    }

    public function test_panitia_dapat_melihat_daftar_tagihan(): void
    {
        Sanctum::actingAs($this->panitiaAkun);
        $this->buatTagihanDenganIuran();

        $this->getJson('/api/manajemen/tagihan')->assertOk();
    }

    // ─── 3. Buat tagihan ────────────────────────────────────────────────────

    public function test_admin_dapat_membuat_tagihan_target_semua(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $payload = [
            'nama_tagihan' => 'Iuran RT Test Semua',
            'id_periode'   => $this->periode->id_periode,
            'nominal'      => 75000,
            'jatuh_tempo'  => now()->addDays(30)->toDateString(),
            'keterangan'   => 'Keterangan test semua',
            'target'       => 'Semua',
        ];

        $response = $this->postJson('/api/manajemen/tagihan', $payload);

        $response->assertCreated()
            ->assertJson(['success' => true]);

        // Tagihan master tersimpan di DB
        $this->assertDatabaseHas('tagihan_iuran', [
            'nama_tagihan' => 'Iuran RT Test Semua',
            'nominal'      => 75000,
            'target'       => 'Semua',
        ]);

        // Baris iuran ter-generate untuk warga aktif
        $tagihan = TagihanIuran::where('nama_tagihan', 'Iuran RT Test Semua')->first();
        $this->assertNotNull($tagihan);
        $this->assertDatabaseHas('iuran', [
            'id_tagihan' => $tagihan->id_tagihan,
            'id_warga'   => $this->warga->id_warga,
        ]);
    }

    public function test_panitia_dapat_membuat_tagihan_target_tertentu(): void
    {
        Sanctum::actingAs($this->panitiaAkun);

        $payload = [
            'nama_tagihan' => 'Iuran RT Test Tertentu',
            'id_periode'   => $this->periode->id_periode,
            'nominal'      => 30000,
            'jatuh_tempo'  => now()->addDays(15)->toDateString(),
            'target'       => 'Tertentu',
            'id_warga'     => [$this->warga->id_warga],
        ];

        $response = $this->postJson('/api/manajemen/tagihan', $payload);

        $response->assertCreated()->assertJson(['success' => true]);

        $tagihan = TagihanIuran::where('nama_tagihan', 'Iuran RT Test Tertentu')->first();
        $this->assertNotNull($tagihan);

        // Hanya warga yang dipilih yang mendapat iuran
        $this->assertDatabaseHas('iuran', [
            'id_tagihan' => $tagihan->id_tagihan,
            'id_warga'   => $this->warga->id_warga,
        ]);
        $this->assertDatabaseMissing('iuran', [
            'id_tagihan' => $tagihan->id_tagihan,
            'id_warga'   => $this->warga2->id_warga,
        ]);
    }

    public function test_target_tertentu_wajib_ada_id_warga(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $payload = [
            'nama_tagihan' => 'Test Gagal',
            'id_periode'   => $this->periode->id_periode,
            'nominal'      => 10000,
            'jatuh_tempo'  => now()->addDays(10)->toDateString(),
            'target'       => 'Tertentu',
            // id_warga tidak dikirim
        ];

        $this->postJson('/api/manajemen/tagihan', $payload)->assertUnprocessable();
    }

    // ─── 4. Detail tagihan ──────────────────────────────────────────────────

    public function test_admin_dapat_melihat_detail_tagihan(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        $response = $this->getJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_tagihan',
                    'nama_tagihan',
                    'statistik' => [
                        'total_warga',
                        'sudah_bayar',
                        'belum_bayar',
                        'menunggu_konfirmasi',
                    ],
                ],
            ]);
    }

    public function test_detail_tagihan_tidak_ditemukan_mengembalikan_404(): void
    {
        Sanctum::actingAs($this->adminAkun);

        $this->getJson('/api/manajemen/tagihan/999999')->assertNotFound();
    }

    // ─── 5. Update tagihan ──────────────────────────────────────────────────

    public function test_admin_dapat_mengubah_tagihan(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        $response = $this->putJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}", [
            'nama_tagihan' => 'Iuran RT Diubah',
            'nominal'      => 60000,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'data'    => ['nama_tagihan' => 'Iuran RT Diubah'],
        ]);
    }

    public function test_perubahan_nominal_memperbarui_iuran_belum_bayar(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        $this->putJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}", [
            'nominal' => 99000,
        ])->assertOk();

        // Semua iuran Belum Bayar harus ikut diperbarui
        $count = Iuran::where('id_tagihan', $tagihan->id_tagihan)
            ->where('status', 'Belum Bayar')
            ->where('nominal', 99000)
            ->count();

        $this->assertGreaterThan(0, $count);
    }

    // ─── 6. Nonaktifkan tagihan ─────────────────────────────────────────────

    public function test_admin_dapat_menonaktifkan_tagihan(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        $response = $this->patchJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}/nonaktifkan");

        $response->assertOk()->assertJson(['success' => true]);

        // Tagihan master nonaktif
        $this->assertDatabaseHas('tagihan_iuran', [
            'id_tagihan' => $tagihan->id_tagihan,
            'is_aktif'   => false,
        ]);

        // Semua iuran terkait ikut nonaktif
        $masihAktif = Iuran::where('id_tagihan', $tagihan->id_tagihan)
            ->where('is_aktif', true)
            ->count();
        $this->assertEquals(0, $masihAktif);
    }

    public function test_nonaktifkan_tagihan_yang_sudah_nonaktif_mengembalikan_422(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran(['is_aktif' => false]);

        $this->patchJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}/nonaktifkan")
            ->assertUnprocessable();
    }

    // ─── 7. Status pembayaran warga ─────────────────────────────────────────

    public function test_admin_dapat_melihat_status_pembayaran_warga_dari_tagihan(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        $response = $this->getJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}/pembayaran");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['data', 'total'],
            ]);
    }

    // ─── 8. Endpoint warga: iuran aktif untuk dropdown ─────────────────────

    public function test_warga_dapat_melihat_iuran_aktif_untuk_dropdown(): void
    {
        Sanctum::actingAs($this->wargaAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        $response = $this->getJson('/api/warga/iuran/aktif');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id_iuran',
                        'nama_iuran',
                        'nominal',
                        'jatuh_tempo',
                        'nama_kepala_keluarga',
                    ],
                ],
            ]);

        // Hanya iuran milik warga login yang dikembalikan
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals(
                $this->warga->nama,
                $item['nama_kepala_keluarga']
            );
        }
    }

    public function test_tagihan_nonaktif_tidak_muncul_di_dropdown_warga(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran();

        // Nonaktifkan
        $this->patchJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}/nonaktifkan")->assertOk();

        // Cek dropdown warga
        Sanctum::actingAs($this->wargaAkun);
        $response = $this->getJson('/api/warga/iuran/aktif')->assertOk();

        $ids = collect($response->json('data'))->pluck('id_iuran')->toArray();
        $iuranIds = Iuran::where('id_tagihan', $tagihan->id_tagihan)->pluck('id_iuran')->toArray();

        // Tidak ada iuran dari tagihan nonaktif di dropdown
        foreach ($iuranIds as $id) {
            $this->assertNotContains($id, $ids);
        }
    }

    // ─── 9. Validasi bayar iuran nonaktif ──────────────────────────────────

    public function test_warga_tidak_dapat_membayar_iuran_nonaktif(): void
    {
        Sanctum::actingAs($this->adminAkun);
        $tagihan = $this->buatTagihanDenganIuran([], [$this->warga->id_warga]);
        $this->patchJson("/api/manajemen/tagihan/{$tagihan->id_tagihan}/nonaktifkan")->assertOk();

        $iuran = Iuran::where('id_tagihan', $tagihan->id_tagihan)
            ->where('id_warga', $this->warga->id_warga)
            ->first();

        Sanctum::actingAs($this->wargaAkun);

        // Buat file dummy untuk bukti (tidak sampai proses Cloudinary)
        $file = \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg', 100, 100);

        $response = $this->postJson("/api/warga/iuran/{$iuran->id_iuran}/pembayaran", [
            'metode'     => 'Transfer',
            'nominal'    => 50000,
            'bukti_bayar' => $file,
        ]);

        // Harus ditolak 422 karena iuran nonaktif (sebelum proses Cloudinary)
        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Tagihan ini tidak aktif dan tidak dapat dibayar.',
            ]);
    }

    public function test_warga_tidak_dapat_membayar_iuran_milik_warga_lain(): void
    {
        $tagihan = $this->buatTagihanDenganIuran([], [$this->warga2->id_warga]);

        $iuranWarga2 = Iuran::where('id_tagihan', $tagihan->id_tagihan)
            ->where('id_warga', $this->warga2->id_warga)
            ->first();

        // wargaAkun (warga1) mencoba bayar iuran warga2
        Sanctum::actingAs($this->wargaAkun);

        $response = $this->postJson("/api/warga/iuran/{$iuranWarga2->id_iuran}/pembayaran", [
            'metode'      => 'Transfer',
            'nominal'     => 50000,
            'bukti_bayar' => \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertNotFound();
    }

    public function test_warga_tidak_dapat_membayar_iuran_yang_sudah_lunas(): void
    {
        $tagihan = $this->buatTagihanDenganIuran([], [$this->warga->id_warga]);

        $iuran = Iuran::where('id_tagihan', $tagihan->id_tagihan)
            ->where('id_warga', $this->warga->id_warga)
            ->first();

        // Set status lunas
        $iuran->status = 'Sudah Bayar';
        $iuran->save();

        Sanctum::actingAs($this->wargaAkun);

        $response = $this->postJson("/api/warga/iuran/{$iuran->id_iuran}/pembayaran", [
            'metode'      => 'Transfer',
            'nominal'     => 50000,
            'bukti_bayar' => \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Iuran ini sudah lunas.',
            ]);
    }

    // ─── 10. Guest ditolak ─────────────────────────────────────────────────

    public function test_guest_tidak_bisa_akses_tagihan_manajemen(): void
    {
        $this->getJson('/api/manajemen/tagihan')->assertUnauthorized();
        $this->postJson('/api/manajemen/tagihan')->assertUnauthorized();
    }

    public function test_guest_tidak_bisa_akses_dropdown_iuran_warga(): void
    {
        $this->getJson('/api/warga/iuran/aktif')->assertUnauthorized();
    }
}
