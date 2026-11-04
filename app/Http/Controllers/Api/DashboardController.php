<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Dashboard Admin.
     *
     * @OA\Get(
     *     path="/api/admin/dashboard",
     *     operationId="getDashboardAdmin",
     *     tags={"Dashboard Admin"},
     *     summary="Mengakses dashboard Admin",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard Admin berhasil diakses",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Dashboard Admin"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function admin(): JsonResponse
    {
        return response()->json([
            'message' => 'Dashboard Admin',
        ]);
    }

    /**
     * Dashboard Panitia.
     *
     * @OA\Get(
     *     path="/api/panitia/dashboard",
     *     operationId="getDashboardPanitia",
     *     tags={"Dashboard Panitia"},
     *     summary="Mengakses dashboard Panitia",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard Panitia berhasil diakses",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Dashboard Panitia"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak")
     * )
     */
    public function panitia(): JsonResponse
    {
        return response()->json([
            'message' => 'Dashboard Panitia',
        ]);
    }

    /**
     * Dashboard Warga.
     *
     * @OA\Get(
     *     path="/api/warga/dashboard",
     *     operationId="getDashboardWarga",
     *     tags={"Dashboard Warga"},
     *     summary="Mengakses dashboard Warga",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard Warga berhasil diakses",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Dashboard Warga"
     *             )
     *         )
     *     ),
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
}
