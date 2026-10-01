<?php

namespace Database\Seeders;

use App\Models\Rw;
use App\Models\Rt;
use Illuminate\Database\Seeder;

class RwRtSeeder extends Seeder
{
    public function run(): void
    {
        $rw = Rw::updateOrCreate(
            [
                'nomor_rw' => '01',
            ],
            [
                'nama_rw' => 'RW 01',
            ]
        );

        $rtList = [
            [
                'nomor_rt' => '01',
                'nama_rt' => 'RT 01',
            ],
            [
                'nomor_rt' => '02',
                'nama_rt' => 'RT 02',
            ],
            [
                'nomor_rt' => '03',
                'nama_rt' => 'RT 03',
            ],
        ];

        foreach ($rtList as $rt) {
            Rt::updateOrCreate(
                [
                    'id_rw' => $rw->id_rw,
                    'nomor_rt' => $rt['nomor_rt'],
                ],
                [
                    'nama_rt' => $rt['nama_rt'],
                ]
            );
        }
    }
}