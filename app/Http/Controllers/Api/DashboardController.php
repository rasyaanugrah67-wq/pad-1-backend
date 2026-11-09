<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    /**
     * Dashboard bersama untuk Admin dan Panitia.
     *
     * @OA\Get(
     *     path="/api/dashboard",
     *     operationId="getDashboardAdminPanitia",
     *     tags={"Dashboard"},
     *     summary="Mengambil ringkasan dashboard Admin dan Panitia",
     *     description="Mengembalikan ringkasan periode aktif, pertandingan yang sedang berlangsung, dan agenda pada tanggal yang dipilih. Hanya dapat diakses Admin dan Panitia.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tanggal",
     *         in="query",
     *         required=false,
     *         description="Tanggal agenda dalam zona waktu Asia/Jakarta. Default tanggal hari ini.",
     *         @OA\Schema(type="string", format="date", example="2026-08-17")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data dashboard berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data dashboard berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="tanggal_filter", type="string", format="date", example="2026-08-17"),
     *                 @OA\Property(
     *                     property="ringkasan",
     *                     type="object",
     *                     @OA\Property(property="total_lomba", type="integer", example=12),
     *                     @OA\Property(property="lomba_berlangsung", type="integer", example=2),
     *                     @OA\Property(property="total_pendaftar", type="integer", example=25),
     *                     @OA\Property(property="pembayaran_lunas", type="integer", example=31),
     *                     @OA\Property(property="pembayaran_belum_lunas", type="integer", example=14)
     *                 ),
     *                 @OA\Property(
     *                     property="sedang_berlangsung",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id_pertandingan", type="integer", example=1),
     *                         @OA\Property(property="id_lomba", type="integer", example=1),
     *                         @OA\Property(property="nama_lomba", type="string", example="Tarik Tambang"),
     *                         @OA\Property(property="lokasi", type="string", nullable=true, example="Lapangan Timur"),
     *                         @OA\Property(property="babak", type="string", nullable=true, example="Penyisihan"),
     *                         @OA\Property(property="peserta_1", type="string", example="Tim A"),
     *                         @OA\Property(property="peserta_2", type="string", example="Tim B"),
     *                         @OA\Property(property="waktu_mulai", type="string", example="08:30:00"),
     *                         @OA\Property(property="waktu_selesai", type="string", nullable=true, example="09:00:00"),
     *                         @OA\Property(property="status", type="string", example="Berlangsung")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="agenda",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id_jadwal", type="integer", example=1),
     *                         @OA\Property(property="id_lomba", type="integer", example=1),
     *                         @OA\Property(property="nama_lomba", type="string", example="Balap Karung"),
     *                         @OA\Property(property="tanggal", type="string", format="date", example="2026-08-17"),
     *                         @OA\Property(property="waktu_mulai", type="string", nullable=true, example="08:00:00"),
     *                         @OA\Property(property="waktu_selesai", type="string", nullable=true, example="08:30:00"),
     *                         @OA\Property(property="lokasi", type="string", nullable=true, example="Lapangan Utama")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Hanya Admin dan Panitia yang dapat mengakses endpoint ini"),
     *     @OA\Response(response=422, description="Format tanggal tidak valid")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        $now = CarbonImmutable::now(self::TIMEZONE);
        $tanggalFilter = $validated['tanggal'] ?? $now->toDateString();

        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->orderByDesc('id_periode')
            ->first();

        if (!$periode) {
            return $this->emptyDashboard($tanggalFilter);
        }

        $idPeriode = $periode->id_periode;

        $lombaPeriode = DB::table('lomba')
            ->where('id_periode', $idPeriode)
            ->where('status', '!=', 'Nonaktif');

        $ringkasan = [
            'total_lomba' => (clone $lombaPeriode)->count(),
            'lomba_berlangsung' => DB::table('jadwal_lomba as j')
                ->join('lomba as l', 'l.id_lomba', '=', 'j.id_lomba')
                ->where('l.id_periode', $idPeriode)
                ->where('l.status', '!=', 'Nonaktif')
                ->whereDate('j.tanggal', $now->toDateString())
                ->whereNotNull('j.waktu_mulai')
                ->whereNotNull('j.waktu_selesai')
                ->whereTime('j.waktu_mulai', '<=', $now->format('H:i:s'))
                ->whereTime('j.waktu_selesai', '>=', $now->format('H:i:s'))
                ->distinct()
                ->count('l.id_lomba'),
            'total_pendaftar' => DB::table('pendaftaran as p')
                ->join('lomba as l', 'l.id_lomba', '=', 'p.id_lomba')
                ->where('l.id_periode', $idPeriode)
                ->whereIn('p.status', ['Menunggu', 'Diterima'])
                ->count(),
            'pembayaran_lunas' => DB::table('iuran')
                ->where('id_periode', $idPeriode)
                ->where('status', 'Sudah Bayar')
                ->count(),
            'pembayaran_belum_lunas' => DB::table('iuran')
                ->where('id_periode', $idPeriode)
                ->where('status', 'Belum Bayar')
                ->count(),
        ];

        $sedangBerlangsung = DB::table('pertandingan as p')
            ->join('lomba as l', 'l.id_lomba', '=', 'p.id_lomba')
            ->leftJoin('jadwal_lomba as j', 'j.id_jadwal', '=', 'p.id_jadwal')
            ->where('l.id_periode', $idPeriode)
            ->where('p.status', 'Berlangsung')
            ->whereDate('p.waktu_mulai', $now->toDateString())
            ->where('p.waktu_mulai', '<=', $now->format('Y-m-d H:i:s'))
            ->where(function ($query) use ($now) {
                $query->whereNull('p.waktu_selesai')
                    ->orWhere('p.waktu_selesai', '>=', $now->format('Y-m-d H:i:s'));
            })
            ->orderBy('p.waktu_mulai')
            ->select(
                'p.id_pertandingan',
                'l.id_lomba',
                'l.nama_lomba',
                'j.lokasi',
                'p.babak',
                'p.peserta_1',
                'p.peserta_2',
                'p.waktu_mulai',
                'p.waktu_selesai',
                'p.status'
            )
            ->get()
            ->map(function ($pertandingan) {
                return [
                    'id_pertandingan' => $pertandingan->id_pertandingan,
                    'id_lomba' => $pertandingan->id_lomba,
                    'nama_lomba' => $pertandingan->nama_lomba,
                    'lokasi' => $pertandingan->lokasi,
                    'babak' => $pertandingan->babak,
                    'peserta_1' => $pertandingan->peserta_1,
                    'peserta_2' => $pertandingan->peserta_2,
                    'waktu_mulai' => CarbonImmutable::parse(
                        $pertandingan->waktu_mulai,
                        self::TIMEZONE
                    )->format('H:i:s'),
                    'waktu_selesai' => $pertandingan->waktu_selesai
                        ? CarbonImmutable::parse(
                            $pertandingan->waktu_selesai,
                            self::TIMEZONE
                        )->format('H:i:s')
                        : null,
                    'status' => $pertandingan->status,
                ];
            })
            ->values();

        $agenda = DB::table('jadwal_lomba as j')
            ->join('lomba as l', 'l.id_lomba', '=', 'j.id_lomba')
            ->where('l.id_periode', $idPeriode)
            ->where('l.status', '!=', 'Nonaktif')
            ->whereDate('j.tanggal', $tanggalFilter)
            ->orderBy('j.waktu_mulai')
            ->orderBy('l.nama_lomba')
            ->select(
                'j.id_jadwal',
                'l.id_lomba',
                'l.nama_lomba',
                'j.tanggal',
                'j.waktu_mulai',
                'j.waktu_selesai',
                'j.lokasi'
            )
            ->get()
            ->map(function ($jadwal) {
                return [
                    'id_jadwal' => $jadwal->id_jadwal,
                    'id_lomba' => $jadwal->id_lomba,
                    'nama_lomba' => $jadwal->nama_lomba,
                    'tanggal' => CarbonImmutable::parse(
                        $jadwal->tanggal,
                        self::TIMEZONE
                    )->format('Y-m-d'),
                    'waktu_mulai' => $jadwal->waktu_mulai,
                    'waktu_selesai' => $jadwal->waktu_selesai,
                    'lokasi' => $jadwal->lokasi,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Data dashboard berhasil diambil.',
            'data' => [
                'tanggal_filter' => $tanggalFilter,
                'ringkasan' => $ringkasan,
                'sedang_berlangsung' => $sedangBerlangsung,
                'agenda' => $agenda,
            ],
        ]);
    }

    /**
     * Compatibility handlers for the original role-specific dashboard routes.
     *
     * @OA\Get(
     *     path="/api/admin/dashboard",
     *     operationId="getDashboardAdminCompatibility",
     *     tags={"Dashboard"},
     *     summary="Dashboard Admin (compatibility route)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Data dashboard bersama berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function admin(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * @OA\Get(
     *     path="/api/panitia/dashboard",
     *     operationId="getDashboardPanitiaCompatibility",
     *     tags={"Dashboard"},
     *     summary="Dashboard Panitia (compatibility route)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Data dashboard bersama berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login atau token tidak valid"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function panitia(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Dashboard warga tetap terpisah dari dashboard pengelola.
     *
     * @OA\Get(
     *     path="/api/warga/dashboard",
     *     operationId="getDashboardWarga",
     *     tags={"Dashboard Warga"},
     *     summary="Mengakses dashboard Warga",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Dashboard Warga berhasil diakses"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function warga(): JsonResponse
    {
        return response()->json([
            'message' => 'Dashboard Warga',
        ]);
    }

    private function emptyDashboard(string $tanggalFilter): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Belum ada periode kegiatan aktif.',
            'data' => [
                'tanggal_filter' => $tanggalFilter,
                'ringkasan' => [
                    'total_lomba' => 0,
                    'lomba_berlangsung' => 0,
                    'total_pendaftar' => 0,
                    'pembayaran_lunas' => 0,
                    'pembayaran_belum_lunas' => 0,
                ],
                'sedang_berlangsung' => [],
                'agenda' => [],
            ],
        ]);
    }
}
