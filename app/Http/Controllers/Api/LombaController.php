<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lomba;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LombaController extends Controller
{
    /**
     * Daftar lomba untuk Warga.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['Semua', 'Segera', 'Selesai']),
            ],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? 'Semua';
        $tanggal = $validated['tanggal'] ?? null;

        // Ambil periode aktif
        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        if (!$periode) {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada periode kegiatan aktif.',
                'data' => [],
                'tanggal_tersedia' => [],
            ]);
        }

        // Query lomba
        $query = Lomba::with([
            'kategori',
            'jadwal' => function ($query) {
                $query->orderBy('tanggal')
                    ->orderBy('waktu_mulai');
            },
        ])
            ->where('id_periode', $periode->id_periode)
            ->where('status', '!=', 'Nonaktif');

        // Pencarian nama lomba
        if ($search) {
            $query->where('nama_lomba', 'like', "%{$search}%");
        }

        // Filter Segera
        if ($status === 'Segera') {
            $query->where('status', 'Dibuka')
                ->whereHas('jadwal', function ($q) {
                    $q->where(function ($jadwalQuery) {
                        $jadwalQuery
                            ->whereDate('tanggal', '>', now()->toDateString())
                            ->orWhere(function ($todayQuery) {
                                $todayQuery
                                    ->whereDate('tanggal', now()->toDateString())
                                    ->where(function ($timeQuery) {
                                        $timeQuery
                                            ->whereNull('waktu_mulai')
                                            ->orWhereTime(
                                                'waktu_mulai',
                                                '>=',
                                                now()->format('H:i:s')
                                            );
                                    });
                            });
                    });
                });
        }

        // Filter Selesai
        if ($status === 'Selesai') {
            $query->where('status', 'Selesai');
        }

        $lomba = $query->orderBy('nama_lomba')->get();

        // ID Warga yang sedang login
        $idWarga = $request->user()->id_warga ?? null;

        // Format response lomba
        $data = $lomba->map(function ($item) use ($idWarga) {
            return $this->formatLomba($item, $idWarga);
        });

        // Ambil tanggal unik untuk tab tanggal
        $tanggalTersedia = $lomba
            ->flatMap(function ($item) {
                return $item->jadwal->pluck('tanggal');
            })
            ->map(function ($tanggal) {
                return Carbon::parse($tanggal)->format('Y-m-d');
            })
            ->unique()
            ->sort()
            ->values();

        // Filter berdasarkan tanggal yang dipilih
        if ($tanggal) {
            $data = $data->filter(function ($item) use ($tanggal) {
                return collect($item['jadwal'])
                    ->contains('tanggal', $tanggal);
            })->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar lomba berhasil ditampilkan.',
            'data' => $data,
            'tanggal_tersedia' => $tanggalTersedia,
        ]);
    }

    /**
     * Detail lomba untuk Warga.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $periode = DB::table('periode')
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        if (!$periode) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada periode kegiatan aktif.',
                'data' => null,
            ], 404);
        }

        $lomba = Lomba::with([
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
            ->first();

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $idWarga = $request->user()->id_warga ?? null;

        $data = $this->formatLomba($lomba, $idWarga, true);

        return response()->json([
            'success' => true,
            'message' => 'Detail lomba berhasil ditampilkan.',
            'data' => $data,
        ]);
    }

    /**
     * Format data lomba agar sesuai kebutuhan frontend.
     */
    private function formatLomba(
        Lomba $lomba,
        ?int $idWarga,
        bool $includeDetail = false
    ): array {
        $jadwal = $lomba->jadwal->map(function ($item) {
            $tanggal = Carbon::parse($item->tanggal);

            // Tentukan batas waktu status jadwal
            if ($item->waktu_selesai) {
                $batasWaktu = Carbon::parse(
                    $tanggal->format('Y-m-d') . ' ' . $item->waktu_selesai
                );
            } elseif ($item->waktu_mulai) {
                $batasWaktu = Carbon::parse(
                    $tanggal->format('Y-m-d') . ' ' . $item->waktu_mulai
                );
            } else {
                $batasWaktu = $tanggal->copy()->endOfDay();
            }

            return [
                'id_jadwal' => $item->id_jadwal,
                'tanggal' => $tanggal->format('Y-m-d'),
                'waktu_mulai' => $item->waktu_mulai,
                'waktu_selesai' => $item->waktu_selesai,
                'lokasi' => $item->lokasi,
                'status_jadwal' => now()->lte($batasWaktu)
                    ? 'Segera'
                    : 'Selesai',
            ];
        });

        // Periksa apakah Warga sudah pernah mendaftar
        $sudahMendaftar = false;

        if ($idWarga) {
            $sudahMendaftar = $lomba->pendaftaran()
                ->where('id_warga', $idWarga)
                ->exists();
        }

        $data = [
            'id_lomba' => $lomba->id_lomba,
            'nama_lomba' => $lomba->nama_lomba,
            'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
            'status' => $lomba->status,
            'deskripsi' => $lomba->deskripsi,

            'kategori' => $lomba->kategori ? [
                'id_kategori' => $lomba->kategori->id_kategori,
                'nama_kategori' => $lomba->kategori->nama_kategori,
            ] : null,

            'kapasitas_peserta' => $lomba->kapasitas_peserta,
            'batas_anggota_tim' => $lomba->batas_anggota_tim,
            'sudah_mendaftar' => $sudahMendaftar,

            'jadwal' => $jadwal->values(),
        ];

        // Informasi tambahan pada halaman detail
        if ($includeDetail) {
            $data['detail'] = $lomba->detail ? [
                'deskripsi' => $lomba->detail->deskripsi,
                'alur' => $lomba->detail->alur,
                'syarat' => $lomba->detail->syarat,
                'peraturan' => $lomba->detail->peraturan,
            ] : null;
        }

        return $data;
    }
}
