<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Iuran;
use App\Models\TagihanIuran;
use App\Models\Warga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

class TagihanIuranController extends Controller
{
    /**
     * Mengambil daftar tagihan iuran.
     *
     * @OA\Get(
     *     path="/api/manajemen/tagihan",
     *     operationId="getDaftarTagihanIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Melihat daftar tagihan iuran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Cari berdasarkan nama tagihan",
     *         @OA\Schema(type="string", example="Iuran RT")
     *     ),
     *     @OA\Parameter(
     *         name="is_aktif",
     *         in="query",
     *         required=false,
     *         description="Filter status aktif (1 = aktif, 0 = nonaktif)",
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="id_periode",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan periode",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Jumlah data per halaman (1-50)",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar tagihan berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'     => ['nullable', 'string', 'max:150'],
            'is_aktif'   => ['nullable', 'boolean'],
            'id_periode' => ['nullable', 'integer', 'exists:periode,id_periode'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $query = TagihanIuran::with([
            'periode:id_periode,tahun,nama_kegiatan',
            'pembuat:id_akun,username',
        ])->withCount('iuran');

        if (!empty($validated['search'])) {
            $query->where('nama_tagihan', 'like', '%' . $validated['search'] . '%');
        }

        if (isset($validated['is_aktif'])) {
            $query->where('is_aktif', (bool) $validated['is_aktif']);
        }

        if (!empty($validated['id_periode'])) {
            $query->where('id_periode', $validated['id_periode']);
        }

        $data = $query->orderByDesc('id_tagihan')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan iuran berhasil diambil.',
            'data'    => $data,
        ]);
    }

    /**
     * Membuat tagihan iuran baru dan generate baris iuran per warga.
     *
     * @OA\Post(
     *     path="/api/manajemen/tagihan",
     *     operationId="buatTagihanIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Membuat tagihan iuran baru",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_tagihan","id_periode","nominal","jatuh_tempo","target"},
     *             @OA\Property(property="nama_tagihan", type="string", example="Iuran RT Agustus 2025"),
     *             @OA\Property(property="id_periode", type="integer", example=1),
     *             @OA\Property(property="nominal", type="number", format="float", example=50000),
     *             @OA\Property(property="jatuh_tempo", type="string", format="date", example="2025-08-31"),
     *             @OA\Property(property="keterangan", type="string", nullable=true, example="Iuran bulanan RT"),
     *             @OA\Property(property="target", type="string", enum={"Semua","Tertentu"}, example="Semua"),
     *             @OA\Property(property="id_warga", type="array", @OA\Items(type="integer"), nullable=true, description="Wajib jika target=Tertentu")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tagihan berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=422, description="Data tidak valid"),
     *     @OA\Response(response=500, description="Gagal membuat tagihan")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_tagihan' => ['required', 'string', 'max:150'],
            'id_periode'   => ['required', 'integer', 'exists:periode,id_periode'],
            'nominal'      => ['required', 'numeric', 'min:1'],
            'jatuh_tempo'  => ['required', 'date'],
            'keterangan'   => ['nullable', 'string', 'max:500'],
            'target'       => ['required', Rule::in(['Semua', 'Tertentu'])],
            'id_warga'     => [
                'required_if:target,Tertentu',
                'nullable',
                'array',
                'min:1',
            ],
            'id_warga.*'   => ['integer', 'exists:warga,id_warga'],
        ]);

        $idAkun = $request->user()->id_akun;

        try {
            $result = DB::transaction(function () use ($validated, $idAkun) {
                // 1. Simpan tagihan master.
                $tagihan = TagihanIuran::create([
                    'id_periode'   => $validated['id_periode'],
                    'nama_tagihan' => $validated['nama_tagihan'],
                    'nominal'      => $validated['nominal'],
                    'jatuh_tempo'  => $validated['jatuh_tempo'],
                    'keterangan'   => $validated['keterangan'] ?? null,
                    'target'       => $validated['target'],
                    'is_aktif'     => true,
                    'created_by'   => $idAkun,
                ]);

                // 2. Tentukan target warga.
                if ($validated['target'] === 'Semua') {
                    $wargaIds = Warga::where('status', 'Aktif')
                        ->pluck('id_warga')
                        ->toArray();
                } else {
                    $wargaIds = $validated['id_warga'];
                }

                // 3. Generate baris iuran per warga (hindari duplikat).
                $existingIds = Iuran::where('id_tagihan', $tagihan->id_tagihan)
                    ->pluck('id_warga')
                    ->toArray();

                $now = now();
                $rows = [];
                foreach ($wargaIds as $idWarga) {
                    if (in_array($idWarga, $existingIds, true)) {
                        continue;
                    }
                    $rows[] = [
                        'id_tagihan'  => $tagihan->id_tagihan,
                        'id_warga'    => $idWarga,
                        'id_periode'  => $tagihan->id_periode,
                        'nama_iuran'  => $tagihan->nama_tagihan,
                        'nominal'     => $tagihan->nominal,
                        'jatuh_tempo' => $tagihan->jatuh_tempo,
                        'keterangan'  => $tagihan->keterangan,
                        'status'      => 'Belum Bayar',
                        'is_aktif'    => true,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                if (!empty($rows)) {
                    Iuran::insert($rows);
                }

                $tagihan->load(['periode:id_periode,tahun,nama_kegiatan', 'pembuat:id_akun,username']);
                $tagihan->loadCount('iuran');

                return $tagihan;
            });

            return response()->json([
                'success' => true,
                'message' => 'Tagihan iuran berhasil dibuat.',
                'data'    => $result,
            ], 201);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tagihan iuran. Silakan coba kembali.',
            ], 500);
        }
    }

    /**
     * Mengambil detail tagihan iuran beserta daftar iuran warga.
     *
     * @OA\Get(
     *     path="/api/manajemen/tagihan/{id_tagihan}",
     *     operationId="getDetailTagihanIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Detail tagihan iuran + statistik pembayaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_tagihan",
     *         in="path",
     *         required=true,
     *         description="ID tagihan",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail tagihan berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=404, description="Tagihan tidak ditemukan")
     * )
     */
    public function show(int $id_tagihan): JsonResponse
    {
        $tagihan = TagihanIuran::with([
            'periode:id_periode,tahun,nama_kegiatan',
            'pembuat:id_akun,username',
            'iuran' => function ($q) {
                $q->with([
                    'warga:id_warga,nama',
                    'pembayaran' => function ($p) {
                        $p->orderByDesc('created_at')->limit(1);
                    },
                ]);
            },
        ])->find($id_tagihan);

        if (!$tagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan iuran tidak ditemukan.',
            ], 404);
        }

        $totalWarga      = $tagihan->iuran->count();
        $sudahBayar      = $tagihan->iuran->where('status', 'Sudah Bayar')->count();
        $belumBayar      = $tagihan->iuran->where('status', 'Belum Bayar')->count();
        $menungguKonfirm = $tagihan->iuran->filter(function ($iuran) {
            return $iuran->pembayaran->first()?->status_verifikasi === 'Menunggu';
        })->count();

        return response()->json([
            'success' => true,
            'message' => 'Detail tagihan iuran berhasil diambil.',
            'data'    => array_merge($tagihan->toArray(), [
                'statistik' => [
                    'total_warga'          => $totalWarga,
                    'sudah_bayar'          => $sudahBayar,
                    'belum_bayar'          => $belumBayar,
                    'menunggu_konfirmasi'  => $menungguKonfirm,
                ],
            ]),
        ]);
    }

    /**
     * Mengubah tagihan iuran (nama, nominal, jatuh_tempo, keterangan).
     *
     * @OA\Put(
     *     path="/api/manajemen/tagihan/{id_tagihan}",
     *     operationId="updateTagihanIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Mengubah tagihan iuran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_tagihan",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nama_tagihan", type="string", example="Iuran RT September 2025"),
     *             @OA\Property(property="nominal", type="number", format="float", example=60000),
     *             @OA\Property(property="jatuh_tempo", type="string", format="date", example="2025-09-30"),
     *             @OA\Property(property="keterangan", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tagihan berhasil diubah",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=404, description="Tagihan tidak ditemukan"),
     *     @OA\Response(response=422, description="Data tidak valid")
     * )
     */
    public function update(Request $request, int $id_tagihan): JsonResponse
    {
        $tagihan = TagihanIuran::find($id_tagihan);

        if (!$tagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan iuran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_tagihan' => ['sometimes', 'string', 'max:150'],
            'nominal'      => ['sometimes', 'numeric', 'min:1'],
            'jatuh_tempo'  => ['sometimes', 'date'],
            'keterangan'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($tagihan, $validated) {
            $nominalBerubah     = isset($validated['nominal']) &&
                (float) $validated['nominal'] !== (float) $tagihan->nominal;
            $jatuhTempoBerubah  = isset($validated['jatuh_tempo']) &&
                $validated['jatuh_tempo'] !== $tagihan->jatuh_tempo->toDateString();
            $namaBerubah        = isset($validated['nama_tagihan']) &&
                $validated['nama_tagihan'] !== $tagihan->nama_tagihan;

            $tagihan->update($validated);

            // Propagasi perubahan ke iuran yang masih Belum Bayar.
            if ($nominalBerubah || $jatuhTempoBerubah || $namaBerubah) {
                $updateIuran = [];
                if ($nominalBerubah) {
                    $updateIuran['nominal'] = $validated['nominal'];
                }
                if ($jatuhTempoBerubah) {
                    $updateIuran['jatuh_tempo'] = $validated['jatuh_tempo'];
                }
                if ($namaBerubah) {
                    $updateIuran['nama_iuran'] = $validated['nama_tagihan'];
                }

                if (!empty($updateIuran)) {
                    Iuran::where('id_tagihan', $tagihan->id_tagihan)
                        ->where('status', 'Belum Bayar')
                        ->update($updateIuran);
                }
            }
        });

        $tagihan->refresh()->load(['periode:id_periode,tahun,nama_kegiatan', 'pembuat:id_akun,username']);

        return response()->json([
            'success' => true,
            'message' => 'Tagihan iuran berhasil diperbarui.',
            'data'    => $tagihan,
        ]);
    }

    /**
     * Menonaktifkan tagihan iuran beserta seluruh iuran terkait.
     *
     * @OA\Patch(
     *     path="/api/manajemen/tagihan/{id_tagihan}/nonaktifkan",
     *     operationId="nonaktifkanTagihanIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Menonaktifkan tagihan iuran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_tagihan",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tagihan berhasil dinonaktifkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=404, description="Tagihan tidak ditemukan"),
     *     @OA\Response(response=422, description="Tagihan sudah nonaktif")
     * )
     */
    public function nonaktifkan(int $id_tagihan): JsonResponse
    {
        $tagihan = TagihanIuran::find($id_tagihan);

        if (!$tagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan iuran tidak ditemukan.',
            ], 404);
        }

        if (!$tagihan->is_aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan ini sudah dalam kondisi nonaktif.',
            ], 422);
        }

        DB::transaction(function () use ($tagihan) {
            $tagihan->is_aktif = false;
            $tagihan->save();

            Iuran::where('id_tagihan', $tagihan->id_tagihan)
                ->update(['is_aktif' => false]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Tagihan iuran berhasil dinonaktifkan.',
            'data'    => $tagihan->fresh(),
        ]);
    }

    /**
     * Mengambil status pembayaran per warga dari sebuah tagihan.
     *
     * @OA\Get(
     *     path="/api/manajemen/tagihan/{id_tagihan}/pembayaran",
     *     operationId="getStatusPembayaranWargaTagihan",
     *     tags={"Manajemen - Iuran"},
     *     summary="Status pembayaran warga dari sebuah tagihan",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_tagihan",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter status iuran",
     *         @OA\Schema(type="string", enum={"Semua","Belum Bayar","Sudah Bayar"}, example="Semua")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status pembayaran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=404, description="Tagihan tidak ditemukan")
     * )
     */
    public function statusPembayaranWarga(Request $request, int $id_tagihan): JsonResponse
    {
        $tagihan = TagihanIuran::find($id_tagihan);

        if (!$tagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan iuran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'status'   => ['nullable', Rule::in(['Semua', 'Belum Bayar', 'Sudah Bayar'])],
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $query = Iuran::with([
            'warga:id_warga,nama',
            'pembayaran' => function ($q) {
                $q->orderByDesc('created_at');
            },
        ])->where('id_tagihan', $id_tagihan);

        if (!empty($validated['status']) && $validated['status'] !== 'Semua') {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $query->whereHas('warga', function ($q) use ($validated) {
                $q->where('nama', 'like', '%' . $validated['search'] . '%');
            });
        }

        $data = $query->orderBy('id_iuran')->paginate($perPage);

        $data->through(function ($iuran) {
            $pembayaran = $iuran->pembayaran->first();

            if ($iuran->status === 'Sudah Bayar') {
                $statusTampilan = 'Lunas';
            } elseif ($pembayaran && $pembayaran->status_verifikasi === 'Menunggu') {
                $statusTampilan = 'Menunggu Konfirmasi';
            } else {
                $statusTampilan = 'Belum Lunas';
            }

            return [
                'id_iuran'            => $iuran->id_iuran,
                'nama_kepala_keluarga' => $iuran->warga?->nama,
                'nominal'             => $iuran->nominal,
                'jatuh_tempo'         => $iuran->jatuh_tempo,
                'status'              => $statusTampilan,
                'status_verifikasi'   => $pembayaran?->status_verifikasi,
                'tanggal_bayar'       => $pembayaran?->tanggal_bayar,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran warga berhasil diambil.',
            'data'    => $data,
        ]);
    }
}
