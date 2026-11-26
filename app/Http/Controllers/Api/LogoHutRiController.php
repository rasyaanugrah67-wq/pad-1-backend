<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogoHutRi;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;
use Throwable;

class LogoHutRiController extends Controller
{
    /**
     * Menampilkan logo HUT RI yang sedang aktif untuk navbar publik.
     *
     * @OA\Get(
     *     path="/api/logo-hut-ri/aktif",
     *     operationId="getLogoHutRiAktif",
     *     tags={"Role Umum"},
     *     summary="Melihat logo HUT RI yang sedang aktif (Publik)",
     *     description="Mengambil satu logo HUT RI yang berstatus Aktif untuk ditampilkan pada navbar website. Dapat diakses publik tanpa login.",
     *     @OA\Response(
     *         response=200,
     *         description="Logo HUT RI aktif berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logo HUT RI aktif berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id_logo", type="integer", example=1),
     *                 @OA\Property(property="tahun", type="integer", example=2026),
     *                 @OA\Property(property="nama_logo", type="string", example="Logo HUT RI ke-81"),
     *                 @OA\Property(property="gambar", type="string", example="https://res.cloudinary.com/.../pad/logo-hut-ri/logo-2026.png"),
     *                 @OA\Property(property="status", type="string", example="Aktif")
     *             )
     *         )
     *     )
     * )
     */
    public function active(): JsonResponse
    {
        $logo = LogoHutRi::where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        return response()->json([
            'success' => true,
            'message' => $logo
                ? 'Logo HUT RI aktif berhasil diambil.'
                : 'Belum ada logo HUT RI yang aktif.',
            'data' => $logo,
        ]);
    }

    /**
     * Menampilkan daftar seluruh logo HUT RI per tahun.
     *
     * @OA\Get(
     *     path="/api/admin/logo-hut-ri",
     *     operationId="getDaftarLogoHutRi",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Menampilkan daftar logo HUT RI",
     *     description="Mengambil seluruh data logo HUT RI per tahun, diurutkan dari tahun terbaru. Hanya dapat diakses oleh Admin.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Cari berdasarkan nama logo atau tahun",
     *         @OA\Schema(type="string", example="81")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan status logo",
     *         @OA\Schema(type="string", enum={"Aktif", "Nonaktif"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar logo HUT RI berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar logo HUT RI berhasil diambil."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['Aktif', 'Nonaktif'])],
        ]);

        $query = LogoHutRi::query();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nama_logo', 'like', "%{$search}%")
                    ->orWhere('tahun', 'like', "%{$search}%");
            });
        }

        $logos = $query
            ->orderByDesc('tahun')
            ->orderByDesc('id_logo')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar logo HUT RI berhasil diambil.',
            'data' => $logos,
        ]);
    }

    /**
     * Menambahkan logo HUT RI baru dan mengunggah gambar ke Cloudinary.
     *
     * @OA\Post(
     *     path="/api/admin/logo-hut-ri",
     *     operationId="storeLogoHutRi",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Menambahkan logo HUT RI baru",
     *     description="Admin menambahkan logo HUT RI untuk tahun tertentu dengan mengunggah file gambar ke Cloudinary folder pad/logo-hut-ri. Jika status diset Aktif, logo aktif sebelumnya otomatis menjadi Nonaktif tanpa dihapus.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"tahun", "nama_logo", "gambar"},
     *                 @OA\Property(property="tahun", type="integer", example=2026),
     *                 @OA\Property(property="nama_logo", type="string", maxLength=150, example="Logo HUT RI ke-81"),
     *                 @OA\Property(property="gambar", type="string", format="binary", description="File gambar logo (JPEG, JPG, PNG, WebP), maks 5 MB"),
     *                 @OA\Property(property="status", type="string", enum={"Aktif", "Nonaktif"}, example="Nonaktif")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Logo HUT RI berhasil ditambahkan"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=422, description="Validasi gagal (termasuk tahun duplikat)"),
     *     @OA\Response(response=500, description="Gagal mengunggah gambar ke Cloudinary")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'min:1945', 'max:2100', 'unique:logo_hut_ri,tahun'],
            'nama_logo' => ['required', 'string', 'max:150'],
            'gambar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'status' => ['sometimes', Rule::in(['Aktif', 'Nonaktif'])],
        ]);

        $status = $validated['status'] ?? 'Nonaktif';

        try {
            $uploaded = $this->uploadToCloudinary($request->file('gambar'));
        } catch (Throwable $e) {
            $this->logCloudinaryError('Gagal mengunggah logo HUT RI ke Cloudinary.', $e, $request->file('gambar'));

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah gambar logo ke penyimpanan cloud. Silakan coba kembali.',
            ], 500);
        }

        try {
            $logo = DB::transaction(function () use ($validated, $uploaded, $status) {
                if ($status === 'Aktif') {
                    LogoHutRi::where('status', 'Aktif')->update(['status' => 'Nonaktif']);
                }

                return LogoHutRi::create([
                    'tahun' => (int) $validated['tahun'],
                    'nama_logo' => $validated['nama_logo'],
                    'gambar' => $uploaded['secure_url'],
                    'status' => $status,
                ]);
            });
        } catch (Throwable $e) {
            if (!empty($uploaded['public_id'])) {
                $this->destroyCloudinaryByPublicId($uploaded['public_id']);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo HUT RI berhasil ditambahkan.',
            'data' => $logo,
        ], 201);
    }

    /**
     * Menampilkan detail satu logo HUT RI.
     *
     * @OA\Get(
     *     path="/api/admin/logo-hut-ri/{id}",
     *     operationId="showLogoHutRi",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Menampilkan detail logo HUT RI",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Detail logo HUT RI berhasil diambil"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Logo HUT RI tidak ditemukan")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $logo = LogoHutRi::find($id);

        if (!$logo) {
            return response()->json([
                'success' => false,
                'message' => 'Logo HUT RI tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail logo HUT RI berhasil diambil.',
            'data' => $logo,
        ]);
    }

    /**
     * Mengubah data dan/atau gambar logo HUT RI.
     *
     * @OA\Put(
     *     path="/api/admin/logo-hut-ri/{id}",
     *     operationId="updateLogoHutRi",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Mengubah data atau gambar logo HUT RI (PUT)",
     *     description="Admin dapat mengubah tahun, nama_logo, status, maupun gambar logo. Jika mengunggah file multipart/form-data dari browser/Swagger, disarankan menggunakan POST /api/admin/logo-hut-ri/{id} atau method spoofing _method=PUT.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="tahun", type="integer", example=2027),
     *                 @OA\Property(property="nama_logo", type="string", maxLength=150, example="Logo HUT RI ke-82"),
     *                 @OA\Property(property="gambar", type="string", format="binary", description="File gambar baru (opsional, maks 5 MB)"),
     *                 @OA\Property(property="status", type="string", enum={"Aktif", "Nonaktif"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logo HUT RI berhasil diperbarui"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Logo HUT RI tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=500, description="Gagal mengunggah gambar ke Cloudinary")
     * )
     *
     * @OA\Post(
     *     path="/api/admin/logo-hut-ri/{id}",
     *     operationId="updateLogoHutRiPost",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Mengubah data dan upload gambar logo HUT RI (POST multipart/form-data)",
     *     description="Alternatif POST multipart/form-data untuk memperbarui data dan mengunggah file gambar logo agar kompatibel dengan parsing file PHP.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="tahun", type="integer", example=2027),
     *                 @OA\Property(property="nama_logo", type="string", maxLength=150, example="Logo HUT RI ke-82"),
     *                 @OA\Property(property="gambar", type="string", format="binary", description="File gambar baru (opsional, maks 5 MB)"),
     *                 @OA\Property(property="status", type="string", enum={"Aktif", "Nonaktif"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Logo HUT RI berhasil diperbarui"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Logo HUT RI tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=500, description="Gagal mengunggah gambar ke Cloudinary")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $logo = LogoHutRi::find($id);

        if (!$logo) {
            return response()->json([
                'success' => false,
                'message' => 'Logo HUT RI tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'tahun' => [
                'sometimes',
                'required',
                'integer',
                'min:1945',
                'max:2100',
                Rule::unique('logo_hut_ri', 'tahun')->ignore($logo->id_logo, 'id_logo'),
            ],
            'nama_logo' => ['sometimes', 'required', 'string', 'max:150'],
            'gambar' => ['sometimes', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'status' => ['sometimes', 'required', Rule::in(['Aktif', 'Nonaktif'])],
        ]);

        $oldImageUrl = $logo->gambar;
        $uploaded = null;

        if ($request->hasFile('gambar')) {
            try {
                $uploaded = $this->uploadToCloudinary($request->file('gambar'));
                $validated['gambar'] = $uploaded['secure_url'];
            } catch (Throwable $e) {
                $this->logCloudinaryError('Gagal mengunggah pembaruan logo HUT RI ke Cloudinary.', $e, $request->file('gambar'));

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengunggah gambar logo ke penyimpanan cloud. Silakan coba kembali.',
                ], 500);
            }
        }

        try {
            DB::transaction(function () use ($logo, $validated) {
                if (($validated['status'] ?? null) === 'Aktif') {
                    LogoHutRi::where('id_logo', '!=', $logo->id_logo)
                        ->where('status', 'Aktif')
                        ->update(['status' => 'Nonaktif']);
                }

                $logo->update($validated);
            });
        } catch (Throwable $e) {
            if ($uploaded && !empty($uploaded['public_id'])) {
                $this->destroyCloudinaryByPublicId($uploaded['public_id']);
            }
            throw $e;
        }

        // Hapus gambar lama di Cloudinary setelah DB berhasil diperbarui.
        if ($uploaded && $oldImageUrl && $oldImageUrl !== $uploaded['secure_url']) {
            $this->deleteCloudinaryImageByUrl($oldImageUrl);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo HUT RI berhasil diperbarui.',
            'data' => $logo->fresh(),
        ]);
    }

    /**
     * Menghapus logo HUT RI.
     *
     * @OA\Delete(
     *     path="/api/admin/logo-hut-ri/{id}",
     *     operationId="destroyLogoHutRi",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Menghapus logo HUT RI",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Logo HUT RI berhasil dihapus"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Logo HUT RI tidak ditemukan")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $logo = LogoHutRi::find($id);

        if (!$logo) {
            return response()->json([
                'success' => false,
                'message' => 'Logo HUT RI tidak ditemukan.',
            ], 404);
        }

        $imageUrl = $logo->gambar;
        $logo->delete();

        if ($imageUrl) {
            $this->deleteCloudinaryImageByUrl($imageUrl);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo HUT RI berhasil dihapus.',
        ]);
    }

    /**
     * Mengaktifkan satu logo HUT RI dan otomatis menonaktifkan logo lainnya.
     *
     * @OA\Patch(
     *     path="/api/admin/logo-hut-ri/{id}/aktif",
     *     operationId="activateLogoHutRi",
     *     tags={"Admin - Logo HUT RI"},
     *     summary="Mengaktifkan logo HUT RI",
     *     description="Mengubah status logo terpilih menjadi Aktif dan otomatis menonaktifkan logo aktif sebelumnya tanpa menghapus data logo tahun sebelumnya.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=2)),
     *     @OA\Response(response=200, description="Logo HUT RI berhasil diaktifkan"),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin)"),
     *     @OA\Response(response=404, description="Logo HUT RI tidak ditemukan")
     * )
     */
    public function activate(int $id): JsonResponse
    {
        $logo = LogoHutRi::find($id);

        if (!$logo) {
            return response()->json([
                'success' => false,
                'message' => 'Logo HUT RI tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($logo) {
            LogoHutRi::where('id_logo', '!=', $logo->id_logo)
                ->where('status', 'Aktif')
                ->update(['status' => 'Nonaktif']);

            $logo->status = 'Aktif';
            $logo->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Logo HUT RI berhasil diaktifkan.',
            'data' => $logo->fresh(),
        ]);
    }

    /**
     * @return array{secure_url: string, public_id: ?string}
     */
    protected function uploadToCloudinary(UploadedFile $file): array
    {
        $cloudinary = new Cloudinary();
        $upload = $cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => 'pad/logo-hut-ri',
                'resource_type' => 'image',
            ]
        );

        return [
            'secure_url' => (string) ($upload['secure_url'] ?? $upload['url']),
            'public_id' => $upload['public_id'] ?? null,
        ];
    }

    protected function destroyCloudinaryByPublicId(string $publicId): void
    {
        try {
            $cloudinary = new Cloudinary();
            $cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => 'image',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function deleteCloudinaryImageByUrl(string $url): void
    {
        $publicId = $this->extractCloudinaryPublicId($url);
        if ($publicId) {
            $this->destroyCloudinaryByPublicId($publicId);
        }
    }

    public function extractCloudinaryPublicId(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parsed = parse_url($url);
        if (($parsed['host'] ?? '') !== 'res.cloudinary.com') {
            return null;
        }

        $path = $parsed['path'] ?? '';
        if (!preg_match('#/image/upload/(?:v\d+/)?(.+?)(?:\.[^./]+)?$#', $path, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    private function logCloudinaryError(string $message, Throwable $e, ?UploadedFile $file): void
    {
        $safeMessage = preg_replace(
            '/(cloudinary:\/\/[^:]+:)[^@]+(@)/i',
            '$1[REDACTED]$2',
            $e->getMessage()
        );

        Log::error($message, [
            'exception_class' => $e::class,
            'exception_message' => $safeMessage,
            'file_name' => $file?->getClientOriginalName(),
            'file_mime' => $file?->getMimeType(),
            'file_size' => $file?->getSize(),
        ]);
    }
}
