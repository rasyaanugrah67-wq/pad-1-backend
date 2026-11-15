<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Iuran;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class IuranController extends Controller
{
    /**
     * Mengambil daftar iuran milik warga yang login.
     *
     * @OA\Get(
     *     path="/api/warga/iuran",
     *     operationId="getDaftarIuranWarga",
     *     tags={"Warga - Iuran"},
     *     summary="Melihat daftar iuran warga",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter status iuran",
     *         @OA\Schema(
     *             type="string",
     *             enum={"Semua", "Belum Bayar", "Sudah Bayar"},
     *             example="Semua"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar iuran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akun tidak terhubung dengan warga"),
     *     @OA\Response(response=422, description="Parameter tidak valid")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in(['Semua', 'Belum Bayar', 'Sudah Bayar']),
            ],
        ]);

        $status = $validated['status'] ?? 'Semua';

        $query = Iuran::with([
            'periode',
            'pembayaran' => function ($q) {
                $q->orderByDesc('created_at');
            },
        ])->where('id_warga', $idWarga);

        if ($status === 'Belum Bayar') {
            $query->where('status', 'Belum Bayar');
        } elseif ($status === 'Sudah Bayar') {
            $query->where('status', 'Sudah Bayar');
        }

        $iuran = $query
            ->orderBy('jatuh_tempo')
            ->orderByDesc('id_iuran')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan iuran berhasil diambil.',
            'data' => $iuran,
        ]);
    }

        /**
     * Mengambil detail iuran milik warga yang login.
     *
     * @OA\Get(
     *     path="/api/warga/iuran/{id_iuran}",
     *     operationId="getDetailIuranWarga",
     *     tags={"Warga - Iuran"},
     *     summary="Melihat detail iuran warga",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_iuran",
     *         in="path",
     *         required=true,
     *         description="ID iuran",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail iuran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akun tidak terhubung dengan warga"),
     *     @OA\Response(response=404, description="Data iuran tidak ditemukan")
     * )
     */
    public function show(Request $request, int $id_iuran): JsonResponse
    {
        $idWarga = $request->user()->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $iuran = Iuran::with([
            'periode',
            'pembayaran' => function ($q) {
                $q->with([
                    'verifikator' => function ($v) {
                        $v->select('id_akun', 'username');
                    },
                ])->orderByDesc('created_at');
            },
        ])
            ->where('id_iuran', $id_iuran)
            ->where('id_warga', $idWarga)
            ->first();

        if (!$iuran) {
            return response()->json([
                'success' => false,
                'message' => 'Data iuran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail iuran berhasil diambil.',
            'data' => $iuran,
        ]);
    }

       /**
     * Mengajukan pembayaran iuran dan mengunggah bukti pembayaran.
     *
     * @OA\Post(
     *     path="/api/warga/iuran/{id_iuran}/pembayaran",
     *     operationId="ajukanPembayaranIuranWarga",
     *     tags={"Warga - Iuran"},
     *     summary="Mengajukan pembayaran iuran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_iuran",
     *         in="path",
     *         required=true,
     *         description="ID iuran yang akan dibayar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"metode", "nominal", "bukti_bayar"},
     *                 @OA\Property(
     *                     property="metode",
     *                     type="string",
     *                     example="QRIS"
     *                 ),
     *                 @OA\Property(
     *                     property="nominal",
     *                     type="number",
     *                     format="float",
     *                     example=50000
     *                 ),
     *                 @OA\Property(
     *                     property="bukti_bayar",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pembayaran berhasil diajukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akun tidak terhubung dengan warga"),
     *     @OA\Response(response=404, description="Data iuran tidak ditemukan"),
     *     @OA\Response(response=409, description="Pengajuan sebelumnya masih menunggu verifikasi"),
     *     @OA\Response(response=422, description="Data pembayaran tidak valid atau nominal tidak sesuai"),
     *     @OA\Response(response=500, description="Gagal mengajukan pembayaran")
     * )
     */
    public function bayar(Request $request, int $id_iuran): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $iuran = Iuran::where('id_iuran', $id_iuran)
            ->where('id_warga', $idWarga)
            ->first();

        if (!$iuran) {
            return response()->json([
                'success' => false,
                'message' => 'Data iuran tidak ditemukan.',
            ], 404);
        }

        if ($iuran->status === 'Sudah Bayar') {
            return response()->json([
                'success' => false,
                'message' => 'Iuran ini sudah lunas.',
            ], 422);
        }

        $menungguVerifikasi = Pembayaran::where(
            'id_iuran',
            $iuran->id_iuran
        )
            ->where('status_verifikasi', 'Menunggu')
            ->exists();

        if ($menungguVerifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan pembayaran sebelumnya masih menunggu verifikasi panitia.',
            ], 409);
        }

        $validated = $request->validate([
            'metode' => ['required', 'string', 'max:50'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'bukti_bayar' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
        ]);

        if (
            round((float) $validated['nominal'], 2) !==
            round((float) $iuran->nominal, 2)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Nominal pembayaran harus sesuai dengan tagihan iuran (Rp ' .
                    number_format($iuran->nominal, 0, ',', '.') . ').',
            ], 422);
        }

        $cloudinary = null;
        $publicId = null;

        try {
            $cloudinary = new Cloudinary();

            $upload = $cloudinary->uploadApi()->upload(
                $request->file('bukti_bayar')->getRealPath(),
                [
                    'folder' => 'pad/bukti-pembayaran',
                    'resource_type' => 'image',
                    'type' => 'authenticated',
                ]
            );

            $publicId = $upload['public_id'];
            $secureUrl = $upload['secure_url'];
            $format = $upload['format'];

            $pembayaran = DB::transaction(function () use (
                $iuran,
                $validated,
                $idWarga,
                $publicId,
                $secureUrl,
                $format
            ) {
                $p = Pembayaran::create([
                    'id_iuran' => $iuran->id_iuran,
                    'tanggal_bayar' => now(),
                    'nominal' => $iuran->nominal,
                    'metode' => $validated['metode'],
                    'bukti_bayar' => $secureUrl,
                    'cloudinary_public_id' => $publicId,
                    'cloudinary_format' => $format,
                    'status_verifikasi' => 'Menunggu',
                    'diverifikasi_oleh' => null,
                    'tanggal_verifikasi' => null,
                ]);

                Notifikasi::create([
                    'id_warga' => $idWarga,
                    'judul' => 'Pengajuan Pembayaran Iuran',
                    'pesan' => 'Pembayaran iuran sebesar Rp ' .
                        number_format($iuran->nominal, 0, ',', '.') .
                        ' berhasil diajukan dan sedang menunggu verifikasi panitia.',
                    'tipe' => 'Iuran',
                    'status_baca' => false,
                ]);

                return $p;
            });

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran iuran berhasil diajukan dan sedang menunggu verifikasi panitia.',
                'data' => $pembayaran,
            ], 201);

        } catch (Throwable $e) {
            if ($cloudinary && $publicId) {
                try {
                    $cloudinary->uploadApi()->destroy($publicId, [
                        'resource_type' => 'image',
                        'type' => 'authenticated',
                    ]);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan pembayaran. Silakan coba kembali.',
            ], 500);
        }
    }

        /**
     * Mengakses bukti pembayaran dengan pemeriksaan hak akses.
     *
     * @OA\Get(
     *     path="/api/pembayaran/{id_pembayaran}/bukti",
     *     operationId="getBuktiPembayaranIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Mengakses bukti pembayaran iuran",
     *     description="Admin dan Panitia dapat mengakses seluruh bukti pembayaran. Warga hanya dapat mengakses bukti pembayaran miliknya sendiri.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_pembayaran",
     *         in="path",
     *         required=true,
     *         description="ID pembayaran",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bukti pembayaran berhasil diakses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="url", type="string", example="https://example.com/bukti.jpg"),
     *                 @OA\Property(property="expires_in", type="integer", nullable=true, example=300)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=404, description="Data atau bukti pembayaran tidak ditemukan"),
     *     @OA\Response(response=500, description="Gagal mengambil bukti pembayaran")
     * )
     */
    public function buktiBayar(
        Request $request,
        int $id_pembayaran
    ): JsonResponse {
        $akun = $request->user();

        $pembayaran = Pembayaran::with('iuran')
            ->find($id_pembayaran);

        if (!$pembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pembayaran tidak ditemukan.',
            ], 404);
        }

        // Memuat relasi role dari database.
        $akun->load('role');

        $namaRole = strtolower(
            $akun->role?->nama_role ?? ''
        );

        // Admin dan Panitia dapat mengakses semua bukti.
        $bolehAkses = in_array(
            $namaRole,
            ['admin', 'panitia'],
            true
        );

        // Warga hanya dapat mengakses bukti miliknya sendiri.
        if (
            $namaRole === 'warga' &&
            $pembayaran->iuran &&
            (int) $pembayaran->iuran->id_warga ===
            (int) $akun->id_warga
        ) {
            $bolehAkses = true;
        }

        if (!$bolehAkses) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak memiliki akses ke bukti pembayaran ini.',
            ], 403);
        }

        // Mendukung bukti pembayaran lama.
        if (!$pembayaran->cloudinary_public_id) {
            if (!$pembayaran->bukti_bayar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bukti pembayaran tidak tersedia.',
                ], 404);
            }

            $url = filter_var(
                $pembayaran->bukti_bayar,
                FILTER_VALIDATE_URL
            )
                ? $pembayaran->bukti_bayar
                : asset('storage/' . $pembayaran->bukti_bayar);

            return response()->json([
                'success' => true,
                'message' => 'Bukti pembayaran berhasil ditemukan.',
                'data' => [
                    'url' => $url,
                    'expires_in' => null,
                ],
            ]);
        }

        if (!$pembayaran->cloudinary_format) {
            return response()->json([
                'success' => false,
                'message' => 'Format bukti pembayaran tidak tersedia.',
            ], 404);
        }

        try {
            $cloudinary = new Cloudinary();

            $url = $cloudinary->uploadApi()->privateDownloadUrl(
                $pembayaran->cloudinary_public_id,
                $pembayaran->cloudinary_format,
                [
                    'resource_type' => 'image',
                    'type' => 'authenticated',
                    'expires_at' => time() + 300,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Bukti pembayaran berhasil diakses.',
                'data' => [
                    'url' => $url,
                    'expires_in' => 300,
                ],
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil bukti pembayaran.',
            ], 500);
        }
    }

        /**
     * Mengambil transparansi iuran seluruh warga.
     *
     * @OA\Get(
     *     path="/api/warga/iuran/transparansi",
     *     operationId="getTransparansiIuran",
     *     tags={"Warga - Iuran"},
     *     summary="Melihat transparansi pembayaran iuran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter status pembayaran",
     *         @OA\Schema(
     *             type="string",
     *             enum={"Semua", "Lunas", "Belum Lunas"},
     *             example="Semua"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Pencarian berdasarkan nama kepala keluarga",
     *         @OA\Schema(type="string", example="Budi")
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
     *         description="Transparansi iuran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=422, description="Parameter tidak valid")
     * )
     */
    public function transparansi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable', 
                Rule::in(['Semua', 'Lunas', 'Belum Lunas']),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $validated['status'] ?? 'Semua';
        $search = $validated['search'] ?? null;
        $perPage = $validated['per_page'] ?? 10;

        $query = Iuran::with([
            'warga:id_warga,nama',
            'pembayaran' => function ($q) {
                $q->orderByDesc('created_at');
            },
        ]);

        if ($status === 'Lunas') {
            $query->where('status', 'Sudah Bayar');
        } elseif ($status === 'Belum Lunas') {
            $query->where('status', 'Belum Bayar');
        }

        if ($search) {
            $query->whereHas('warga', function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%');
            });
        }

        $data = $query
            ->orderByDesc('id_iuran')
            ->paginate($perPage);

        $data->getCollection()->transform(function ($iuran) {
            $pembayaranTerakhir = $iuran->pembayaran->first();

            if ($iuran->status === 'Sudah Bayar') {
                $statusTampilan = 'Lunas';
            } elseif (
                $pembayaranTerakhir &&
                $pembayaranTerakhir->status_verifikasi === 'Menunggu'
            ) {
                $statusTampilan = 'Menunggu Konfirmasi';
            } else {
                $statusTampilan = 'Belum Lunas';
            }

            return [
                'id_iuran' => $iuran->id_iuran,
                'nama_kepala_keluarga' => $iuran->warga?->nama,
                'nominal' => $iuran->nominal,
                'status' => $statusTampilan,
                'waktu_pembayaran' => $pembayaranTerakhir
                    ? $pembayaranTerakhir->tanggal_bayar
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Transparansi iuran berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Mengambil daftar iuran untuk kebutuhan manajemen.
     *
     * @OA\Get(
     *     path="/api/manajemen/iuran",
     *     operationId="getDaftarIuranManajemen",
     *     tags={"Manajemen - Iuran"},
     *     summary="Melihat daftar iuran seluruh warga",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter status iuran",
     *         @OA\Schema(
     *             type="string",
     *             enum={"Semua", "Menunggu", "Lunas", "Belum Lunas"},
     *             example="Semua"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Pencarian berdasarkan nama kepala keluarga",
     *         @OA\Schema(type="string", example="Budi")
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
     *         description="Data manajemen iuran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=422, description="Parameter tidak valid")
     * )
     */
    
public function daftarIuranManajemen(Request $request): JsonResponse
{
    $validated = $request->validate([
        'status' => [
            'nullable',
            Rule::in(['Semua', 'Menunggu', 'Lunas', 'Belum Lunas']),
        ],
        'search' => ['nullable', 'string', 'max:100'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
    ]);

    $status = $validated['status'] ?? 'Semua';
    $search = $validated['search'] ?? null;
    $perPage = $validated['per_page'] ?? 10;

    $query = Iuran::with([
        'warga',
        'pembayaran' => function ($query) {
            $query->orderByDesc('tanggal_bayar')
                  ->orderByDesc('id_pembayaran');
        },
    ]);

    // Filter berdasarkan status sebelum pagination.
    if ($status === 'Menunggu') {
        $query->whereHas('pembayaran', function ($query) {
            $query->where('status_verifikasi', 'Menunggu');
        });
    } elseif ($status === 'Lunas') {
        $query->where('status', 'Sudah Bayar');
    } elseif ($status === 'Belum Lunas') {
        $query->where('status', 'Belum Bayar')
              ->whereDoesntHave('pembayaran', function ($query) {
                  $query->where('status_verifikasi', 'Menunggu');
              });
    }

    // Pencarian berdasarkan nama kepala keluarga.
    if (!empty($search)) {
        $query->whereHas('warga', function ($query) use ($search) {
            $query->where(
                'nama',
                'like',
                '%' . $search . '%'
            );
        });
    }

    // Pagination dilakukan setelah seluruh filter diterapkan.
    $data = $query->orderByDesc('id_iuran')
        ->paginate($perPage);

    // Rapikan format data untuk frontend.
    $data->through(function ($iuran) {
        $pembayaran = $iuran->pembayaran->first();

        if ($iuran->status === 'Sudah Bayar') {
            $statusTampilan = 'Lunas';
        } elseif (
            $pembayaran &&
            $pembayaran->status_verifikasi === 'Menunggu'
        ) {
            $statusTampilan = 'Menunggu Konfirmasi';
        } else {
            $statusTampilan = 'Belum Lunas';
        }

        return [
            'id_iuran' => $iuran->id_iuran,
            'id_pembayaran' => $pembayaran?->id_pembayaran,
            'nama_kepala_keluarga' => $iuran->warga?->nama,
            'nominal' => $iuran->nominal,
            'tanggal_bayar' => $pembayaran?->tanggal_bayar,
            'bukti_bayar_url' => $pembayaran?->bukti_bayar_url,
            'status' => $statusTampilan,
            'status_verifikasi' =>
                $pembayaran?->status_verifikasi,
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Data manajemen iuran berhasil diambil.',
        'data' => $data,
        ]);
    }


       /**
     * Verifikasi pembayaran oleh Admin atau Panitia.
     *
     * @OA\Patch(
     *     path="/api/manajemen/pembayaran/{id_pembayaran}/verifikasi",
     *     operationId="verifikasiPembayaranIuran",
     *     tags={"Manajemen - Iuran"},
     *     summary="Menerima atau menolak pembayaran iuran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_pembayaran",
     *         in="path",
     *         required=true,
     *         description="ID pembayaran yang akan diverifikasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"keputusan"},
     *             @OA\Property(
     *                 property="keputusan",
     *                 type="string",
     *                 enum={"Diterima", "Ditolak"},
     *                 example="Diterima"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pembayaran berhasil diverifikasi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=404, description="Data pembayaran atau iuran tidak ditemukan"),
     *     @OA\Response(response=422, description="Keputusan tidak valid atau pembayaran sudah diverifikasi")
     * )
     */
    
    public function verifikasiPembayaran(
        Request $request,
        int $id_pembayaran
    ): JsonResponse {
        $validated = $request->validate([
            'keputusan' => [
                'required',
                Rule::in(['Diterima', 'Ditolak']),
            ],
        ]);

        $keputusan = $validated['keputusan'];
        $akun = $request->user();

        if (!$akun) {
            return response()->json([
                'success' => false,
                'message' => 'Akun belum terautentikasi. Silakan login kembali.',
            ], 401);
        }

        $idAkun = $akun->id_akun;

        return DB::transaction(function () use (
            $id_pembayaran,
            $keputusan,
            $idAkun
        ) {
            // Ambil pembayaran dan kunci selama proses verifikasi.
            $pembayaran = Pembayaran::with('iuran')
                ->where('id_pembayaran', $id_pembayaran)
                ->lockForUpdate()
                ->first();

            if (!$pembayaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pembayaran tidak ditemukan.',
                ], 404);
            }

            // Pembayaran hanya boleh diverifikasi satu kali.
            if ($pembayaran->status_verifikasi !== 'Menunggu') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembayaran ini sudah diverifikasi sebelumnya.',
                    'data' => [
                        'status_verifikasi' => $pembayaran->status_verifikasi,
                    ],
                ], 422);
            }

            $iuran = Iuran::where(
                'id_iuran',
                $pembayaran->id_iuran
            )->lockForUpdate()->first();

            if (!$iuran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data iuran tidak ditemukan.',
                ], 404);
            }

            // Perbarui status pembayaran.
            $pembayaran->update([
                'status_verifikasi' => $keputusan,
                'diverifikasi_oleh' => $idAkun,
                'tanggal_verifikasi' => now(),
            ]);

            // Perbarui status iuran.
            if ($keputusan === 'Diterima') {
                $iuran->update([
                    'status' => 'Sudah Bayar',
                ]);

                $judul = 'Pembayaran Iuran Diterima';
                $pesan = 'Pembayaran iuran sebesar Rp ' .
                    number_format($pembayaran->nominal, 0, ',', '.') .
                    ' telah diterima dan dikonfirmasi oleh panitia.';
            } else {
                $iuran->update([
                    'status' => 'Belum Bayar',
                ]);

                $judul = 'Pembayaran Iuran Ditolak';
                $pesan = 'Pembayaran iuran sebesar Rp ' .
                    number_format($pembayaran->nominal, 0, ',', '.') .
                    ' ditolak. Silakan periksa kembali bukti pembayaran dan ajukan ulang.';
            }

            // Kirim notifikasi kepada warga.
            Notifikasi::create([
                'id_warga' => $iuran->id_warga,
                'judul' => $judul,
                'pesan' => $pesan,
                'tipe' => 'Iuran',
                'status_baca' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => $keputusan === 'Diterima'
                    ? 'Pembayaran berhasil diterima.'
                    : 'Pembayaran berhasil ditolak.',
                'data' => [
                    'id_pembayaran' => $pembayaran->id_pembayaran,
                    'id_iuran' => $iuran->id_iuran,
                    'status_verifikasi' => $pembayaran->status_verifikasi,
                    'status_iuran' => $iuran->status,
                    'diverifikasi_oleh' => $idAkun,
                    'tanggal_verifikasi' => $pembayaran->tanggal_verifikasi,
                ],
            ]);
        });
    }


}


