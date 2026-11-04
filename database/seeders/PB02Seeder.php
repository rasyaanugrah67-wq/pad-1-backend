<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PB02Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Menambahkan data baru atau memperbarui data lama.
            // created_at hanya diisi saat data pertama kali dibuat.
            $simpan = function (
                string $tabel,
                array $kunci,
                array $nilai
            ): void {
                $query = DB::table($tabel)->where($kunci);

                if ($query->exists()) {
                    $query->update(array_merge(
                        $nilai,
                        ['updated_at' => now()]
                    ));
                } else {
                    DB::table($tabel)->insert(array_merge(
                        $kunci,
                        $nilai,
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    ));
                }
            };

            // 1. Data periode HUT RI
            $simpan(
                'periode',
                ['tahun' => 2026],
                [
                    'nama_kegiatan' => 'Semarak HUT RI ke-81',
                    'deskripsi' => 'Nusantara Bersuka, Indonesia Berjaya',
                    'status' => 'Aktif',
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
                $simpan(
                    'kategori_lomba',
                    ['nama_kategori' => $nama],
                    []
                );
            }

            // 3. Data lomba dan jadwal
            $daftarLomba = [
                [
                    'nama' => 'Tarik Tambang',
                    'kategori' => 'Dewasa',
                    'jenis_pendaftaran' => 'Beregu',
                    'tanggal' => '2026-08-15',
                    'mulai' => '09:00:00',
                    'selesai' => '11:00:00',
                    'lokasi' => 'Lapangan Timur',
                ],
                [
                    'nama' => 'Catur',
                    'kategori' => 'Umum',
                    'jenis_pendaftaran' => 'Individu',
                    'tanggal' => '2026-08-15',
                    'mulai' => '10:00:00',
                    'selesai' => '12:00:00',
                    'lokasi' => 'Balai Desa',
                ],
                [
                    'nama' => 'Futsal Sarung',
                    'kategori' => 'Dewasa',
                    'jenis_pendaftaran' => 'Beregu',
                    'tanggal' => '2026-08-16',
                    'mulai' => '09:00:00',
                    'selesai' => '11:30:00',
                    'lokasi' => 'Lapangan Utara',
                ],
                [
                    'nama' => 'Makan Kerupuk',
                    'kategori' => 'Umum',
                    'jenis_pendaftaran' => 'Individu',
                    'tanggal' => '2026-08-16',
                    'mulai' => '09:30:00',
                    'selesai' => '11:00:00',
                    'lokasi' => 'Depan Pendopo',
                ],
                [
                    'nama' => 'Mewarnai',
                    'kategori' => 'Anak-anak',
                    'jenis_pendaftaran' => 'Individu',
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

                // Simpan data lomba
                $simpan(
                    'lomba',
                    [
                        'id_periode' => $idPeriode,
                        'nama_lomba' => $item['nama'],
                    ],
                    [
                        'id_kategori' => $idKategori,
                        'jenis_pendaftaran' => $item['jenis_pendaftaran'],
                        'deskripsi' => 'Perlombaan dalam rangka HUT RI ke-81.',
                        'status' => 'Dibuka',
                    ]
                );

                $idLomba = DB::table('lomba')
                    ->where('id_periode', $idPeriode)
                    ->where('nama_lomba', $item['nama'])
                    ->value('id_lomba');

                // Simpan jadwal lomba
                $simpan(
                    'jadwal_lomba',
                    [
                        'id_lomba' => $idLomba,
                        'tanggal' => $item['tanggal'],
                    ],
                    [
                        'waktu_mulai' => $item['mulai'],
                        'waktu_selesai' => $item['selesai'],
                        'lokasi' => $item['lokasi'],
                    ]
                );
            }

        });

        $this->command?->info(
            'Data PB-02 berhasil disiapkan.'
        );
    }
}
