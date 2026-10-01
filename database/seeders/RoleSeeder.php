<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id_role' => 1,
                'nama_role' => 'Admin',
                'deskripsi' => 'Mengelola keseluruhan sistem dan data administrasi.',
            ],
            [
                'id_role' => 2,
                'nama_role' => 'Panitia',
                'deskripsi' => 'Mengelola operasional perlombaan dan kegiatan.',
            ],
            [
                'id_role' => 3,
                'nama_role' => 'Warga',
                'deskripsi' => 'Mengakses layanan perlombaan sebagai warga.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                [
                    'id_role' => $role['id_role'],
                ],
                [
                    'nama_role' => $role['nama_role'],
                    'deskripsi' => $role['deskripsi'],
                ]
            );
        }
    }
}