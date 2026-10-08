<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WargaTestingSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder ini hanya untuk database lokal/testing.
        if (app()->environment('production')) {
            throw new RuntimeException(
                'WargaTestingSeeder tidak boleh dijalankan di production.'
            );
        }

        DB::transaction(function () {

            // 1. Cari role Warga
            $idRole = DB::table('role')
                ->where('nama_role', 'Warga')
                ->value('id_role');

            // 2. Cari RT 01
            $idRt = DB::table('rt')
                ->where('nama_rt', 'RT 01')
                ->value('id_rt');

            if (!$idRole || !$idRt) {
                throw new RuntimeException(
                    'Role Warga atau RT 01 belum tersedia.'
                );
            }

            // 3. Buat data warga jika belum ada
            $warga = DB::table('akun')
                ->where('username', 'warga_test')
                ->first();

            if ($warga) {
                $idWarga = $warga->id_warga;
            } else {
                $idWarga = DB::table('warga')->insertGetId([
                    'id_rt' => $idRt,
                    'nik' => null,
                    'nama' => 'Warga Testing PAD',
                    'no_hp' => null,
                    'alamat' => 'RT 01',
                    'jenis_kelamin' => 'L',
                    'tanggal_lahir' => null,
                    'status' => 'Aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Buat akun Warga jika belum ada
            DB::table('akun')->insertOrIgnore([
                'id_warga' => $idWarga,
                'id_role' => $idRole,
                'username' => 'warga_test',
                'password' => Hash::make('WargaTest123!'),
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->command->info(
            'Akun warga_test siap digunakan untuk testing.'
        );
    }
}
