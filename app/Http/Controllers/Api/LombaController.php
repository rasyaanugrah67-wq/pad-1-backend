<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lomba;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LombaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/warga/lomba",
     *     operationId="getDaftarLombaWarga",
     *     tags={"Warga - Lomba"},
     *     summary="Menampilkan daftar lomba",
     *     description="Mengambil daftar lomba pada periode aktif. Mendukung pencarian nama lomba, filter status, dan filter tanggal. Informasi kapasitas peserta dan batas anggota tim turut ditampilkan.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Pencarian berdasarkan nama lomba",
     *         @OA\Schema(type="string", example="Futsal")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter status lomba",
     *         @OA\Schema(
     *             type="string",
     *             enum={"Semua","Segera","Selesai"},
     *             example="Semua"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="tanggal",
     *         in="query",
     *         required=false,
     *         description="Filter tanggal jadwal lomba",
     *         @OA\Schema(type="string", format="date", example="2026-08-17")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar lomba berhasil ditampilkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar lomba berhasil ditampilkan."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id_lomba", type="integer", example=3),
     *                     @OA\Property(property="nama_lomba", type="string", example="Futsal Sarung"),
     *                     @OA\Property(property="jenis_pendaftaran", type="string", example="Beregu"),
     *                     @OA\Property(property="status", type="string", example="Dibuka"),
     *                     @OA\Property(property="deskripsi", type="string", nullable=true),
     *                     @OA\Property(
     *                         property="kategori",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id_kategori", type="integer", example=2),
     *                         @OA\Property(property="nama_kategori", type="string", example="Umum")
     *                     ),
     *                     @OA\Property(property="kapasitas_peserta", type="integer", nullable=true, example=4),
     *                     @OA\Property(property="batas_anggota_tim", type="integer", nullable=true, example=5),
     *                     @OA\Property(property="sudah_mendaftar", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="jadwal",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id_jadwal", type="integer", example=1),
     *                             @OA\Property(property="tanggal", type="string", format="date", example="2026-08-17"),
     *                             @OA\Property(property="waktu_mulai", type="string", nullable=true, example="08:00:00"),
     *                             @OA\Property(property="waktu_selesai", type="string", nullable=true, example="10:00:00"),
     *                             @OA\Property(property="lokasi", type="string", nullable=true, example="Lapangan RW"),
     *                             @OA\Property(property="status_jadwal", type="string", example="Selesai")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="tanggal_tersedia",
     *                 type="array",
     *                 @OA\Items(type="string", format="date", example="2026-08-17")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum terautentikasi")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['Semua', 'Segera', 'Selesai']),
            ],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'Semua';
        $tanggal = $validated['tanggal'] ?? null;

        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        if (!$periode) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada periode kegiatan aktif.',
                'data' => [],
                'tanggal_tersedia' => [],
            ]);
        }

        $query = Lomba::with([
            'kategori',
            'jadwal' => function ($query) {
                $query->orderBy('tanggal')
                    ->orderBy('waktu_mulai');
            },
        ])
            ->where('id_periode', $periode->id_periode)
            ->where('status', '!=', 'Nonaktif');

        if ($search) {
            $query->where('nama_lomba', 'like', "%{$search}%");
        }

        if ($status === 'Segera') {
            $query->where('status', 'Dibuka')
                ->whereHas('jadwal', function ($q) {
                    $q->where(function ($jadwalQuery) {
                        $jadwalQuery
                            ->whereDate('tanggal', '>', now()->toDateString())
                            ->orWhere(function ($todayQuery) {
                                $todayQuery
                                    ->whereDate('tanggal', now()->toDateString())
                                    ->where(function ($timeQuery) {
                                        $timeQuery
                                            ->whereNull('waktu_mulai')
                                            ->orWhereTime(
                                                'waktu_mulai',
                                                '>=',
                                                now()->format('H:i:s')
                                            );
                                    });
                            });
                    });
                });
        }

        if ($status === 'Selesai') {
            $query->where('status', 'Selesai');
        }

        $lomba = $query->orderBy('nama_lomba')->get();

        $idWarga = $request->user()->id_warga ?? null;

        $data = $lomba->map(function ($item) use ($idWarga) {
            return $this->formatLomba($item, $idWarga);
        });

        $tanggalTersedia = $lomba
            ->flatMap(function ($item) {
                return $item->jadwal->pluck('tanggal');
            })
            ->map(function ($tanggal) {
                return Carbon::parse($tanggal)->format('Y-m-d');
            })
            ->unique()
            ->sort()
            ->values();

        if ($tanggal) {
            $data = $data->filter(function ($item) use ($tanggal) {
                return collect($item['jadwal'])
                    ->contains('tanggal', $tanggal);
            })->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar lomba berhasil ditampilkan.',
            'data' => $data,
            'tanggal_tersedia' => $tanggalTersedia,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/warga/lomba/{id}",
     *     operationId="getDetailLombaWarga",
     *     tags={"Warga - Lomba"},
     *     summary="Menampilkan detail lomba",
     *     description="Mengambil informasi lengkap lomba, termasuk kategori, jadwal, persyaratan, alur, peraturan, kapasitas peserta, dan batas anggota tim.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID lomba",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail lomba berhasil ditampilkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail lomba berhasil ditampilkan."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_lomba", type="integer", example=3),
     *                 @OA\Property(property="nama_lomba", type="string", example="Futsal Sarung"),
     *                 @OA\Property(property="jenis_pendaftaran", type="string", example="Beregu"),
     *                 @OA\Property(property="status", type="string", example="Dibuka"),
     *                 @OA\Property(property="deskripsi", type="string", nullable=true),
     *                 @OA\Property(
     *                     property="kategori",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id_kategori", type="integer", example=2),
     *                     @OA\Property(property="nama_kategori", type="string", example="Umum")
     *                 ),
     *                 @OA\Property(property="kapasitas_peserta", type="integer", nullable=true, example=4),
     *                 @OA\Property(property="batas_anggota_tim", type="integer", nullable=true, example=5),
     *                 @OA\Property(property="sudah_mendaftar", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="jadwal",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id_jadwal", type="integer", example=1),
     *                         @OA\Property(property="tanggal", type="string", format="date", example="2026-08-17"),
     *                         @OA\Property(property="waktu_mulai", type="string", nullable=true, example="08:00:00"),
     *                         @OA\Property(property="waktu_selesai", type="string", nullable=true, example="10:00:00"),
     *                         @OA\Property(property="lokasi", type="string", nullable=true, example="Lapangan RW"),
     *                         @OA\Property(property="status_jadwal", type="string", example="Selesai")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="detail",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="deskripsi", type="string", nullable=true),
     *                     @OA\Property(property="alur", type="string", nullable=true),
     *                     @OA\Property(property="syarat", type="string", nullable=true),
     *                     @OA\Property(property="peraturan", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan atau periode aktif belum tersedia"),
     *     @OA\Response(response=401, description="Belum terautentikasi")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        if (!$periode) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada periode kegiatan aktif.',
                'data' => null,
            ], 404);
        }

        $lomba = Lomba::with([
            'kategori',
            'detail',
            'jadwal' => function ($query) {
                $query->orderBy('tanggal')
                    ->orderBy('waktu_mulai');
            },
        ])
            ->where('id_periode', $periode->id_periode)
            ->where('status', '!=', 'Nonaktif')
            ->where('id_lomba', $id)
            ->first();

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $idWarga = $request->user()->id_warga ?? null;

        $data = $this->formatLomba($lomba, $idWarga, true);

        return response()->json([
            'success' => true,
            'message' => 'Detail lomba berhasil ditampilkan.',
            'data' => $data,
        ]);
    }

    /**
     * Format data lomba agar sesuai kebutuhan frontend.
     */
    private function formatLomba(
        Lomba $lomba,
        ?int $idWarga,
        bool $includeDetail = false
    ): array {
        $jadwal = $lomba->jadwal->map(function ($item) {
            $tanggal = Carbon::parse($item->tanggal);

            if ($item->waktu_selesai) {
                $batasWaktu = Carbon::parse(
                    $tanggal->format('Y-m-d') . ' ' . $item->waktu_selesai
                );
            } elseif ($item->waktu_mulai) {
                $batasWaktu = Carbon::parse(
                    $tanggal->format('Y-m-d') . ' ' . $item->waktu_mulai
                );
            } else {
                $batasWaktu = $tanggal->copy()->endOfDay();
            }

            return [
                'id_jadwal' => $item->id_jadwal,
                'tanggal' => $tanggal->format('Y-m-d'),
                'waktu_mulai' => $item->waktu_mulai,
                'waktu_selesai' => $item->waktu_selesai,
                'lokasi' => $item->lokasi,
                'status_jadwal' => now()->lte($batasWaktu)
                    ? 'Segera'
                    : 'Selesai',
            ];
        });

        $sudahMendaftar = false;

        if ($idWarga) {
            $sudahMendaftar = $lomba->pendaftaran()
                ->where('id_warga', $idWarga)
                ->exists();
        }

        $data = [
            'id_lomba' => $lomba->id_lomba,
            'nama_lomba' => $lomba->nama_lomba,
            'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
            'status' => $lomba->status,
            'deskripsi' => $lomba->deskripsi,

            'kategori' => $lomba->kategori ? [
                'id_kategori' => $lomba->kategori->id_kategori,
                'nama_kategori' => $lomba->kategori->nama_kategori,
            ] : null,

            'kapasitas_peserta' => $lomba->kapasitas_peserta,
            'batas_anggota_tim' => $lomba->batas_anggota_tim,
            'sudah_mendaftar' => $sudahMendaftar,

            'jadwal' => $jadwal->values(),
        ];

        if ($includeDetail) {
            $data['detail'] = $lomba->detail ? [
                'deskripsi' => $lomba->detail->deskripsi,
                'alur' => $lomba->detail->alur,
                'syarat' => $lomba->detail->syarat,
                'peraturan' => $lomba->detail->peraturan,
            ] : null;
        }

        return $data;
    }
}
