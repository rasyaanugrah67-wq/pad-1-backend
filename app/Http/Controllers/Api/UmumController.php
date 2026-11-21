<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalLomba;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class UmumController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/jadwal",
     *     operationId="getJadwalUmum",
     *     tags={"Role Umum"},
     *     summary="Mengambil jadwal lomba publik",
     *     @OA\Parameter(name="tanggal", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-08-17")),
     *     @OA\Parameter(name="id_lomba", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", example="Futsal")),
     *     @OA\Response(response=200, description="Jadwal berhasil diambil", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", example=true), @OA\Property(property="message", type="string"), @OA\Property(property="data", type="array", @OA\Items(type="object")))),
     *     @OA\Response(response=422, description="Parameter filter tidak valid")
     * )
     */
    public function jadwal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'id_lomba' => ['nullable', 'integer', 'exists:lomba,id_lomba'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $periode = DB::table('periode')->where('status', 'Aktif')->orderByDesc('tahun')->first();
        $jadwal = JadwalLomba::with(['lomba.kategori'])
            ->when($periode, fn ($q) => $q->whereHas('lomba', fn ($lomba) => $lomba->where('id_periode', $periode->id_periode)->where('status', '!=', 'Nonaktif')))
            ->when($validated['tanggal'] ?? null, fn ($q, $tanggal) => $q->whereDate('tanggal', $tanggal))
            ->when($validated['id_lomba'] ?? null, fn ($q, $id) => $q->where('id_lomba', $id))
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->whereHas('lomba', fn ($lomba) => $lomba->where('nama_lomba', 'like', "%{$search}%")->orWhereHas('kategori', fn ($kategori) => $kategori->where('nama_kategori', 'like', "%{$search}%"))))
            ->orderBy('tanggal')->orderBy('waktu_mulai')->get()
            ->map(fn ($item) => [
                'id_jadwal' => $item->id_jadwal,
                'id_lomba' => $item->id_lomba,
                'nama_lomba' => $item->lomba?->nama_lomba,
                'kategori' => $item->lomba?->kategori?->nama_kategori,
                'tanggal' => optional($item->tanggal)->toDateString(),
                'waktu_mulai' => $item->waktu_mulai,
                'waktu_selesai' => $item->waktu_selesai,
                'lokasi' => $item->lokasi,
            ])->values();

        return response()->json(['success' => true, 'message' => 'Jadwal lomba berhasil diambil.', 'data' => $jadwal]);
    }

    /**
     * @OA\Get(
     *     path="/api/pengumuman",
     *     operationId="getPengumumanUmum",
     *     tags={"Role Umum"},
     *     summary="Mengambil pengumuman publik",
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", example="Jadwal")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50, example=10)),
     *     @OA\Response(response=200, description="Pengumuman berhasil diambil", @OA\JsonContent(type="object", @OA\Property(property="success", type="boolean", example=true), @OA\Property(property="message", type="string"), @OA\Property(property="data", type="object"))),
     *     @OA\Response(response=422, description="Parameter pagination atau search tidak valid")
     * )
     */
    public function pengumuman(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $data = DB::table('pengumuman as p')
            ->join('periode as pe', 'p.id_periode', '=', 'pe.id_periode')
            ->where('p.status', 'Dipublikasi')->whereNotNull('p.tanggal_publikasi')->where('p.tanggal_publikasi', '<=', now())
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('p.judul', 'like', "%{$search}%")->orWhere('p.isi', 'like', "%{$search}%")))
            ->select('p.id_pengumuman', 'p.id_periode', 'pe.tahun', 'p.judul', 'p.isi', 'p.tanggal_publikasi')
            ->orderByDesc('p.tanggal_publikasi')->paginate($validated['per_page'] ?? 10);

        return response()->json(['success' => true, 'message' => 'Pengumuman berhasil diambil.', 'data' => $data]);
    }
}
