<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PanitiaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('DEMO_PANITIA_USERNAME');
        $password = env('DEMO_PANITIA_PASSWORD');

        if (!$username || !$password || strlen($password) < 12) {
            throw new RuntimeException(
                'Username dan password Panitia belum diatur atau password kurang dari 12 karakter.'
            );
        }

        DB::transaction(function () use ($username, $password) {

            if (DB::table('akun')->where('username', $username)->exists()) {
                throw new RuntimeException('Username Panitia sudah digunakan.');
            }

            $role = DB::table('role')
                ->where('nama_role', 'Panitia')
                ->first();

            $rt = DB::table('rt')
                ->where('nama_rt', 'RT 01')
                ->first();

            if (!$role || !$rt) {
                throw new RuntimeException('Role Panitia atau RT 01 tidak ditemukan.');
            }

            $wargaId = DB::table('warga')->insertGetId([
                'id_rt' => $rt->id_rt,
                'nik' => (string) random_int(1000000000000000, 9999999999999999),
                'nama' => 'Panitia Demo PAD',
                'no_hp' => '08' . random_int(10000000000, 99999999999),
                'alamat' => 'RT 01',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '1995-01-01',
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_warga');

            DB::table('akun')->insert([
                'id_warga' => $wargaId,
                'id_role' => $role->id_role,
                'username' => $username,
                'password' => Hash::make($password),
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->command->info('Akun Panitia Demo berhasil dibuat!');
    }
}