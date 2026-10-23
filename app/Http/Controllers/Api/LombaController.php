<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lomba;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LombaController extends Controller
{
    // PB-03: Daftar lomba warga
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['Semua', 'Segera', 'Selesai']),
            ],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'Semua';

        // Mengikuti periode aktif seperti PB-02
        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        if (!$periode) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada periode kegiatan aktif',
                'data' => [],
            ]);
        }

        $query = Lomba::with([
                'kategori',
                'jadwal' => function ($query) {
                    $query->orderBy('tanggal')
                        ->orderBy('waktu_mulai');
                },
            ])
            ->where('id_periode', $periode->id_periode)
            ->where('status', '!=', 'Nonaktif');

        // Pencarian berdasarkan nama lomba
        if ($search !== null && $search !== '') {
            $query->where('nama_lomba', 'like', '%' . $search . '%');
        }

        // Filter status sesuai tampilan frontend
        
        if ($status === 'Segera') {
            $query->where('status', 'Dibuka')
        ->whereHas('jadwal', function ($q) {
            $q->whereDate('tanggal', '>=', now()->toDateString());
        });
}       elseif ($status === 'Selesai') {
            $query->where('status', 'Selesai');
}

        $lomba = $query
            ->orderBy('nama_lomba')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar lomba berhasil diambil',
            'data' => $lomba,
        ]);
    }

    // PB-03: Detail lomba warga
    public function show(int $id): JsonResponse
    {
        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        $lomba = $periode
            ? Lomba::with([
                'kategori',
                'detail',
                'jadwal' => function ($query) {
                    $query->orderBy('tanggal')
                        ->orderBy('waktu_mulai');
                },
            ])
                ->where('id_periode', $periode->id_periode)
                ->where('status', '!=', 'Nonaktif')
                ->where('id_lomba', $id)
                ->first()
            : null;

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak ditemukan',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail lomba berhasil diambil',
            'data' => $lomba,
        ]);
    }
}
