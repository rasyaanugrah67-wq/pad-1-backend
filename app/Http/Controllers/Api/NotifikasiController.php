<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{


    /**
     *  PB-06: Mengambil seluruh notifikasi milik warga yang login.
     * @OA\Get(
     *     path="/api/warga/notifikasi",
     *     operationId="getNotifikasiWarga",
     *     tags={"Warga - Notifikasi"},
     *     summary="Melihat daftar notifikasi warga",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status_baca",
     *         in="query",
     *         required=false,
     *         description="Filter status baca: true atau false",
     *         @OA\Schema(type="boolean"),
     *         example=false
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar notifikasi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akun tidak terhubung dengan warga")
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

        $query = Notifikasi::where('id_warga', $idWarga)
            ->orderByDesc('created_at')
            ->orderByDesc('id_notifikasi');

        if ($request->has('status_baca')) {
            $statusBaca = filter_var($request->query('status_baca'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($statusBaca !== null) {
                $query->where('status_baca', $statusBaca);
            }
        }

        $notifikasi = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar notifikasi berhasil diambil.',
            'data' => $notifikasi,
        ]);
    }

    
    /**
     *  PB-06: Mengambil jumlah notifikasi yang belum dibaca (unread count).
     * @OA\Get(
     *     path="/api/warga/notifikasi/unread-count",
     *     operationId="getJumlahNotifikasiBelumDibaca",
     *     tags={"Warga - Notifikasi"},
     *     summary="Mengambil jumlah notifikasi belum dibaca",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Jumlah notifikasi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="unread_count", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login")
     * )
     */

    
    public function unreadCount(Request $request): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $unreadCount = Notifikasi::where('id_warga', $idWarga)
            ->where('status_baca', false)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Jumlah notifikasi belum dibaca berhasil diambil.',
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    
    /**
      * PB-06: Menandai satu notifikasi telah dibaca (idempotent)
     * @OA\Patch(
     *     path="/api/warga/notifikasi/{id_notifikasi}/read",
     *     operationId="tandaiNotifikasiDibaca",
     *     tags={"Warga - Notifikasi"},
     *     summary="Menandai satu notifikasi sebagai sudah dibaca",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_notifikasi",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifikasi berhasil ditandai sudah dibaca",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=404, description="Notifikasi tidak ditemukan")
     * )
     */

    
    public function markAsRead(Request $request, int $id_notifikasi): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $notifikasi = Notifikasi::where('id_notifikasi', $id_notifikasi)
            ->where('id_warga', $idWarga)
            ->first();

        if (!$notifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        if (!$notifikasi->status_baca) {
            $notifikasi->update([
                'status_baca' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil ditandai sudah dibaca.',
            'data' => $notifikasi,
        ]);
    }

    
    /**
     *  PB-06: Menandai semua notifikasi milik warga sebagai sudah dibaca.
     * @OA\Patch(
     *     path="/api/warga/notifikasi/read-all",
     *     operationId="tandaiSemuaNotifikasiDibaca",
     *     tags={"Warga - Notifikasi"},
     *     summary="Menandai semua notifikasi sebagai sudah dibaca",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Semua notifikasi berhasil ditandai sudah dibaca",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="updated_count", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login")
     * )
     */


    public function markAllAsRead(Request $request): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $affected = Notifikasi::where('id_warga', $idWarga)
            ->where('status_baca', false)
            ->update([
                'status_baca' => true,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi berhasil ditandai sudah dibaca.',
            'data' => [
                'updated_count' => $affected,
            ],
        ]);
    }
}

