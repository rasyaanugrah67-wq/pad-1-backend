<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengaturanPembayaran;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PengaturanPembayaranController extends Controller
{
    /**
     * Mengambil pengaturan pembayaran iuran.
     *
     * @OA\Get(
     *     path="/api/pengaturan-pembayaran",
     *     operationId="getPengaturanPembayaran",
     *     tags={"Pengaturan Pembayaran"},
     *     summary="Melihat pengaturan pembayaran",
     *     description="Mengambil informasi rekening bank dan QRIS untuk pembayaran iuran.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pengaturan pembayaran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pengaturan pembayaran berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id_pengaturan", type="integer", example=1),
     *                 @OA\Property(property="nama_bank", type="string", nullable=true, example="BCA"),
     *                 @OA\Property(property="nomor_rekening", type="string", nullable=true, example="1234567890"),
     *                 @OA\Property(property="atas_nama", type="string", nullable=true, example="Panitia 17 Agustus"),
     *                 @OA\Property(property="qris_image", type="string", nullable=true, example="https://res.cloudinary.com/.../image/upload/.../qris.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses")
     * )
     */
    public function show(): JsonResponse
    {
        $settings = PengaturanPembayaran::query()->first();

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan pembayaran berhasil diambil.',
            'data' => $this->data($settings),
        ]);
    }

    /**
     * Memperbarui pengaturan pembayaran iuran.
     *
     * @OA\Post(
     *     path="/api/pengaturan-pembayaran",
     *     operationId="updatePengaturanPembayaranPost",
     *     tags={"Pengaturan Pembayaran"},
     *     summary="Mengubah pengaturan pembayaran dan upload QRIS (POST multipart/form-data)",
     *     description="Admin atau Panitia dapat mengubah informasi rekening bank dan mengunggah gambar QRIS. Direkomendasikan menggunakan HTTP POST multipart/form-data agar upload file terbaca dengan baik oleh server.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="nama_bank", type="string", maxLength=100, example="BCA"),
     *                 @OA\Property(property="nomor_rekening", type="string", maxLength=50, example="1234567890"),
     *                 @OA\Property(property="atas_nama", type="string", maxLength=150, example="Panitia 17 Agustus"),
     *                 @OA\Property(
     *                     property="qris_image",
     *                     type="string",
     *                     format="binary",
     *                     description="File gambar QRIS (JPEG, JPG, PNG, WebP), maksimal 5 MB"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pengaturan pembayaran berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pengaturan pembayaran berhasil diperbarui."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_pengaturan", type="integer", example=1),
     *                 @OA\Property(property="nama_bank", type="string", nullable=true, example="BCA"),
     *                 @OA\Property(property="nomor_rekening", type="string", nullable=true, example="1234567890"),
     *                 @OA\Property(property="atas_nama", type="string", nullable=true, example="Panitia 17 Agustus"),
     *                 @OA\Property(property="qris_image", type="string", nullable=true, example="https://res.cloudinary.com/.../image/upload/.../qris.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=422, description="Data tidak valid"),
     *     @OA\Response(response=500, description="Gagal mengunggah gambar QRIS ke penyimpanan cloud")
     * )
     *
     * @OA\Put(
     *     path="/api/pengaturan-pembayaran",
     *     operationId="updatePengaturanPembayaran",
     *     tags={"Pengaturan Pembayaran"},
     *     summary="Mengubah pengaturan pembayaran (PUT)",
     *     description="Admin atau Panitia dapat mengubah informasi rekening bank. Jika menyertakan file pada PUT, gunakan method spoofing _method=PUT atau gunakan POST.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="nama_bank", type="string", maxLength=100, example="BCA"),
     *                 @OA\Property(property="nomor_rekening", type="string", maxLength=50, example="1234567890"),
     *                 @OA\Property(property="atas_nama", type="string", maxLength=150, example="Panitia 17 Agustus"),
     *                 @OA\Property(
     *                     property="qris_image",
     *                     type="string",
     *                     format="binary",
     *                     description="File gambar QRIS (JPEG, JPG, PNG, WebP), maksimal 5 MB"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pengaturan pembayaran berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pengaturan pembayaran berhasil diperbarui."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_pengaturan", type="integer", example=1),
     *                 @OA\Property(property="nama_bank", type="string", nullable=true, example="BCA"),
     *                 @OA\Property(property="nomor_rekening", type="string", nullable=true, example="1234567890"),
     *                 @OA\Property(property="atas_nama", type="string", nullable=true, example="Panitia 17 Agustus"),
     *                 @OA\Property(property="qris_image", type="string", nullable=true, example="https://res.cloudinary.com/.../image/upload/.../qris.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Tidak memiliki hak akses"),
     *     @OA\Response(response=422, description="Data tidak valid"),
     *     @OA\Response(response=500, description="Gagal mengunggah gambar QRIS ke penyimpanan cloud")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_bank' => ['nullable', 'string', 'max:100'],
            'nomor_rekening' => ['nullable', 'string', 'max:50', 'regex:/^[0-9 .-]+$/'],
            'atas_nama' => ['nullable', 'string', 'max:150'],
            'qris_image' => ['sometimes', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $settings = PengaturanPembayaran::query()->first();
        $oldQris = $settings?->qris_image;
        $uploadedQrisUrl = null;
        $cloudinaryPublicId = null;
        $cloudinary = null;

        if ($request->hasFile('qris_image')) {
            try {
                $cloudinary = new Cloudinary();
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('qris_image')->getRealPath(),
                    [
                        'folder' => 'pad/qris',
                        'resource_type' => 'image',
                    ]
                );

                $uploadedQrisUrl = $upload['secure_url'] ?? $upload['url'];
                $cloudinaryPublicId = $upload['public_id'] ?? null;
                $validated['qris_image'] = $uploadedQrisUrl;
            } catch (Throwable $e) {
                $safeMessage = preg_replace(
                    '/(cloudinary:\/\/[^:]+:)[^@]+(@)/i',
                    '$1[REDACTED]$2',
                    $e->getMessage()
                );

                Log::error('Gagal mengunggah QRIS ke Cloudinary.', [
                    'exception_class' => $e::class,
                    'exception_message' => $safeMessage,
                    'exception_code' => $e->getCode(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'file_name' => $request->file('qris_image')?->getClientOriginalName(),
                    'file_mime' => $request->file('qris_image')?->getMimeType(),
                    'file_size' => $request->file('qris_image')?->getSize(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengunggah gambar QRIS ke penyimpanan cloud. Silakan coba kembali.',
                ], 500);
            }
        }

        try {
            if ($settings) {
                $settings->update($validated);
            } else {
                $settings = PengaturanPembayaran::create($validated);
            }
        } catch (Throwable $e) {
            if ($cloudinary && $cloudinaryPublicId) {
                try {
                    $cloudinary->uploadApi()->destroy($cloudinaryPublicId, [
                        'resource_type' => 'image',
                    ]);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $e;
        }

        // Hapus file QRIS lama jika sebelumnya disimpan di storage lokal
        if ($uploadedQrisUrl && $oldQris && !str_starts_with($oldQris, 'http://') && !str_starts_with($oldQris, 'https://')) {
            Storage::disk('public')->delete($oldQris);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan pembayaran berhasil diperbarui.',
            'data' => $this->data($settings->fresh()),
        ]);
    }

    private function data(?PengaturanPembayaran $settings): ?array
    {
        if (!$settings) return null;

        $qrisUrl = null;
        if ($settings->qris_image) {
            if (str_starts_with($settings->qris_image, 'http://') || str_starts_with($settings->qris_image, 'https://')) {
                $qrisUrl = $settings->qris_image;
            } else {
                $qrisUrl = Storage::disk('public')->url($settings->qris_image);
            }
        }

        return [
            'id_pengaturan' => $settings->id_pengaturan,
            'nama_bank' => $settings->nama_bank,
            'nomor_rekening' => $settings->nomor_rekening,
            'atas_nama' => $settings->atas_nama,
            'qris_image' => $qrisUrl,
        ];
    }
}
