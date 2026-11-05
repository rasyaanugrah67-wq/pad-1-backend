<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class WargaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/warga",
     *     tags={"Admin - Data Warga"},
     *     summary="Menampilkan daftar warga",
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="id_rt", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"Aktif", "Nonaktif"})),
     *     @OA\Response(response=200, description="Daftar warga berhasil ditampilkan"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki akses")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warga::with('rt.rw');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        if ($request->filled('id_rt')) {
            $query->where('id_rt', $request->id_rt);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json([
            'success' => true,
            'message' => 'Daftar warga berhasil ditampilkan.',
            'data' => $query->orderBy('nama')->paginate($perPage),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/warga",
     *     tags={"Admin - Data Warga"},
     *     summary="Menambahkan data warga",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_rt", "nik", "nama", "jenis_kelamin"},
     *             @OA\Property(property="id_rt", type="integer", example=1),
     *             @OA\Property(property="nik", type="string", example="3276010101900001"),
     *             @OA\Property(property="nama", type="string", example="Budi Santoso"),
     *             @OA\Property(property="no_hp", type="string", example="081234567890"),
     *             @OA\Property(property="alamat", type="string", example="Jalan Melati"),
     *             @OA\Property(property="jenis_kelamin", type="string", enum={"L", "P"}, example="L"),
     *             @OA\Property(property="tanggal_lahir", type="string", format="date", example="1990-01-01"),
     *             @OA\Property(property="status", type="string", enum={"Aktif", "Nonaktif"}, example="Aktif")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Warga berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_rt' => ['required', 'integer', 'exists:rt,id_rt'],
            'nik' => ['required', 'string', 'digits:16', 'unique:warga,nik'],
            'nama' => ['required', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tanggal_lahir' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['Aktif', 'Nonaktif'])],
        ]);

        $warga = Warga::create($validated);
        $warga->load('rt.rw');

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil ditambahkan.',
            'data' => $warga,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/warga/{warga}",
     *     tags={"Admin - Data Warga"},
     *     summary="Menampilkan detail warga",
     *     @OA\Parameter(name="warga", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detail warga berhasil ditampilkan"),
     *     @OA\Response(response=404, description="Warga tidak ditemukan")
     * )
     */
    public function show(Warga $warga): JsonResponse
    {
        $warga->load('rt.rw');

        return response()->json([
            'success' => true,
            'message' => 'Detail warga berhasil ditampilkan.',
            'data' => $warga,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/warga/{warga}",
     *     tags={"Admin - Data Warga"},
     *     summary="Mengubah data warga",
     *     @OA\Parameter(name="warga", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_rt", type="integer", example=1),
     *             @OA\Property(property="nik", type="string", example="3276010101900001"),
     *             @OA\Property(property="nama", type="string", example="Budi Santoso"),
     *             @OA\Property(property="no_hp", type="string", example="081234567890"),
     *             @OA\Property(property="alamat", type="string", example="Jalan Melati"),
     *             @OA\Property(property="jenis_kelamin", type="string", enum={"L", "P"}),
     *             @OA\Property(property="tanggal_lahir", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"Aktif", "Nonaktif"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Data warga berhasil diubah"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function update(Request $request, Warga $warga): JsonResponse
    {
        $validated = $request->validate([
            'id_rt' => ['sometimes', 'required', 'integer', 'exists:rt,id_rt'],
            'nik' => [
                'sometimes',
                'required',
                'string',
                'digits:16',
                Rule::unique('warga', 'nik')->ignore($warga->id_warga, 'id_warga'),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:150'],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:20'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'jenis_kelamin' => ['sometimes', 'required', Rule::in(['L', 'P'])],
            'tanggal_lahir' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['Aktif', 'Nonaktif'])],
        ]);

        $warga->update($validated);
        $warga->load('rt.rw');

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diperbarui.',
            'data' => $warga,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/warga/{warga}",
     *     tags={"Admin - Data Warga"},
     *     summary="Menonaktifkan warga",
     *     @OA\Parameter(name="warga", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Warga berhasil dinonaktifkan"),
     *     @OA\Response(response=404, description="Warga tidak ditemukan")
     * )
     */
    public function destroy(Warga $warga): JsonResponse
    {
        $warga->update([
            'status' => 'Nonaktif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warga berhasil dinonaktifkan.',
            'data' => $warga,
        ]);
    }
}