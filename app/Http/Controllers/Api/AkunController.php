<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Akun;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AkunController extends Controller
{
    /**
     * Menampilkan daftar akun untuk Admin.
     *
     * @OA\Get(
     *     path="/api/admin/akun",
     *     tags={"Admin - Manajemen Akun"},
     *     summary="Menampilkan daftar akun",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false,
     *         @OA\Schema(type="string")),
     *     @OA\Parameter(name="role", in="query", required=false,
     *         @OA\Schema(type="string", enum={"Admin","Panitia","Warga"})),
     *     @OA\Parameter(name="status", in="query", required=false,
     *         @OA\Schema(type="string", enum={"Aktif","Nonaktif"})),
     *     @OA\Parameter(name="per_page", in="query", required=false,
     *         @OA\Schema(type="integer", example=15)),
     *     @OA\Response(response=200, description="Daftar akun berhasil diambil")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['Admin', 'Panitia', 'Warga'])],
            'status' => ['nullable', Rule::in(['Aktif', 'Nonaktif'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Akun::with([
            'warga.rt.rw',
            'role',
        ]);

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhereHas('warga', function ($wargaQuery) use ($search) {
                        $wargaQuery->where('nama', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($validated['role'])) {
            $query->whereHas('role', function ($roleQuery) use ($validated) {
                $roleQuery->where('nama_role', $validated['role']);
            });
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $akun = $query
            ->orderBy('id_akun', 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Daftar akun berhasil diambil.',
            'data' => $akun,
        ]);
    }

    /**
     * Menampilkan detail akun.
     *
     * @OA\Get(
     *     path="/api/admin/akun/{id_akun}",
     *     tags={"Admin - Manajemen Akun"},
     *     summary="Menampilkan detail akun",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id_akun", in="path", required=true,
     *         @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detail akun berhasil diambil"),
     *     @OA\Response(response=404, description="Akun tidak ditemukan")
     * )
     */
    public function show($id_akun): JsonResponse
    {
        $akun = Akun::with([
            'warga.rt.rw',
            'role',
        ])->findOrFail($id_akun);

        return response()->json([
            'success' => true,
            'message' => 'Detail akun berhasil diambil.',
            'data' => $akun,
        ]);
    }

    /**
     * Mengubah role akun.
     *
     * @OA\Patch(
     *     path="/api/admin/akun/{id_akun}/role",
     *     tags={"Admin - Manajemen Akun"},
     *     summary="Mengubah role akun",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id_akun", in="path", required=true,
     *         @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"id_role"},
     *             @OA\Property(property="id_role", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Role akun berhasil diperbarui"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function updateRole(Request $request, $id_akun): JsonResponse
    {
        $validated = $request->validate([
            'id_role' => [
                'required',
                'integer',
                Rule::exists('role', 'id_role'),
            ],
        ]);

        $akun = Akun::with('role')->findOrFail($id_akun);
        $adminLogin = $request->user();

        // Admin tidak boleh mengubah role akunnya sendiri.
        if ($adminLogin && (int) $adminLogin->id_akun === (int) $akun->id_akun) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak dapat mengubah role akun sendiri.',
            ], 403);
        }

        $roleBaru = (int) $validated['id_role'];
        $roleLamaAdalahAdmin = $akun->role?->nama_role === 'Admin';

        // Lindungi Admin aktif terakhir.
        if (
            $roleLamaAdalahAdmin &&
            $roleBaru !== (int) $akun->id_role &&
            $akun->status === 'Aktif'
        ) {
            $jumlahAdminAktif = Akun::where('status', 'Aktif')
                ->whereHas('role', function ($query) {
                    $query->where('nama_role', 'Admin');
                })
                ->count();

            if ($jumlahAdminAktif <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role Admin tidak dapat diubah karena ini adalah Admin aktif terakhir.',
                ], 422);
            }
        }

        $akun->id_role = $roleBaru;
        $akun->save();

        $akun->load(['warga.rt.rw', 'role']);

        return response()->json([
            'success' => true,
            'message' => 'Role akun berhasil diperbarui.',
            'data' => $akun,
        ]);
    }

    /**
     * Mengubah status akun.
     *
     * @OA\Patch(
     *     path="/api/admin/akun/{id_akun}/status",
     *     tags={"Admin - Manajemen Akun"},
     *     summary="Mengubah status akun",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id_akun", in="path", required=true,
     *         @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string",
     *                 enum={"Aktif","Nonaktif"}, example="Nonaktif")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status akun berhasil diperbarui"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function updateStatus(Request $request, $id_akun): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['Aktif', 'Nonaktif']),
            ],
        ]);

        $akun = Akun::with('role')->findOrFail($id_akun);
        $adminLogin = $request->user();

        // Admin tidak boleh menonaktifkan akunnya sendiri.
        if (
            $adminLogin &&
            (int) $adminLogin->id_akun === (int) $akun->id_akun &&
            $validated['status'] === 'Nonaktif'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak dapat menonaktifkan akun sendiri.',
            ], 403);
        }

        // Lindungi Admin aktif terakhir.
        if (
            $akun->role?->nama_role === 'Admin' &&
            $akun->status === 'Aktif' &&
            $validated['status'] === 'Nonaktif'
        ) {
            $jumlahAdminAktif = Akun::where('status', 'Aktif')
                ->whereHas('role', function ($query) {
                    $query->where('nama_role', 'Admin');
                })
                ->count();

            if ($jumlahAdminAktif <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak dapat dinonaktifkan karena ini adalah Admin aktif terakhir.',
                ], 422);
            }
        }

        $akun->status = $validated['status'];
        $akun->save();

        $akun->load(['warga.rt.rw', 'role']);

        return response()->json([
            'success' => true,
            'message' => 'Status akun berhasil diperbarui.',
            'data' => $akun,
        ]);
    }
}
