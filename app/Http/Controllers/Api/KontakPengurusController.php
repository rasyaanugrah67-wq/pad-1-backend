<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KontakPengurusController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/warga/kontak-pengurus",
     *     tags={"Warga - Profil"},
     *     summary="Mengambil kontak Ketua RT dan RW warga yang login",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Kontak pengurus berhasil ditampilkan"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=404, description="Kontak pengurus belum tersedia")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $warga = $request->user()->loadMissing('warga.rt.rw')->warga;
        $rt = $warga?->rt;
        $rw = $rt?->rw;

        if (!$warga || !$rt || !$rw || !$rt->nama_ketua || !$rt->nomor_whatsapp || !$rw->nama_ketua || !$rw->nomor_whatsapp) {
            return response()->json([
                'success' => false,
                'message' => 'Kontak pengurus belum tersedia',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kontak Ketua RT dan RW berhasil ditampilkan.',
            'data' => [
                'rt' => [
                    'id_rt' => $rt->id_rt,
                    'nomor_rt' => $rt->nomor_rt,
                    'nama_ketua' => $rt->nama_ketua,
                    'nomor_whatsapp' => $rt->nomor_whatsapp,
                ],
                'rw' => [
                    'id_rw' => $rw->id_rw,
                    'nomor_rw' => $rw->nomor_rw,
                    'nama_ketua' => $rw->nama_ketua,
                    'nomor_whatsapp' => $rw->nomor_whatsapp,
                ],
            ],
        ]);
    }
}
