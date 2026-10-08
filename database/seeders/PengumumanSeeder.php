<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PengumumanSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder hanya untuk pengujian lokal.
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Seeder testing tidak boleh dijalankan di production.'
            );
        }

        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->first();

        $admin = DB::table('akun')
            ->join('role', 'akun.id_role', '=', 'role.id_role')
            ->where('role.nama_role', 'Admin')
            ->select('akun.id_akun')
            ->first();

        if (!$periode || !$admin) {
            throw new RuntimeException(
                'Periode aktif atau akun Admin belum tersedia.'
            );
        }

        $judul = 'Pengumuman Jadwal Lomba 17 Agustus';

        DB::table('pengumuman')->updateOrInsert(
            [
                'id_periode' => $periode->id_periode,
                'judul' => $judul
            ],
            [
                'id_akun' => $admin->id_akun,
                'isi' => 'Seluruh warga diharapkan hadir dan mengikuti perlombaan 17 Agustus sesuai jadwal yang telah ditentukan.',
                'tanggal_publikasi' => '2026-08-14 08:00:00',
                'status' => 'Dipublikasi',
                'updated_at' => now()
            ]
        );

        $this->command->info(
            'Data pengumuman berhasil disiapkan.'
        );
    }
}
