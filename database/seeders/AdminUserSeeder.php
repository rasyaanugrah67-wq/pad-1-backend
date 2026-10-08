<?php

namespace Database\Seeders;

use App\Models\Rt;
use App\Models\Akun;
use App\Models\Warga;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $rt = Rt::where('nomor_rt', '01')
            ->whereHas('rw', function ($query) {
                $query->where('nomor_rw', '01');
            })
            ->firstOrFail();

        $adminWarga = Warga::updateOrCreate(
            [
                'nik' => '0000000000000001',
            ],
            [
                'id_rt' => $rt->id_rt,
                'nama' => 'Administrator',
                'no_hp' => '6281234567890',
                'alamat' => 'Sekretariat RW 01',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2000-01-01',
                'status' => 'Aktif',
            ]
        );

        Akun::updateOrCreate(
            [
                'username' => env('ADMIN_USERNAME', 'admin'),
            ],
            [
                'id_warga' => $adminWarga->id_warga,
                'id_role' => 1,

                'password' => Hash::make(
                    env('ADMIN_PASSWORD')
                ),

                'status' => 'Aktif',
            ]
        );
    }
}