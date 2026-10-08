<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PertandinganSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Seeder testing tidak boleh dijalankan di production.'
            );
        }

        $lomba = DB::table('lomba')
            ->where('nama_lomba', 'Tarik Tambang')
            ->first();

        if (!$lomba) {
            throw new RuntimeException(
                'Lomba Tarik Tambang belum tersedia.'
            );
        }

        DB::table('pertandingan')->updateOrInsert(
            [
                'id_lomba' => $lomba->id_lomba,
                'peserta_1' => 'RT 01',
                'peserta_2' => 'RT 02',
            ],
            [
                'waktu_mulai' => '2026-08-15 09:00:00',
                'status' => 'Berlangsung',
                'updated_at' => now(),
            ]
        );

        $this->command->info(
            'Data pertandingan berhasil disiapkan.'
        );
    }
}
