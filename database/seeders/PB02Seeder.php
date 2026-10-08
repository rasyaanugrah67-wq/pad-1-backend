<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PB02Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // 1. Data periode HUT RI
            DB::table('periode')->updateOrInsert(
                ['tahun' => 2026],
                [
                    'nama_kegiatan' => 'Semarak HUT RI ke-81',
                    'deskripsi' => 'Nusantara Bersuka, Indonesia Berjaya',
                    'status' => 'Aktif',
                    'updated_at' => now(),
                ]
            );

            $idPeriode = DB::table('periode')
                ->where('tahun', 2026)
                ->value('id_periode');

            // 2. Data kategori lomba
            $kategori = [
                'Dewasa',
                'Umum',
                'Anak-anak',
            ];

            foreach ($kategori as $nama) {
                DB::table('kategori_lomba')->updateOrInsert(
                    ['nama_kategori' => $nama],
                    ['updated_at' => now()]
                );
            }

            // 3. Data lomba dan jadwal
            $daftarLomba = [
                [
                    'nama' => 'Tarik Tambang',
                    'kategori' => 'Dewasa',
                    'tanggal' => '2026-08-15',
                    'mulai' => '09:00:00',
                    'selesai' => '11:00:00',
                    'lokasi' => 'Lapangan Timur',
                ],
                [
                    'nama' => 'Catur',
                    'kategori' => 'Umum',
                    'tanggal' => '2026-08-15',
                    'mulai' => '10:00:00',
                    'selesai' => '12:00:00',
                    'lokasi' => 'Balai Desa',
                ],
                [
                    'nama' => 'Futsal Sarung',
                    'kategori' => 'Dewasa',
                    'tanggal' => '2026-08-16',
                    'mulai' => '09:00:00',
                    'selesai' => '11:30:00',
                    'lokasi' => 'Lapangan Utara',
                ],
                [
                    'nama' => 'Makan Kerupuk',
                    'kategori' => 'Umum',
                    'tanggal' => '2026-08-16',
                    'mulai' => '09:30:00',
                    'selesai' => '11:00:00',
                    'lokasi' => 'Depan Pendopo',
                ],
                [
                    'nama' => 'Mewarnai',
                    'kategori' => 'Anak-anak',
                    'tanggal' => '2026-08-16',
                    'mulai' => '13:00:00',
                    'selesai' => '15:00:00',
                    'lokasi' => 'Aula Balai Desa',
                ],
            ];

            foreach ($daftarLomba as $item) {

                $idKategori = DB::table('kategori_lomba')
                    ->where('nama_kategori', $item['kategori'])
                    ->value('id_kategori');

                DB::table('lomba')->updateOrInsert(
                    [
                        'id_periode' => $idPeriode,
                        'nama_lomba' => $item['nama'],
                    ],
                    [
                        'id_kategori' => $idKategori,
                        'deskripsi' => 'Perlombaan dalam rangka HUT RI ke-81.',
                        'status' => 'Dibuka',
                        'updated_at' => now(),
                    ]
                );

                $idLomba = DB::table('lomba')
                    ->where('id_periode', $idPeriode)
                    ->where('nama_lomba', $item['nama'])
                    ->value('id_lomba');

                DB::table('jadwal_lomba')->updateOrInsert(
                    [
                        'id_lomba' => $idLomba,
                        'tanggal' => $item['tanggal'],
                    ],
                    [
                        'waktu_mulai' => $item['mulai'],
                        'waktu_selesai' => $item['selesai'],
                        'lokasi' => $item['lokasi'],
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}
