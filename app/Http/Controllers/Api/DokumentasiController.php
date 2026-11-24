<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlbumDokumentasi;
use Cloudinary\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;
use Throwable;

class DokumentasiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/dokumentasi",
     *     operationId="getDaftarAlbumDokumentasiUmum",
     *     tags={"Role Umum"},
     *     summary="Mengambil daftar album dokumentasi publik",
     *     description="Daftar album dokumentasi yang dapat diakses tanpa login.",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Pencarian berdasarkan nama album",
     *         @OA\Schema(type="string", example="HUT RI")
     *     ),
     *     @OA\Parameter(
     *         name="id_periode",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan ID Periode",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="id_lomba",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan ID Lomba",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar album dokumentasi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar album dokumentasi berhasil diambil."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Parameter filter tidak valid")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'id_periode' => ['nullable', 'integer', 'exists:periode,id_periode'],
            'id_lomba' => ['nullable', 'integer', 'exists:lomba,id_lomba'],
        ]);

        $albums = AlbumDokumentasi::with([
            'periode:id_periode,tahun,nama_kegiatan',
            'lomba:id_lomba,nama_lomba'
        ])
            ->withCount([
                'media as jumlah_foto' => fn ($q) => $q->where('jenis_file', 'Foto'),
                'media as jumlah_video' => fn ($q) => $q->where('jenis_file', 'Video'),
            ])
            ->when($validated['search'] ?? null, fn ($q, $search) =>
                $q->where('nama_album', 'like', "%{$search}%")
            )
            ->when($validated['id_periode'] ?? null, fn ($q, $id) =>
                $q->where('id_periode', $id)
            )
            ->when($validated['id_lomba'] ?? null, fn ($q, $id) =>
                $q->where('id_lomba', $id)
            )
            ->latest('tanggal_album')
            ->latest('id_album')
            ->get();

        return $this->ok(
            $albums->map(fn ($album) => $this->albumData($album)),
            'Daftar album dokumentasi berhasil diambil.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/dokumentasi/{id_album}",
     *     operationId="getDetailAlbumDokumentasiUmum",
     *     tags={"Role Umum"},
     *     summary="Mengambil detail album dokumentasi publik",
     *     description="Detail album dan media dokumentasi yang dapat diakses tanpa login.",
     *     @OA\Parameter(
     *         name="id_album",
     *         in="path",
     *         required=true,
     *         description="ID Album Dokumentasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail album dokumentasi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail album dokumentasi berhasil diambil."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Album dokumentasi tidak ditemukan")
     * )
     */
    public function show(int $id_album): JsonResponse
    {
        $album = AlbumDokumentasi::with([
            'periode:id_periode,tahun,nama_kegiatan',
            'lomba:id_lomba,nama_lomba',
            'media'
        ])->find($id_album);

        if (!$album) {
            return $this->error('Album dokumentasi tidak ditemukan.', 404);
        }

        return $this->ok(
            $this->albumData($album, true),
            'Detail album dokumentasi berhasil diambil.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/dokumentasi",
     *     operationId="storeAlbumDokumentasi",
     *     tags={"Dokumentasi"},
     *     summary="Membuat album dokumentasi baru",
     *     description="Hanya dapat diakses oleh Admin dan Panitia. Menggunakan multipart/form-data jika menyertakan cover.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nama_album"},
     *                 @OA\Property(property="nama_album", type="string", example="Album Pembukaan HUT RI"),
     *                 @OA\Property(property="tanggal_album", type="string", format="date", example="2026-08-17"),
     *                 @OA\Property(property="id_periode", type="integer", example=1),
     *                 @OA\Property(property="id_lomba", type="integer", example=1),
     *                 @OA\Property(property="cover", type="string", format="binary", description="File cover gambar (jpg, png, webp)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Album dokumentasi berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Album dokumentasi berhasil dibuat."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (hanya Admin/Panitia)"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->albumValidation($request);

        if (!empty($validated['id_periode']) && !empty($validated['id_lomba'])) {
            $lombaValid = \App\Models\Lomba::where('id_lomba', $validated['id_lomba'])
                ->where('id_periode', $validated['id_periode'])
                ->exists();

            if (!$lombaValid) {
                return $this->error('Lomba yang dipilih tidak sesuai dengan periode yang ditentukan.', 422);
            }
        }

        $coverUrl = null;
        $cloudinaryPublicId = null;
        $cloudinary = null;

        if ($request->hasFile('cover')) {
            try {
                $cloudinary = new Cloudinary();
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('cover')->getRealPath(),
                    [
                        'folder' => 'pad/dokumentasi/covers',
                        'resource_type' => 'image',
                    ]
                );

                $coverUrl = $upload['secure_url'] ?? $upload['url'];
                $cloudinaryPublicId = $upload['public_id'] ?? null;
            } catch (Throwable $e) {
                Log::error('Gagal mengunggah cover album ke Cloudinary: ' . $e->getMessage(), [
                    'exception' => $e
                ]);

                return $this->error('Gagal mengunggah cover album ke penyimpanan cloud. Silakan coba kembali.', 500);
            }
        }

        try {
            $album = AlbumDokumentasi::create(
                array_merge($validated, ['cover' => $coverUrl])
            );
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

        return $this->ok(
            $this->albumData($album->load(['periode', 'lomba'])),
            'Album dokumentasi berhasil dibuat.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/dokumentasi/{id_album}",
     *     operationId="updateAlbumDokumentasi",
     *     tags={"Dokumentasi"},
     *     summary="Memperbarui data album dokumentasi",
     *     description="Hanya dapat diakses oleh Admin dan Panitia. Untuk update file cover, gunakan POST dengan _method=PUT (multipart/form-data) atau gunakan PUT raw JSON tanpa file cover.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_album",
     *         in="path",
     *         required=true,
     *         description="ID Album Dokumentasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nama_album", type="string", example="Album Pembukaan HUT RI Revisi"),
     *             @OA\Property(property="tanggal_album", type="string", format="date", example="2026-08-17"),
     *             @OA\Property(property="id_periode", type="integer", example=1),
     *             @OA\Property(property="id_lomba", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Album dokumentasi berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Album dokumentasi berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak"),
     *     @OA\Response(response=404, description="Album dokumentasi tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function update(Request $request, int $id_album): JsonResponse
    {
        $album = AlbumDokumentasi::find($id_album);

        if (!$album) {
            return $this->error('Album dokumentasi tidak ditemukan.', 404);
        }

        $validated = $this->albumValidation($request, true);

        $targetPeriode = array_key_exists('id_periode', $validated)
            ? $validated['id_periode']
            : $album->id_periode;

        $targetLomba = array_key_exists('id_lomba', $validated)
            ? $validated['id_lomba']
            : $album->id_lomba;

        if (!empty($targetPeriode) && !empty($targetLomba)) {
            $lombaValid = \App\Models\Lomba::where('id_lomba', $targetLomba)
                ->where('id_periode', $targetPeriode)
                ->exists();

            if (!$lombaValid) {
                return $this->error('Lomba yang dipilih tidak sesuai dengan periode yang ditentukan.', 422);
            }
        }

        $oldCover = $album->cover;
        $newCoverUrl = null;
        $cloudinaryPublicId = null;
        $cloudinary = null;

        if ($request->hasFile('cover')) {
            try {
                $cloudinary = new Cloudinary();
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('cover')->getRealPath(),
                    [
                        'folder' => 'pad/dokumentasi/covers',
                        'resource_type' => 'image',
                    ]
                );

                $newCoverUrl = $upload['secure_url'] ?? $upload['url'];
                $cloudinaryPublicId = $upload['public_id'] ?? null;
                $validated['cover'] = $newCoverUrl;
            } catch (Throwable $e) {
                Log::error('Gagal mengunggah cover baru ke Cloudinary: ' . $e->getMessage(), [
                    'exception' => $e
                ]);

                return $this->error('Gagal mengunggah cover album ke penyimpanan cloud. Silakan coba kembali.', 500);
            }
        }

        try {
            $album->update($validated);
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

        // Cover lama hanya dihapus setelah cover baru dan record database berhasil disimpan.
        if ($newCoverUrl && $oldCover) {
            $this->deleteSingleFile($oldCover, 'image');
        }

        return $this->ok(
            $this->albumData($album->fresh(['periode', 'lomba'])),
            'Album dokumentasi berhasil diperbarui.'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/dokumentasi/{id_album}",
     *     operationId="destroyAlbumDokumentasi",
     *     tags={"Dokumentasi"},
     *     summary="Menghapus album dokumentasi beserta seluruh medianya",
     *     description="Hanya dapat diakses oleh Admin dan Panitia.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_album",
     *         in="path",
     *         required=true,
     *         description="ID Album Dokumentasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Album dokumentasi berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Album dan seluruh media dokumentasi berhasil dihapus."),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak"),
     *     @OA\Response(response=404, description="Album dokumentasi tidak ditemukan")
     * )
     */
    public function destroy(int $id_album): JsonResponse
    {
        $album = AlbumDokumentasi::with('media')->find($id_album);

        if (!$album) {
            return $this->error('Album dokumentasi tidak ditemukan.', 404);
        }

        // Ambil daftar file media beserta jenis_file dan cover album sebelum record database dihapus
        $mediaItems = $album->media->map(fn ($m) => [
            'file' => $m->file,
            'jenis_file' => $m->jenis_file,
        ])->all();

        $coverFile = $album->cover;

        // Hapus record media dan album dalam satu transaksi database.
        DB::transaction(function () use ($album) {
            $album->media()->delete();
            $album->delete();
        });

        // Hapus seluruh file fisik media setelah transaksi database berhasil
        foreach ($mediaItems as $item) {
            if (!empty($item['file'])) {
                $resourceType = ($item['jenis_file'] === 'Video') ? 'video' : 'image';
                $this->deleteSingleFile($item['file'], $resourceType);
            }
        }

        // Hapus file cover album
        if ($coverFile) {
            $this->deleteSingleFile($coverFile, 'image');
        }

        return $this->ok(null, 'Album dan seluruh media dokumentasi berhasil dihapus.');
    }

    /**
     * @OA\Post(
     *     path="/api/dokumentasi/{id_album}/media",
     *     operationId="uploadMediaDokumentasi",
     *     tags={"Dokumentasi"},
     *     summary="Mengunggah foto atau video ke dalam album",
     *     description="Hanya dapat diakses oleh Admin dan Panitia. Menggunakan multipart/form-data.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_album",
     *         in="path",
     *         required=true,
     *         description="ID Album Dokumentasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"jenis_file","file"},
     *                 @OA\Property(property="jenis_file", type="string", enum={"Foto","Video"}, example="Foto"),
     *                 @OA\Property(property="file", type="string", format="binary", description="File foto (jpg, png, webp) atau video (mp4, mov, avi, mkv) max 50MB"),
     *                 @OA\Property(property="keterangan", type="string", example="Dokumentasi saat babak semifinal")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Media dokumentasi berhasil diunggah",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Media dokumentasi berhasil diunggah."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak"),
     *     @OA\Response(response=404, description="Album dokumentasi tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function uploadMedia(Request $request, int $id_album): JsonResponse
    {
        $album = AlbumDokumentasi::find($id_album);

        if (!$album) {
            return $this->error('Album dokumentasi tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'jenis_file' => ['required', Rule::in(['Foto', 'Video'])],
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska'
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $request->file('file');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

        if (($validated['jenis_file'] === 'Foto') !== $isImage) {
            return $this->error('Jenis file tidak sesuai dengan jenis media.', 422);
        }

        $resourceType = ($validated['jenis_file'] === 'Video') ? 'video' : 'image';
        $uploadedUrl = null;
        $cloudinaryPublicId = null;
        $cloudinary = null;

        try {
            $cloudinary = new Cloudinary();
            $upload = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'pad/dokumentasi/media',
                    'resource_type' => $resourceType,
                ]
            );

            $uploadedUrl = $upload['secure_url'] ?? $upload['url'];
            $cloudinaryPublicId = $upload['public_id'] ?? null;
        } catch (Throwable $e) {
            Log::error('Gagal mengunggah media ke Cloudinary: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return $this->error('Gagal mengunggah media ke penyimpanan cloud. Silakan coba kembali.', 500);
        }

        try {
            $media = $album->media()->create([
                'file' => $uploadedUrl,
                'jenis_file' => $validated['jenis_file'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
        } catch (Throwable $e) {
            if ($cloudinary && $cloudinaryPublicId) {
                try {
                    $cloudinary->uploadApi()->destroy($cloudinaryPublicId, [
                        'resource_type' => $resourceType,
                    ]);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }

            throw $e;
        }

        return $this->ok(
            $this->mediaData($media),
            'Media dokumentasi berhasil diunggah.',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/dokumentasi/{id_album}/media/{id_dokumentasi}",
     *     operationId="destroyMediaDokumentasi",
     *     tags={"Dokumentasi"},
     *     summary="Menghapus foto atau video tertentu dari album",
     *     description="Hanya dapat diakses oleh Admin dan Panitia.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_album",
     *         in="path",
     *         required=true,
     *         description="ID Album Dokumentasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="id_dokumentasi",
     *         in="path",
     *         required=true,
     *         description="ID Media Dokumentasi",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media dokumentasi berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Media dokumentasi berhasil dihapus."),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak"),
     *     @OA\Response(response=404, description="Media dokumentasi tidak ditemukan")
     * )
     */
    public function destroyMedia(int $id_album, int $id_dokumentasi): JsonResponse
    {
        $media = AlbumDokumentasi::find($id_album)
            ?->media()
            ->find($id_dokumentasi);

        if (!$media) {
            return $this->error('Media dokumentasi tidak ditemukan.', 404);
        }

        $filePath = $media->file;
        $resourceType = ($media->jenis_file === 'Video') ? 'video' : 'image';

        DB::transaction(fn () => $media->delete());

        if ($filePath) {
            $this->deleteSingleFile($filePath, $resourceType);
        }

        return $this->ok(null, 'Media dokumentasi berhasil dihapus.');
    }

    private function albumValidation(Request $request, bool $sometimes = false): array
    {
        $rule = $sometimes ? 'sometimes' : 'required';

        return $request->validate([
            'nama_album' => [$rule, 'string', 'max:150'],
            'tanggal_album' => ['nullable', 'date'],
            'id_periode' => ['nullable', 'integer', 'exists:periode,id_periode'],
            'id_lomba' => ['nullable', 'integer', 'exists:lomba,id_lomba'],
            'cover' => [
                $sometimes ? 'sometimes' : 'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120'
            ],
        ]);
    }

    private function albumData(AlbumDokumentasi $album, bool $detail = false): array
    {
        $data = [
            'id_album' => $album->id_album,
            'nama_album' => $album->nama_album,
            'tanggal_album' => optional($album->tanggal_album)->toDateString(),
            'cover' => $this->url($album->cover),
            'jumlah_foto' => (int) (
                $album->jumlah_foto
                ?? $album->media->where('jenis_file', 'Foto')->count()
            ),
            'jumlah_video' => (int) (
                $album->jumlah_video
                ?? $album->media->where('jenis_file', 'Video')->count()
            ),
            'periode' => $album->periode
                ? [
                    'id_periode' => $album->periode->id_periode,
                    'tahun' => $album->periode->tahun,
                    'nama_kegiatan' => $album->periode->nama_kegiatan
                ]
                : null,
            'lomba' => $album->lomba
                ? [
                    'id_lomba' => $album->lomba->id_lomba,
                    'nama_lomba' => $album->lomba->nama_lomba
                ]
                : null,
        ];

        if ($detail) {
            $data['media'] = $album->media
                ->map(fn ($media) => $this->mediaData($media))
                ->values();
        }

        return $data;
    }

    private function mediaData($media): array
    {
        return [
            'id_dokumentasi' => $media->id_dokumentasi,
            'jenis_file' => $media->jenis_file,
            'file' => $this->url($media->file),
            'keterangan' => $media->keterangan,
            'created_at' => $media->created_at,
        ];
    }

    private function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Hapus satu file fisik dari Cloudinary (jika URL Cloudinary) atau Storage lokal.
     */
    private function deleteSingleFile(string $path, string $resourceType = 'image'): void
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $publicId = $this->extractCloudinaryPublicId($path);
            
            if ($publicId) {
                try {
                    $cloudinary = new Cloudinary();
                    $cloudinary->uploadApi()->destroy($publicId, [
                        'resource_type' => $resourceType,
                    ]);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        } else {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Ekstrak public_id dari Cloudinary delivery URL.
     */
    private function extractCloudinaryPublicId(string $url): ?string
    {
        $parsed = parse_url($url);
        if (($parsed['host'] ?? '') !== 'res.cloudinary.com') {
            return null;
        }

        $path = $parsed['path'] ?? '';
        if (preg_match('#/(?:image|video|raw)/upload/(?:v\d+/)?(.+?)(?:\.[a-zA-Z0-9]+)?$#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function ok($data, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $status);
    }
}
