<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class WargaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('DEMO_WARGA_USERNAME');
        $password = env('DEMO_WARGA_PASSWORD');

        if (!$username || !$password || strlen($password) < 12) {
            throw new RuntimeException(
                'DEMO_WARGA_USERNAME dan DEMO_WARGA_PASSWORD (minimal 12 karakter) wajib diatur.'
            );
        }

        if ($username === 'admin') {
            throw new RuntimeException(
                'Username demo tidak boleh menggunakan akun admin.'
            );
        }

        DB::transaction(function () use ($username, $password) {

            $idRole = DB::table('role')
                ->where('nama_role', 'Warga')
                ->value('id_role');

            $idRt = DB::table('rt')
                ->where('nama_rt', 'RT 01')
                ->value('id_rt');

            if (!$idRole || !$idRt) {
                throw new RuntimeException(
                    'Role Warga atau RT 01 belum tersedia.'
                );
            }

            if (DB::table('akun')->where('username', $username)->exists()) {
                throw new RuntimeException(
                    'Username sudah digunakan. Seeder tidak akan mengubah akun yang ada.'
                );
            }

            $idWarga = DB::table('warga')->insertGetId([
                'id_rt' => $idRt,
                'nik' => (string) random_int(1000000000000000, 9999999999999999),
                'nama' => 'Warga Demo PAD',
                'no_hp' => '08' . random_int(10000000000, 99999999999),
                'alamat' => 'RT 01',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2000-01-01',
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('akun')->insert([
                'id_warga' => $idWarga,
                'id_role' => $idRole,
                'username' => $username,
                'password' => Hash::make($password),
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->command?->info('Akun Warga demo berhasil dibuat.');
    }
}
