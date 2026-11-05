<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rw;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class RwController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/rw",
     *     tags={"Admin - Data RW"},
     *     summary="Menampilkan daftar RW",
     *     @OA\Response(response=200, description="Daftar RW berhasil ditampilkan")
     * )
     */
    public function index(): JsonResponse
    {
        $rw = Rw::withCount('rt')
            ->with('rt')
            ->orderBy('nomor_rw')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar RW berhasil ditampilkan.',
            'data' => $rw,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/rw",
     *     tags={"Admin - Data RW"},
     *     summary="Menambahkan data RW",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nomor_rw"},
     *             @OA\Property(property="nomor_rw", type="string", example="001"),
     *             @OA\Property(property="nama_rw", type="string", example="RW 001")
     *         )
     *     ),
     *     @OA\Response(response=201, description="RW berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_rw' => [
                'required',
                'string',
                'max:10',
                'unique:rw,nomor_rw',
            ],
            'nama_rw' => ['nullable', 'string', 'max:100'],
        ]);

        $rw = Rw::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data RW berhasil ditambahkan.',
            'data' => $rw,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/rw/{rw}",
     *     tags={"Admin - Data RW"},
     *     summary="Menampilkan detail RW",
     *     @OA\Parameter(name="rw", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detail RW berhasil ditampilkan"),
     *     @OA\Response(response=404, description="RW tidak ditemukan")
     * )
     */
    public function show(Rw $rw): JsonResponse
    {
        $rw->load('rt')
            ->loadCount('rt');

        return response()->json([
            'success' => true,
            'message' => 'Detail RW berhasil ditampilkan.',
            'data' => $rw,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/rw/{rw}",
     *     tags={"Admin - Data RW"},
     *     summary="Mengubah data RW",
     *     @OA\Parameter(name="rw", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nomor_rw", type="string", example="002"),
     *             @OA\Property(property="nama_rw", type="string", example="RW 002")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Data RW berhasil diubah"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function update(Request $request, Rw $rw): JsonResponse
    {
        $validated = $request->validate([
            'nomor_rw' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('rw', 'nomor_rw')->ignore($rw->id_rw, 'id_rw'),
            ],
            'nama_rw' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $rw->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data RW berhasil diperbarui.',
            'data' => $rw,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/rw/{rw}",
     *     tags={"Admin - Data RW"},
     *     summary="Menghapus data RW",
     *     @OA\Parameter(name="rw", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="RW berhasil dihapus"),
     *     @OA\Response(response=409, description="RW masih memiliki RT")
     * )
     */
    public function destroy(Rw $rw): JsonResponse
    {
        if ($rw->rt()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'RW tidak dapat dihapus karena masih memiliki data RT.',
            ], 409);
        }

        $rw->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data RW berhasil dihapus.',
        ]);
    }
}