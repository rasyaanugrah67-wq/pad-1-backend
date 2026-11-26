<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Periode;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PesanManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function akun(string $roleName): Akun
    {
        $role = Role::firstOrCreate(['nama_role' => $roleName]);
        $rw = Rw::firstOrCreate(['nomor_rw' => '88'], ['nama_rw' => 'RW 88']);
        $rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '88'], ['nama_rt' => 'RT 88']);
        $warga = Warga::firstOrCreate(['nik' => '8888888888888888'], ['id_rt' => $rt->id_rt, 'nama' => 'Pengirim', 'jenis_kelamin' => 'L', 'status' => 'Aktif']);
        return Akun::firstOrCreate(['username' => 'pengirim-' . strtolower($roleName)], ['id_warga' => $warga->id_warga, 'id_role' => $role->id_role, 'password' => Hash::make('secret'), 'status' => 'Aktif']);
    }

    public function test_admin_can_publish_and_create_one_notification_per_warga(): void
    {
        $akun = $this->akun('Admin');
        $role = Role::firstOrCreate(['nama_role' => 'Warga']);
        $rw = Rw::where('nomor_rw', '88')->first();
        $rt = Rt::where('id_rw', $rw->id_rw)->where('nomor_rt', '88')->first();
        $warga = Warga::create(['id_rt' => $rt->id_rt, 'nik' => '8888888888888887', 'nama' => 'Penerima', 'jenis_kelamin' => 'P', 'status' => 'Aktif']);
        Akun::create(['id_warga' => $warga->id_warga, 'id_role' => $role->id_role, 'username' => 'penerima-pesan', 'password' => Hash::make('secret'), 'status' => 'Aktif']);
        $periode = Periode::firstOrCreate(['tahun' => 2099], ['nama_kegiatan' => 'Testing 2099', 'status' => 'Aktif']);
        Sanctum::actingAs($akun);

        $response = $this->postJson('/api/manajemen/pesan', ['id_periode' => $periode->id_periode, 'judul' => 'Informasi', 'isi' => 'Isi pengumuman', 'status' => 'Dipublikasi']);
        $response->assertCreated();
        $id = $response->json('data.id_pengumuman');
        $this->assertNotNull($response->json('data.tanggal_publikasi'));
        $this->assertDatabaseHas('pengumuman', ['id_pengumuman' => $id, 'status' => 'Dipublikasi']);
        $this->assertDatabaseHas('notifikasi', ['id_pengumuman' => $id, 'id_warga' => $warga->id_warga]);

        $this->patchJson("/api/manajemen/pesan/{$id}/kirim")->assertStatus(422);
        $this->assertSame(1, \App\Models\Notifikasi::where('id_pengumuman', $id)->where('id_warga', $warga->id_warga)->count());
    }

    public function test_warga_cannot_manage_messages_and_draft_has_no_notification(): void
    {
        $akun = $this->akun('Warga');
        $periode = Periode::firstOrCreate(['tahun' => 2098], ['nama_kegiatan' => 'Testing 2098', 'status' => 'Aktif']);
        Sanctum::actingAs($akun);
        $this->postJson('/api/manajemen/pesan', ['id_periode' => $periode->id_periode, 'judul' => 'Draft', 'isi' => 'Isi'])->assertForbidden();
    }
}
