<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Iuran;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IuranController extends Controller
{


    /**
     * PB-05: Mengambil daftar iuran milik warga yang sedang login.
     *
     * @OA\Get(
     *     path="/api/warga/iuran",
     *     operationId="getDaftarIuranWarga",
     *     tags={"Warga - Iuran & Pembayaran"},
     *     summary="Melihat daftar iuran warga",
     *     description="Mengambil daftar tagihan iuran milik warga login. Mendukung filter status iuran (Semua, Belum Bayar, Sudah Bayar).",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter status pembayaran iuran",
     *         @OA\Schema(type="string", enum={"Semua","Belum Bayar","Sudah Bayar"}),
     *         example="Belum Bayar"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar iuran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar tagihan iuran berhasil diambil."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Bukan warga)"),
     *     @OA\Response(response=422, description="Filter status tidak valid")
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
        ])
            ->where('id_warga', $idWarga);

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
     * PB-05: Mengambil detail satu iuran milik warga yang sedang login.
     *
     * @OA\Get(
     *     path="/api/warga/iuran/{id_iuran}",
     *     operationId="getDetailIuranWarga",
     *     tags={"Warga - Iuran & Pembayaran"},
     *     summary="Melihat detail iuran warga",
     *     description="Menampilkan detail satu tagihan iuran dan riwayat pengajuan pembayarannya. Hanya dapat diakses oleh pemilik iuran.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_iuran",
     *         in="path",
     *         required=true,
     *         description="ID tagihan iuran",
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail iuran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail iuran berhasil diambil."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Bukan warga)"),
     *     @OA\Response(response=404, description="Iuran tidak ditemukan atau bukan milik warga")
     * )
     */
    public function show(Request $request, int $id_iuran): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $iuran = Iuran::with([
            'periode',
            'pembayaran' => function ($q) {
                $q->with(['verifikator' => function ($v) {
                    $v->select('id_akun', 'username');
                }])->orderByDesc('created_at');
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
     * PB-05: Mengajukan pembayaran untuk suatu tagihan iuran.
     *
     * @OA\Post(
     *     path="/api/warga/iuran/{id_iuran}/pembayaran",
     *     operationId="ajukanPembayaranIuranWarga",
     *     tags={"Warga - Iuran & Pembayaran"},
     *     summary="Mengajukan pembayaran iuran",
     *     description="Mengirim nominal, metode pembayaran, dan berkas foto bukti bayar. Pengajuan pembayaran berstatus awal Menunggu dan tidak langsung mengubah status iuran menjadi lunas.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_iuran",
     *         in="path",
     *         required=true,
     *         description="ID tagihan iuran yang ingin dibayar",
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data pengajuan pembayaran iuran dan berkas bukti",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"metode","nominal","bukti_bayar"},
     *                 @OA\Property(property="metode", type="string", maxLength=50, example="Transfer Bank BCA", description="Metode pembayaran"),
     *                 @OA\Property(property="nominal", type="number", format="float", example=50000, description="Nominal yang dibayarkan (harus sama persis dengan tagihan iuran)"),
     *                 @OA\Property(property="bukti_bayar", type="string", format="binary", description="Berkas gambar bukti transfer (jpeg, png, jpg, webp maks 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pembayaran berhasil diajukan dan sedang menunggu verifikasi panitia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pembayaran iuran berhasil diajukan dan sedang menunggu verifikasi panitia."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Bukan warga)"),
     *     @OA\Response(response=404, description="Data iuran tidak ditemukan"),
     *     @OA\Response(response=409, description="Pengajuan pembayaran sebelumnya masih menunggu verifikasi panitia"),
     *     @OA\Response(response=422, description="Unprocessable Entity - Nominal tidak cocok, iuran sudah lunas, atau berkas tidak valid")
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

        // Cari iuran dan pastikan milik warga yang login
        $iuran = Iuran::where('id_iuran', $id_iuran)
            ->where('id_warga', $idWarga)
            ->first();

        if (!$iuran) {
            return response()->json([
                'success' => false,
                'message' => 'Data iuran tidak ditemukan.',
            ], 404);
        }

        // Cek jika status iuran sudah lunas
        if ($iuran->status === 'Sudah Bayar') {
            return response()->json([
                'success' => false,
                'message' => 'Iuran ini sudah lunas.',
            ], 422);
        }

        // Cek apakah ada pengajuan pembayaran sebelumnya yang masih menunggu verifikasi
        $menungguVerifikasi = Pembayaran::where('id_iuran', $iuran->id_iuran)
            ->where('status_verifikasi', 'Menunggu')
            ->exists();

        if ($menungguVerifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan pembayaran sebelumnya masih menunggu verifikasi panitia.',
            ], 409);
        }

        $request->validate([
            'metode' => ['required', 'string', 'max:50'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'bukti_bayar' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        // Pastikan nominal pembayaran sama dengan tagihan iuran
        if (round((float) $request->nominal, 2) !== round((float) $iuran->nominal, 2)) {
            return response()->json([
                'success' => false,
                'message' => 'Nominal pembayaran harus sesuai dengan tagihan iuran (Rp ' . number_format($iuran->nominal, 0, ',', '.') . ').',
            ], 422);
        }

        // Simpan file bukti bayar ke disk 'public'
        $path = $request->file('bukti_bayar')->store('bukti_bayar', 'public');

        $pembayaran = DB::transaction(function () use ($iuran, $request, $path, $idWarga) {
            $p = Pembayaran::create([
                'id_iuran' => $iuran->id_iuran,
                'tanggal_bayar' => now(),
                'nominal' => $iuran->nominal,
                'metode' => $request->metode,
                'bukti_bayar' => $path,
                'status_verifikasi' => 'Menunggu',
                'diverifikasi_oleh' => null,
                'tanggal_verifikasi' => null,
            ]);

            Notifikasi::create([
                'id_warga' => $idWarga,
                'judul' => 'Pengajuan Pembayaran Iuran',
                'pesan' => 'Pembayaran iuran sebesar Rp ' . number_format($iuran->nominal, 0, ',', '.') . ' berhasil diajukan dan sedang menunggu verifikasi panitia.',
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
    }
}

