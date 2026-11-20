<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlbumDokumentasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DokumentasiController extends Controller
{
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

        $cover = null;

        if ($request->hasFile('cover')) {
            $cover = $request->file('cover')->store('dokumentasi/covers', 'public');
        }

        try {
            $album = AlbumDokumentasi::create(
                array_merge($validated, ['cover' => $cover])
            );
        } catch (\Throwable $e) {
            if ($cover) {
                Storage::disk('public')->delete($cover);
            }

            throw $e;
        }

        return $this->ok(
            $this->albumData($album->load(['periode', 'lomba'])),
            'Album dokumentasi berhasil dibuat.',
            201
        );
    }

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
        $newCover = null;

        if ($request->hasFile('cover')) {
            $newCover = $request->file('cover')->store('dokumentasi/covers', 'public');
            $validated['cover'] = $newCover;
        }

        try {
            $album->update($validated);
        } catch (\Throwable $e) {
            if ($newCover) {
                Storage::disk('public')->delete($newCover);
            }

            throw $e;
        }

        // Cover lama hanya dihapus setelah cover baru berhasil disimpan.
        if ($newCover && $oldCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return $this->ok(
            $this->albumData($album->fresh(['periode', 'lomba'])),
            'Album dokumentasi berhasil diperbarui.'
        );
    }

    public function destroy(int $id_album): JsonResponse
    {
        $album = AlbumDokumentasi::with('media')->find($id_album);

        if (!$album) {
            return $this->error('Album dokumentasi tidak ditemukan.', 404);
        }

        // Simpan path file sebelum record database dihapus.
        $paths = $album->media
            ->pluck('file')
            ->filter()
            ->push($album->cover)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Hapus record media dan album dalam satu transaksi.
        DB::transaction(function () use ($album) {
            $album->media()->delete();
            $album->delete();
        });

        // Hapus file fisik setelah transaksi database berhasil.
        Storage::disk('public')->delete($paths);

        return $this->ok(null, 'Album dan seluruh media dokumentasi berhasil dihapus.');
    }

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

        $path = $file->store('dokumentasi/media', 'public');

        try {
            $media = $album->media()->create([
                'file' => $path,
                'jenis_file' => $validated['jenis_file'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return $this->ok(
            $this->mediaData($media),
            'Media dokumentasi berhasil diunggah.',
            201
        );
    }

    public function destroyMedia(int $id_album, int $id_dokumentasi): JsonResponse
    {
        $media = AlbumDokumentasi::find($id_album)
            ?->media()
            ->find($id_dokumentasi);

        if (!$media) {
            return $this->error('Media dokumentasi tidak ditemukan.', 404);
        }

        $path = $media->file;

        DB::transaction(fn () => $media->delete());

        Storage::disk('public')->delete($path);

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
        return $path ? Storage::disk('public')->url($path) : null;
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