<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BerandaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/warga/beranda",
     *     operationId="getBerandaWarga",
     *     tags={"PB-02 Beranda Warga"},
     *     summary="Menampilkan data beranda warga",
     *     description="Mengambil banner, jadwal lomba, pengumuman terbaru, dan pertandingan yang sedang berlangsung. Endpoint khusus role Warga.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="tanggal",
     *         in="query",
     *         required=false,
     *         description="Tanggal agenda dengan format YYYY-MM-DD. Default menggunakan tanggal hari ini.",
     *         @OA\Schema(
     *             type="string",
     *             format="date",
     *             example="2026-08-15"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data beranda warga berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Data beranda warga berhasil diambil"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="banner",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(
     *                         property="id_periode",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="judul",
     *                         type="string",
     *                         example="Semarak HUT RI ke-81"
     *                     ),
     *                     @OA\Property(
     *                         property="tahun",
     *                         type="string",
     *                         example="2026"
     *                     ),
     *                     @OA\Property(
     *                         property="deskripsi",
     *                         type="string",
     *                         example="Nusantara Bersuka, Indonesia Berjaya"
     *                     ),
     *                     @OA\Property(
     *                         property="logo",
     *                         type="string",
     *                         nullable=true
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="tanggal_filter",
     *                     type="string",
     *                     format="date",
     *                     example="2026-08-15"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="jadwal_agenda",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(
     *                             property="id_jadwal",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="id_lomba",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="nama_lomba",
     *                             type="string",
     *                             example="Tarik Tambang"
     *                         ),
     *                         @OA\Property(
     *                             property="nama_kategori",
     *                             type="string",
     *                             example="Dewasa"
     *                         ),
     *                         @OA\Property(
     *                             property="tanggal",
     *                             type="string",
     *                             format="date",
     *                             example="2026-08-15"
     *                         ),
     *                         @OA\Property(
     *                             property="waktu_mulai",
     *                             type="string",
     *                             example="09:00:00"
     *                         ),
     *                         @OA\Property(
     *                             property="waktu_selesai",
     *                             type="string",
     *                             example="11:00:00"
     *                         ),
     *                         @OA\Property(
     *                             property="lokasi",
     *                             type="string",
     *                             example="Lapangan Timur"
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pengumuman_terbaru",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(
     *                         property="id_pengumuman",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="judul",
     *                         type="string",
     *                         example="Pengumuman Jadwal Lomba 17 Agustus"
     *                     ),
     *                     @OA\Property(
     *                         property="isi",
     *                         type="string",
     *                         example="Seluruh warga diharapkan hadir dan mengikuti perlombaan 17 Agustus."
     *                     ),
     *                     @OA\Property(
     *                         property="tanggal_publikasi",
     *                         type="string",
     *                         example="2026-08-14 08:00:00"
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="sedang_berlangsung",
     *                     type="array",
     *                     description="Daftar pertandingan yang berstatus Berlangsung",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(
     *                             property="id_pertandingan",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="id_lomba",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="nama_lomba",
     *                             type="string",
     *                             example="Tarik Tambang"
     *                         ),
     *                         @OA\Property(
     *                             property="peserta_1",
     *                             type="string",
     *                             example="RT 01"
     *                         ),
     *                         @OA\Property(
     *                             property="peserta_2",
     *                             type="string",
     *                             example="RT 02"
     *                         ),
     *                         @OA\Property(
     *                             property="waktu_mulai",
     *                             type="string",
     *                             example="2026-08-15 09:00:00"
     *                         ),
     *                         @OA\Property(
     *                             property="status",
     *                             type="string",
     *                             example="Berlangsung"
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Pengguna belum login"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Pengguna tidak memiliki role Warga"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Format tanggal tidak valid"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Ambil dan validasi tanggal
        $tanggal = $request->query(
            'tanggal',
            now()->toDateString()
        );

        $request->merge(['tanggal' => $tanggal]);

        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
        ]);

        // 2. Ambil periode kegiatan aktif
        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        // 3. Ambil jadwal lomba sesuai tanggal
        $jadwal = DB::table('jadwal_lomba as j')
            ->join(
                'lomba as l',
                'j.id_lomba',
                '=',
                'l.id_lomba'
            )
            ->join(
                'kategori_lomba as k',
                'l.id_kategori',
                '=',
                'k.id_kategori'
            )
            ->when($periode, function ($query) use ($periode) {
                $query->where(
                    'l.id_periode',
                    $periode->id_periode
                );
            })
            ->whereDate('j.tanggal', $tanggal)
            ->where('l.status', 'Dibuka')
            ->orderBy('j.waktu_mulai')
            ->select(
                'j.id_jadwal',
                'l.id_lomba',
                'l.nama_lomba',
                'k.nama_kategori',
                'j.tanggal',
                'j.waktu_mulai',
                'j.waktu_selesai',
                'j.lokasi'
            )
            ->get();

        // 4. Ambil pengumuman terbaru
        $pengumuman = null;

        if ($periode) {
            $pengumuman = DB::table('pengumuman')
                ->where('id_periode', $periode->id_periode)
                ->where('status', 'Dipublikasi')
                ->whereNotNull('tanggal_publikasi')
                ->where('tanggal_publikasi', '<=', now())
                ->orderByDesc('tanggal_publikasi')
                ->first();
        }

        // 5. Ambil pertandingan yang sedang berlangsung
        $pertandingan = DB::table('pertandingan as p')
            ->join(
                'lomba as l',
                'p.id_lomba',
                '=',
                'l.id_lomba'
            )
            ->where('p.status', 'Berlangsung')
            ->when($periode, function ($query) use ($periode) {
                $query->where(
                    'l.id_periode',
                    $periode->id_periode
                );
            })
            ->select(
                'p.id_pertandingan',
                'l.id_lomba',
                'l.nama_lomba',
                'p.peserta_1',
                'p.peserta_2',
                'p.waktu_mulai',
                'p.status'
            )
            ->orderBy('p.waktu_mulai')
            ->get();

        // 6. Kirim respons JSON
        return response()->json([
            'success' => true,
            'message' => 'Data beranda warga berhasil diambil',
            'data' => [
                'banner' => $periode ? [
                    'id_periode' => $periode->id_periode,
                    'judul' => $periode->nama_kegiatan,
                    'tahun' => $periode->tahun,
                    'deskripsi' => $periode->deskripsi,
                    'logo' => $periode->logo,
                ] : null,

                'tanggal_filter' => $tanggal,

                'jadwal_agenda' => $jadwal,

                'pengumuman_terbaru' => $pengumuman ? [
                    'id_pengumuman' => $pengumuman->id_pengumuman,
                    'judul' => $pengumuman->judul,
                    'isi' => $pengumuman->isi,
                    'tanggal_publikasi' =>
                        $pengumuman->tanggal_publikasi,
                ] : null,

                'sedang_berlangsung' => $pertandingan,
            ],
        ]);
    }
}
