<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\Role;
use App\Models\Rt;
use App\Models\Rw;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KontakPengurusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_warga_can_see_contact_for_their_rt_and_rw(): void
    {
        $role = Role::firstOrCreate(['nama_role' => 'Warga']);
        $rw = Rw::firstOrCreate(['nomor_rw' => '98'], ['nama_rw' => 'RW 98', 'nama_ketua' => 'Ketua RW', 'nomor_whatsapp' => '081111111111']);
        $rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '98'], ['nama_rt' => 'RT 98', 'nama_ketua' => 'Ketua RT', 'nomor_whatsapp' => '082222222222']);
        $warga = Warga::create(['id_rt' => $rt->id_rt, 'nik' => '9999999999999998', 'nama' => 'Warga Kontak', 'jenis_kelamin' => 'L', 'status' => 'Aktif']);
        $akun = Akun::create(['id_warga' => $warga->id_warga, 'id_role' => $role->id_role, 'username' => 'warga-kontak', 'password' => 'secret', 'status' => 'Aktif']);

        Sanctum::actingAs($akun);

        $this->getJson('/api/warga/kontak-pengurus')
            ->assertOk()
            ->assertJsonPath('data.rt.nama_ketua', 'Ketua RT')
            ->assertJsonPath('data.rt.nomor_whatsapp', '082222222222')
            ->assertJsonPath('data.rw.nama_ketua', 'Ketua RW');
    }

    public function test_missing_contact_returns_expected_message(): void
    {
        $role = Role::firstOrCreate(['nama_role' => 'Warga']);
        $rw = Rw::firstOrCreate(['nomor_rw' => '97']);
        $rt = Rt::firstOrCreate(['id_rw' => $rw->id_rw, 'nomor_rt' => '97']);
        $warga = Warga::create(['id_rt' => $rt->id_rt, 'nik' => '9999999999999997', 'nama' => 'Warga Tanpa Kontak', 'jenis_kelamin' => 'P', 'status' => 'Aktif']);
        $akun = Akun::create(['id_warga' => $warga->id_warga, 'id_role' => $role->id_role, 'username' => 'warga-tanpa-kontak', 'password' => 'secret', 'status' => 'Aktif']);

        Sanctum::actingAs($akun);

        $this->getJson('/api/warga/kontak-pengurus')
            ->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'Kontak pengurus belum tersedia']);
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/warga/kontak-pengurus')->assertUnauthorized();
    }
}
