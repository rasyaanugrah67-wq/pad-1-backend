<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class RtController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/rt",
     *     tags={"Admin - Data RT"},
     *     summary="Menampilkan daftar RT",
     *     @OA\Response(response=200, description="Daftar RT berhasil ditampilkan")
     * )
     */
    public function index(): JsonResponse
    {
        $rt = Rt::with('rw')
            ->withCount('warga')
            ->orderBy('nomor_rt')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar RT berhasil ditampilkan.',
            'data' => $rt,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/rt",
     *     tags={"Admin - Data RT"},
     *     summary="Menambahkan data RT",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_rw", "nomor_rt"},
     *             @OA\Property(property="id_rw", type="integer", example=1),
     *             @OA\Property(property="nomor_rt", type="string", example="001"),
     *             @OA\Property(property="nama_rt", type="string", example="RT 001")
     *         )
     *     ),
     *     @OA\Response(response=201, description="RT berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_rw' => ['required', 'integer', 'exists:rw,id_rw'],
            'nomor_rt' => [
                'required',
                'string',
                'max:10',
                Rule::unique('rt', 'nomor_rt')
                    ->where(fn ($query) => $query->where('id_rw', $request->input('id_rw'))),
            ],
            'nama_rt' => ['nullable', 'string', 'max:100'],
        ]);

        $rt = Rt::create($validated);
        $rt->load('rw');

        return response()->json([
            'success' => true,
            'message' => 'Data RT berhasil ditambahkan.',
            'data' => $rt,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/rt/{rt}",
     *     tags={"Admin - Data RT"},
     *     summary="Menampilkan detail RT",
     *     @OA\Parameter(name="rt", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detail RT berhasil ditampilkan"),
     *     @OA\Response(response=404, description="RT tidak ditemukan")
     * )
     */
    public function show(Rt $rt): JsonResponse
    {
        $rt->load('rw')
            ->loadCount('warga');

        return response()->json([
            'success' => true,
            'message' => 'Detail RT berhasil ditampilkan.',
            'data' => $rt,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/rt/{rt}",
     *     tags={"Admin - Data RT"},
     *     summary="Mengubah data RT",
     *     @OA\Parameter(name="rt", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_rw", type="integer", example=1),
     *             @OA\Property(property="nomor_rt", type="string", example="002"),
     *             @OA\Property(property="nama_rt", type="string", example="RT 002")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Data RT berhasil diubah"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function update(Request $request, Rt $rt): JsonResponse
    {
        $idRw = $request->input('id_rw', $rt->id_rw);

        $validated = $request->validate([
            'id_rw' => ['sometimes', 'required', 'integer', 'exists:rw,id_rw'],
            'nomor_rt' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('rt', 'nomor_rt')
                    ->where(fn ($query) => $query->where('id_rw', $idRw))
                    ->ignore($rt->id_rt, 'id_rt'),
            ],
            'nama_rt' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $rt->update($validated);
        $rt->load('rw');

        return response()->json([
            'success' => true,
            'message' => 'Data RT berhasil diperbarui.',
            'data' => $rt,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/rt/{rt}",
     *     tags={"Admin - Data RT"},
     *     summary="Menghapus data RT",
     *     @OA\Parameter(name="rt", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="RT berhasil dihapus"),
     *     @OA\Response(response=409, description="RT masih memiliki warga")
     * )
     */
    public function destroy(Rt $rt): JsonResponse
    {
        if ($rt->warga()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'RT tidak dapat dihapus karena masih memiliki data warga.',
            ], 409);
        }

        $rt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data RT berhasil dihapus.',
        ]);
    }
}