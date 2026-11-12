<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IuranDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Periode demo
            $periode = DB::table('periode')
                ->where('id_periode', 1)
                ->first();

            if (!$periode) {
                throw new RuntimeException(
                    'Periode dengan ID 1 tidak ditemukan di database online.'
                );
            }

            // Data 10 iuran demo
            $dataIuran = [
                ['wargademo01', 'Sudah Bayar'],
                ['wargademo02', 'Belum Bayar'],
                ['wargademo03', 'Belum Bayar'],
                ['wargademo04', 'Sudah Bayar'],
                ['wargademo05', 'Belum Bayar'],
                ['wargademo06', 'Belum Bayar'],
                ['wargademo07', 'Sudah Bayar'],
                ['wargademo08', 'Belum Bayar'],
                ['wargademo09', 'Sudah Bayar'],
                ['wargademo10', 'Belum Bayar'],
            ];

            $iuranOnline = [];

            foreach ($dataIuran as [$username, $status]) {

                $akun = DB::table('akun')
                    ->where('username', $username)
                    ->first();

                if (!$akun || !$akun->id_warga) {
                    throw new RuntimeException(
                        "Akun warga {$username} tidak ditemukan."
                    );
                }

                $warga = DB::table('warga')
                    ->where('id_warga', $akun->id_warga)
                    ->first();

                if (!$warga) {
                    throw new RuntimeException(
                        "Data warga {$username} tidak ditemukan."
                    );
                }

                $iuran = DB::table('iuran')
                    ->where('id_warga', $akun->id_warga)
                    ->where('id_periode', $periode->id_periode)
                    ->first();

                if (!$iuran) {
                    $idIuran = DB::table('iuran')->insertGetId([
                        'id_warga' => $akun->id_warga,
                        'id_periode' => $periode->id_periode,
                        'nominal' => 75000,
                        'jatuh_tempo' => '2026-08-10',
                        'status' => $status,
                        'created_at' => '2026-11-11 02:22:23',
                        'updated_at' => '2026-11-11 02:22:23',
                    ]);
                } else {
                    $idIuran = $iuran->id_iuran;

                    DB::table('iuran')
                        ->where('id_iuran', $idIuran)
                        ->update(['status' => $status]);
                }

                $iuranOnline[$username] = $idIuran;
            }

            // Admin dan Panitia online untuk referensi verifikasi
            $adminId = DB::table('akun')
                ->join('role', 'akun.id_role', '=', 'role.id_role')
                ->where('role.nama_role', 'Admin')
                ->value('akun.id_akun');

            $panitiaId = DB::table('akun')
                ->join('role', 'akun.id_role', '=', 'role.id_role')
                ->where('role.nama_role', 'Panitia')
                ->value('akun.id_akun');

            // Data 10 pembayaran demo
            $dataPembayaran = [
                ['wargademo01', '2026-11-01 09:00:00', 'Transfer BCA', 'Diterima', 'admin'],
                ['wargademo02', '2026-11-01 09:00:00', 'QRIS', 'Menunggu', null],
                ['wargademo03', '2026-11-01 09:00:00', 'Transfer BRI', 'Ditolak', 'panitia'],
                ['wargademo04', '2026-11-01 09:00:00', 'Transfer BCA', 'Diterima', 'admin'],
                ['wargademo05', '2026-11-01 09:00:00', 'QRIS', 'Menunggu', null],
                ['wargademo06', '2026-11-01 09:00:00', 'Transfer BRI', 'Ditolak', 'panitia'],
                ['wargademo07', '2026-11-01 09:00:00', 'Transfer BCA', 'Diterima', 'admin'],
                ['wargademo08', '2026-11-01 09:00:00', 'QRIS', 'Menunggu', null],
                ['wargademo09', '2026-11-01 09:00:00', 'Transfer BCA', 'Diterima', 'admin'],
                ['wargademo10', '2026-11-10 21:04:53', 'QRIS', 'Menunggu', null],
            ];

            foreach ($dataPembayaran as [$username, $tanggal, $metode, $status, $verifikator]) {

                $idIuran = $iuranOnline[$username];

                // Hindari pembayaran ganda
                $sudahAda = DB::table('pembayaran')
                    ->where('id_iuran', $idIuran)
                    ->where('tanggal_bayar', $tanggal)
                    ->where('metode', $metode)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $verifikatorId = null;

                if ($verifikator === 'admin') {
                    $verifikatorId = $adminId;
                } elseif ($verifikator === 'panitia') {
                    $verifikatorId = $panitiaId;
                }

                $isPembayaranCloudinary = $username === 'wargademo10';

                DB::table('pembayaran')->insert([
                    'id_iuran' => $idIuran,
                    'tanggal_bayar' => $tanggal,
                    'nominal' => 75000,
                    'metode' => $metode,

                    'bukti_bayar' => $isPembayaranCloudinary
                        ? 'https://res.cloudinary.com/qwto0skl/image/authenticated/s--XnrjajBI--/v1794344692/pad/bukti-pembayaran/lulh1dtk4cj4hkghsfim.jpg'
                        : null,

                    'cloudinary_public_id' => $isPembayaranCloudinary
                        ? 'pad/bukti-pembayaran/lulh1dtk4cj4hkghsfim'
                        : null,

                    'cloudinary_format' => $isPembayaranCloudinary
                        ? 'jpg'
                        : null,

                    'status_verifikasi' => $status,
                    'diverifikasi_oleh' => $verifikatorId,
                    'tanggal_verifikasi' => $status === 'Menunggu'
                        ? null
                        : '2026-11-02 10:00:00',

                    'created_at' => $isPembayaranCloudinary
                        ? '2026-11-10 21:04:53'
                        : '2026-11-11 02:27:17',

                    'updated_at' => $isPembayaranCloudinary
                        ? '2026-11-10 21:04:53'
                        : '2026-11-11 02:27:17',
                ]);
            }

        });

        $this->command->info(
            'Data dummy iuran dan pembayaran berhasil diproses.'
        );
    }
}
