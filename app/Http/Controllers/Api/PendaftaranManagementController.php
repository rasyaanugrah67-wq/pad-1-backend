<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnggotaPendaftaran;
use App\Models\Lomba;
use App\Models\Pendaftaran;
use App\Models\TimLomba;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * PB-04: Pengelolaan pendaftaran oleh Admin dan Panitia.
 */
class PendaftaranManagementController extends Controller
{
    /**
     * Menampilkan seluruh pendaftaran.
     *
     * @OA\Get(
     *     path="/api/admin/pendaftaran",
     *     operationId="adminDaftarPendaftaran",
     *     tags={"Admin - Pendaftaran"},
     *     summary="Melihat seluruh pendaftaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"Menunggu","Diterima","Ditolak"})),
     *     @OA\Parameter(name="id_lomba", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Daftar pendaftaran berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     *
     * @OA\Get(
     *     path="/api/panitia/pendaftaran",
     *     operationId="panitiaDaftarPendaftaran",
     *     tags={"Panitia - Pendaftaran"},
     *     summary="Melihat seluruh pendaftaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"Menunggu","Diterima","Ditolak"})),
     *     @OA\Parameter(name="id_lomba", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Daftar pendaftaran berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pendaftaran::with([
            'warga',
            'lomba.kategori',
            'lomba.jadwal',
            'anggota',
            'tim.pendaftaran.warga',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_lomba')) {
            $query->where('id_lomba', $request->id_lomba);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->whereHas('warga', function ($warga) use ($search) {
                    $warga->where('nama', 'like', "%{$search}%");
                })->orWhereHas('lomba', function ($lomba) use ($search) {
                    $lomba->where('nama_lomba', 'like', "%{$search}%");
                });
            });
        }

        $pendaftaran = $query
            ->orderByDesc('tanggal_daftar')
            ->orderByDesc('id_pendaftaran')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pendaftaran berhasil diambil.',
            'data' => $pendaftaran,
        ]);
    }

    /**
     * Melihat detail pendaftaran.
     *
     * @OA\Get(
     *     path="/api/admin/pendaftaran/{id}",
     *     operationId="adminDetailPendaftaran",
     *     tags={"Admin - Pendaftaran"},
     *     summary="Melihat detail pendaftaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detail pendaftaran berhasil diambil"),
     *     @OA\Response(response=404, description="Pendaftaran tidak ditemukan")
     * )
     *
     * @OA\Get(
     *     path="/api/panitia/pendaftaran/{id}",
     *     operationId="panitiaDetailPendaftaran",
     *     tags={"Panitia - Pendaftaran"},
     *     summary="Melihat detail pendaftaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detail pendaftaran berhasil diambil"),
     *     @OA\Response(response=404, description="Pendaftaran tidak ditemukan")
     * )
     */
    public function show($id): JsonResponse
    {
        $pendaftaran = Pendaftaran::with([
            'warga',
            'lomba.kategori',
            'lomba.jadwal',
            'anggota',
            'tim.pendaftaran.warga',
        ])->where('id_pendaftaran', $id)->first();

        if (!$pendaftaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendaftaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pendaftaran berhasil diambil.',
            'data' => $pendaftaran,
        ]);
    }

    /**
     * Mengubah status pendaftaran.
     *
     * @OA\Patch(
     *     path="/api/admin/pendaftaran/{id}/status",
     *     operationId="adminUbahStatusPendaftaran",
     *     tags={"Admin - Pendaftaran"},
     *     summary="Mengubah status pendaftaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status", type="string", enum={"Menunggu","Diterima","Ditolak"}, example="Diterima"),
     *         @OA\Property(property="catatan", type="string", nullable=true, example="Persyaratan belum lengkap")
     *     )),
     *     @OA\Response(response=200, description="Status pendaftaran berhasil diperbarui"),
     *     @OA\Response(response=404, description="Pendaftaran tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     *
     * @OA\Patch(
     *     path="/api/panitia/pendaftaran/{id}/status",
     *     operationId="panitiaUbahStatusPendaftaran",
     *     tags={"Panitia - Pendaftaran"},
     *     summary="Mengubah status pendaftaran",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status", type="string", enum={"Menunggu","Diterima","Ditolak"}, example="Diterima"),
     *         @OA\Property(property="catatan", type="string", nullable=true, example="Persyaratan belum lengkap")
     *     )),
     *     @OA\Response(response=200, description="Status pendaftaran berhasil diperbarui"),
     *     @OA\Response(response=404, description="Pendaftaran tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:Menunggu,Diterima,Ditolak'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pendaftaran = Pendaftaran::where(
            'id_pendaftaran',
            $id
        )->first();

        if (!$pendaftaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendaftaran tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($request, $pendaftaran) {
            $pendaftaran->status = $request->status;

            if ($request->has('catatan')) {
                $pendaftaran->catatan = $request->catatan;
            }

            $pendaftaran->save();
        });

        $pendaftaran->load([
            'warga',
            'lomba',
            'anggota',
            'tim.pendaftaran.warga',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status pendaftaran berhasil diperbarui.',
            'data' => $pendaftaran,
        ]);
    }

    /**
     * Melihat pendaftar pada lomba tertentu.
     *
     * @OA\Get(
     *     path="/api/admin/lomba/{id}/pendaftaran",
     *     operationId="adminPendaftarPerLomba",
     *     tags={"Admin - Pendaftaran"},
     *     summary="Melihat daftar pendaftar pada lomba tertentu",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"Menunggu","Diterima","Ditolak"})),
     *     @OA\Response(response=200, description="Daftar pendaftar berhasil diambil"),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan")
     * )
     *
     * @OA\Get(
     *     path="/api/panitia/lomba/{id}/pendaftaran",
     *     operationId="panitiaPendaftarPerLomba",
     *     tags={"Panitia - Pendaftaran"},
     *     summary="Melihat daftar pendaftar pada lomba tertentu",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"Menunggu","Diterima","Ditolak"})),
     *     @OA\Response(response=200, description="Daftar pendaftar berhasil diambil"),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan")
     * )
     */
    public function byLomba(Request $request, $id): JsonResponse
    {
        $lomba = Lomba::where('id_lomba', $id)->first();

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Data lomba tidak ditemukan.',
            ], 404);
        }

        $query = Pendaftaran::with([
            'warga',
            'anggota',
            'tim.pendaftaran.warga',
        ])->where('id_lomba', $id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pendaftaran = $query
            ->orderByDesc('tanggal_daftar')
            ->orderByDesc('id_pendaftaran')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pendaftar lomba berhasil diambil.',
            'lomba' => $lomba,
            'data' => $pendaftaran,
        ]);
    }

    /**
     * Melihat kuota lomba.
     *
     * @OA\Get(
     *     path="/api/admin/lomba/{id}/kuota",
     *     operationId="adminKuotaLomba",
     *     tags={"Admin - Pendaftaran"},
     *     summary="Melihat penggunaan kuota lomba",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Informasi kuota berhasil diambil"),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan")
     * )
     *
     * @OA\Get(
     *     path="/api/panitia/lomba/{id}/kuota",
     *     operationId="panitiaKuotaLomba",
     *     tags={"Panitia - Pendaftaran"},
     *     summary="Melihat penggunaan kuota lomba",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Informasi kuota berhasil diambil"),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan")
     * )
     */
    public function kuota($id): JsonResponse
    {
        $lomba = Lomba::where('id_lomba', $id)->first();

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Data lomba tidak ditemukan.',
            ], 404);
        }

        $pendaftaranAktif = Pendaftaran::where('id_lomba', $id)
            ->whereIn('status', ['Menunggu', 'Diterima']);

        $jumlahPendaftar = (clone $pendaftaranAktif)->count();

        // Kuota lomba individu dihitung berdasarkan jumlah pendaftaran.
        if (!$lomba->isBeregu()) {
            $kapasitas = $lomba->kapasitas_peserta;

            $sisa = $kapasitas !== null
                ? max(0, $kapasitas - $jumlahPendaftar)
                : null;

            return response()->json([
                'success' => true,
                'message' => 'Informasi kuota lomba berhasil diambil.',
                'data' => [
                    'id_lomba' => $lomba->id_lomba,
                    'nama_lomba' => $lomba->nama_lomba,
                    'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
                    'jumlah_peserta' => $jumlahPendaftar,
                    'kapasitas_peserta' => $kapasitas,
                    'sisa_kapasitas' => $sisa,
                    'kuota_penuh' => $kapasitas !== null
                        ? $jumlahPendaftar >= $kapasitas
                        : false,
                    'pendaftaran_dibuka' => $lomba->status === 'Dibuka'
                        && ($kapasitas === null || $jumlahPendaftar < $kapasitas),
                ],
            ]);
        }

        // Kuota lomba beregu dihitung berdasarkan jumlah tim.
        $kapasitasTim = (int) $lomba->kapasitas_peserta;
        $batasAnggota = (int) $lomba->batas_anggota_tim;

        $jumlahTimLama = (clone $pendaftaranAktif)
            ->whereNotNull('nama_tim')
            ->whereNull('id_tim')
            ->count();

        $jumlahTimOtomatis = TimLomba::where(
            'id_lomba',
            $id
        )->count();

        $jumlahMenungguTim = (clone $pendaftaranAktif)
            ->whereNull('id_tim')
            ->whereNull('nama_tim')
            ->count();

        $jumlahTimTerpakai = $jumlahTimLama
            + $jumlahTimOtomatis
            + ($batasAnggota > 0
                ? (int) ceil($jumlahMenungguTim / $batasAnggota)
                : 0);

        $anggotaTimLama = AnggotaPendaftaran::whereHas(
            'pendaftaran',
            function ($query) use ($id) {
                $query->where('id_lomba', $id)
                    ->whereIn('status', ['Menunggu', 'Diterima'])
                    ->whereNotNull('nama_tim')
                    ->whereNull('id_tim');
            }
        )->count();

        $totalPeserta = $jumlahPendaftar + $anggotaTimLama;

        $sisaKapasitas = ($kapasitasTim > 0 && $batasAnggota > 0)
            ? max(
                0,
                (($kapasitasTim - $jumlahTimLama - $jumlahTimOtomatis)
                    * $batasAnggota) - $jumlahMenungguTim
            )
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Informasi kuota lomba berhasil diambil.',
            'data' => [
                'id_lomba' => $lomba->id_lomba,
                'nama_lomba' => $lomba->nama_lomba,
                'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
                'jumlah_peserta' => $totalPeserta,
                'jumlah_tim' => $jumlahTimTerpakai,
                'kapasitas_tim' => $kapasitasTim,
                'batas_anggota_tim' => $batasAnggota,
                'peserta_menunggu_tim' => $jumlahMenungguTim,
                'sisa_kapasitas' => $sisaKapasitas,
                'kuota_penuh' => $sisaKapasitas <= 0,
                'pendaftaran_dibuka' => $lomba->status === 'Dibuka'
                    && $sisaKapasitas > 0,
            ],
        ]);
    }
}
