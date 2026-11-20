<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lomba;
use App\Models\Periode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LombaManagementController extends Controller
{
    /**
     * GET: Daftar lomba untuk Admin dan Panitia.
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        $query = Lomba::with([
            'kategori',
            'jadwal',
        ])->withCount([
            'pendaftaran as jumlah_pendaftar' => function ($query) {
                $query->whereNotIn('status', [
                    'Ditolak',
                    'Dibatalkan',
                ]);
            },
        ]);

        // Search nama lomba, kategori, dan lokasi.
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('nama_lomba', 'like', "%{$search}%")
                    ->orWhereHas('kategori', function ($kategori) use ($search) {
                        $kategori->where(
                            'nama_kategori',
                            'like',
                            "%{$search}%"
                        );
                    })
                    ->orWhereHas('jadwal', function ($jadwal) use ($search) {
                        $jadwal->where(
                            'lokasi',
                            'like',
                            "%{$search}%"
                        );
                    });
            });
        }

        // Filter kategori.
        if ($request->filled('kategori')) {
            $kategori = $request->kategori;

            $query->whereHas('kategori', function ($q) use ($kategori) {
                $q->where('nama_kategori', 'like', "%{$kategori}%");
            });
        }

        $lomba = $query
            ->orderBy('created_at', 'desc')
            ->get();

        // Format data sekaligus menentukan status berdasarkan jadwal.
        $data = $lomba->map(function ($item) use ($today) {
            $tanggalJadwal = $item->jadwal
                ->map(function ($jadwal) {
                    return Carbon::parse($jadwal->tanggal)->toDateString();
                });

            if ($tanggalJadwal->contains($today)) {
                $statusLomba = 'Berlangsung';
            } elseif ($tanggalJadwal->contains(function ($tanggal) use ($today) {
                return $tanggal > $today;
            })) {
                $statusLomba = 'Mendatang';
            } elseif (
                $tanggalJadwal->isNotEmpty()
                && $tanggalJadwal->every(function ($tanggal) use ($today) {
                    return $tanggal < $today;
                })
            ) {
                $statusLomba = 'Selesai';
            } else {
                $statusLomba = 'Belum Dijadwalkan';
            }

            return [
                'id_lomba' => $item->id_lomba,
                'nama_lomba' => $item->nama_lomba,
                'jenis_pendaftaran' => $item->jenis_pendaftaran,
                'status' => $item->status,
                'status_lomba' => $statusLomba,
                'kategori' => $item->kategori?->nama_kategori,
                'deskripsi' => $item->deskripsi,
                'gambar' => $item->gambar,
                'kapasitas_peserta' => $item->kapasitas_peserta,
                'batas_anggota_tim' => $item->batas_anggota_tim,
                'jumlah_pendaftar' => $item->jumlah_pendaftar,
                'jadwal' => $item->jadwal->map(function ($jadwal) {
                    return [
                        'id_jadwal' => $jadwal->id_jadwal,
                        'tanggal' => Carbon::parse($jadwal->tanggal)
                            ->toDateString(),
                        'waktu_mulai' => $jadwal->waktu_mulai,
                        'waktu_selesai' => $jadwal->waktu_selesai,
                        'lokasi' => $jadwal->lokasi,
                    ];
                })->values(),
            ];
        });

        // Filter status berdasarkan hasil format.
        if ($request->filled('status_lomba')) {
            $data = $data->filter(function ($item) use ($request) {
                return $item['status_lomba'] === $request->status_lomba;
            });
        }

        // Statistik seluruh lomba, tidak terpengaruh pencarian/filter.
        $semuaLomba = Lomba::with('jadwal')->get();

        $total = $semuaLomba->count();
        $berlangsung = 0;
        $mendatang = 0;
        $selesai = 0;

        foreach ($semuaLomba as $item) {
            $tanggalJadwal = $item->jadwal
                ->map(function ($jadwal) {
                    return Carbon::parse($jadwal->tanggal)->toDateString();
                });

            if ($tanggalJadwal->contains($today)) {
                $berlangsung++;
            } elseif ($tanggalJadwal->contains(function ($tanggal) use ($today) {
                return $tanggal > $today;
            })) {
                $mendatang++;
            } elseif (
                $tanggalJadwal->isNotEmpty()
                && $tanggalJadwal->every(function ($tanggal) use ($today) {
                    return $tanggal < $today;
                })
            ) {
                $selesai++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data lomba berhasil diambil.',
            'statistik' => [
                'total_lomba' => $total,
                'lomba_berlangsung' => $berlangsung,
                'lomba_mendatang' => $mendatang,
                'lomba_selesai' => $selesai,
            ],
            'data' => $data->values(),
        ]);
    }

    /**
     * POST: Tambah lomba baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_kategori' => [
                'required',
                'integer',
                'exists:kategori_lomba,id_kategori',
            ],

            'nama_lomba' => [
                'required',
                'string',
                'max:100',
            ],

            'jenis_pendaftaran' => [
                'required',
                Rule::in(['Individu', 'Beregu']),
            ],

            'deskripsi' => [
                'nullable',
                'string',
            ],

            'gambar' => [
                'nullable',
                'string',
                'max:255',
            ],

            'kapasitas_peserta' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'batas_anggota_tim' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'detail' => [
                'nullable',
                'array',
            ],

            'detail.deskripsi' => [
                'nullable',
                'string',
            ],

            'detail.alur' => [
                'nullable',
                'string',
            ],

            'detail.syarat' => [
                'nullable',
                'string',
            ],

            'detail.peraturan' => [
                'nullable',
                'string',
            ],

            'jadwal' => [
                'required',
                'array',
                'min:1',
            ],

            'jadwal.*.tanggal' => [
                'required',
                'date',
            ],

            'jadwal.*.waktu_mulai' => [
                'nullable',
                'date_format:H:i',
            ],

            'jadwal.*.waktu_selesai' => [
                'nullable',
                'date_format:H:i',
                'after:jadwal.*.waktu_mulai',
            ],

            'jadwal.*.lokasi' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        // Validasi khusus lomba beregu.
        if ($validated['jenis_pendaftaran'] === 'Beregu') {
            if (empty($validated['batas_anggota_tim'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batas anggota tim wajib diisi untuk lomba beregu.',
                ], 422);
            }
        }

        // Ambil periode yang sedang aktif.
        $periodeAktif = Periode::where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        if (!$periodeAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada periode HUT RI yang aktif. Lomba tidak dapat dibuat.',
            ], 422);
        }

        // Simpan lomba, detail, dan jadwal dalam satu transaksi.
        $lomba = DB::transaction(function () use ($validated, $periodeAktif) {
            $lomba = Lomba::create([
                'id_periode' => $periodeAktif->id_periode,
                'id_kategori' => $validated['id_kategori'],
                'nama_lomba' => $validated['nama_lomba'],
                'jenis_pendaftaran' => $validated['jenis_pendaftaran'],
                'deskripsi' => $validated['deskripsi'] ?? null,
                'gambar' => $validated['gambar'] ?? null,
                'kapasitas_peserta' => $validated['kapasitas_peserta'] ?? null,
                'batas_anggota_tim' => $validated['batas_anggota_tim'] ?? null,
                'status' => 'Dibuka',
            ]);

            // Simpan detail lomba jika ada.
            if (!empty($validated['detail'])) {
                $lomba->detail()->create([
                    'deskripsi' => $validated['detail']['deskripsi'] ?? null,
                    'alur' => $validated['detail']['alur'] ?? null,
                    'syarat' => $validated['detail']['syarat'] ?? null,
                    'peraturan' => $validated['detail']['peraturan'] ?? null,
                ]);
            }

            // Simpan seluruh jadwal lomba.
            foreach ($validated['jadwal'] as $jadwal) {
                $lomba->jadwal()->create([
                    'tanggal' => $jadwal['tanggal'],
                    'waktu_mulai' => $jadwal['waktu_mulai'] ?? null,
                    'waktu_selesai' => $jadwal['waktu_selesai'] ?? null,
                    'lokasi' => $jadwal['lokasi'] ?? null,
                ]);
            }

            return $lomba;
        });

        $lomba->load([
            'kategori',
            'detail',
            'jadwal',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lomba berhasil ditambahkan.',
            'data' => [
                'id_lomba' => $lomba->id_lomba,
                'id_periode' => $lomba->id_periode,
                'nama_lomba' => $lomba->nama_lomba,
                'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
                'status' => $lomba->status,
                'kategori' => $lomba->kategori?->nama_kategori,
                'deskripsi' => $lomba->deskripsi,
                'gambar' => $lomba->gambar,
                'kapasitas_peserta' => $lomba->kapasitas_peserta,
                'batas_anggota_tim' => $lomba->batas_anggota_tim,
                'detail' => $lomba->detail,
                'jadwal' => $lomba->jadwal,
            ],
        ], 201);
    }


/**
 * PUT: Edit data lomba.
 */
public function update(Request $request, $id_lomba): JsonResponse
{
    $lomba = Lomba::with(['detail', 'jadwal'])
        ->find($id_lomba);

    if (!$lomba) {
        return response()->json([
            'success' => false,
            'message' => 'Lomba tidak ditemukan.',
        ], 404);
    }

    $validated = $request->validate([
        'id_kategori' => [
            'sometimes',
            'required',
            'integer',
            'exists:kategori_lomba,id_kategori',
        ],

        'nama_lomba' => [
            'sometimes',
            'required',
            'string',
            'max:100',
        ],

        'jenis_pendaftaran' => [
            'sometimes',
            'required',
            Rule::in(['Individu', 'Beregu']),
        ],

        'deskripsi' => [
            'sometimes',
            'nullable',
            'string',
        ],

        'gambar' => [
            'sometimes',
            'nullable',
            'string',
            'max:255',
        ],

        'kapasitas_peserta' => [
            'sometimes',
            'nullable',
            'integer',
            'min:1',
        ],

        'batas_anggota_tim' => [
            'sometimes',
            'nullable',
            'integer',
            'min:1',
        ],

        'detail' => [
            'sometimes',
            'nullable',
            'array',
        ],

        'detail.deskripsi' => [
            'sometimes',
            'nullable',
            'string',
        ],

        'detail.alur' => [
            'sometimes',
            'nullable',
            'string',
        ],

        'detail.syarat' => [
            'sometimes',
            'nullable',
            'string',
        ],

        'detail.peraturan' => [
            'sometimes',
            'nullable',
            'string',
        ],

        'jadwal' => [
            'sometimes',
            'required',
            'array',
            'min:1',
        ],

        'jadwal.*.tanggal' => [
            'required_with:jadwal',
            'date',
        ],

        'jadwal.*.waktu_mulai' => [
            'nullable',
            'date_format:H:i',
        ],

        'jadwal.*.waktu_selesai' => [
            'nullable',
            'date_format:H:i',
            'after:jadwal.*.waktu_mulai',
        ],

        'jadwal.*.lokasi' => [
            'nullable',
            'string',
            'max:150',
        ],
    ]);

    // Validasi jenis pendaftaran setelah perubahan.
    $jenisPendaftaran = $validated['jenis_pendaftaran']
        ?? $lomba->jenis_pendaftaran;

    $batasAnggotaTim = array_key_exists(
        'batas_anggota_tim',
        $validated
    )
        ? $validated['batas_anggota_tim']
        : $lomba->batas_anggota_tim;

    if (
        $jenisPendaftaran === 'Beregu'
        && empty($batasAnggotaTim)
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Batas anggota tim wajib diisi untuk lomba beregu.',
        ], 422);
    }

    DB::transaction(function () use ($lomba, $validated) {
        // Update data utama lomba hanya field yang dikirim.
        $fieldLomba = [
            'id_kategori',
            'nama_lomba',
            'jenis_pendaftaran',
            'deskripsi',
            'gambar',
            'kapasitas_peserta',
            'batas_anggota_tim',
        ];

        foreach ($fieldLomba as $field) {
            if (array_key_exists($field, $validated)) {
                $lomba->{$field} = $validated[$field];
            }
        }

        $lomba->save();

        // Update atau buat detail lomba jika detail dikirim.
        if (array_key_exists('detail', $validated)) {
            $detailInput = $validated['detail'] ?? [];
            $existingDetail = $lomba->detail;

            $lomba->detail()->updateOrCreate(
                ['id_lomba' => $lomba->id_lomba],
                [
                    'deskripsi' => array_key_exists('deskripsi', $detailInput)
                        ? $detailInput['deskripsi']
                        : $existingDetail?->deskripsi,
                    'alur' => array_key_exists('alur', $detailInput)
                        ? $detailInput['alur']
                        : $existingDetail?->alur,
                    'syarat' => array_key_exists('syarat', $detailInput)
                        ? $detailInput['syarat']
                        : $existingDetail?->syarat,
                    'peraturan' => array_key_exists('peraturan', $detailInput)
                        ? $detailInput['peraturan']
                        : $existingDetail?->peraturan,
                ]
            );
        }

        // Jadwal diperbarui hanya jika key jadwal dikirim.
        // Jadwal lama akan diganti dengan jadwal baru.
        if (array_key_exists('jadwal', $validated)) {
            $lomba->jadwal()->delete();

            foreach ($validated['jadwal'] as $jadwal) {
                $lomba->jadwal()->create([
                    'tanggal' => $jadwal['tanggal'],
                    'waktu_mulai' => $jadwal['waktu_mulai'] ?? null,
                    'waktu_selesai' => $jadwal['waktu_selesai'] ?? null,
                    'lokasi' => $jadwal['lokasi'] ?? null,
                ]);
            }
        }
    });

    $lomba->refresh()->load([
        'kategori',
        'detail',
        'jadwal',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Lomba berhasil diperbarui.',
        'data' => [
            'id_lomba' => $lomba->id_lomba,
            'id_periode' => $lomba->id_periode,
            'nama_lomba' => $lomba->nama_lomba,
            'jenis_pendaftaran' => $lomba->jenis_pendaftaran,
            'status' => $lomba->status,
            'kategori' => $lomba->kategori?->nama_kategori,
            'deskripsi' => $lomba->deskripsi,
            'gambar' => $lomba->gambar,
            'kapasitas_peserta' => $lomba->kapasitas_peserta,
            'batas_anggota_tim' => $lomba->batas_anggota_tim,
            'detail' => $lomba->detail,
            'jadwal' => $lomba->jadwal,
          ],
        ]);
    }

    
/**
 * DELETE: Hapus lomba.
 */
public function destroy($id_lomba): JsonResponse
{
    $lomba = Lomba::find($id_lomba);

    if (!$lomba) {
        return response()->json([
            'success' => false,
            'message' => 'Lomba tidak ditemukan.',
        ], 404);
    }

    // Cegah penghapusan lomba yang sudah memiliki data terkait.
    if ($lomba->pendaftaran()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Lomba tidak dapat dihapus karena sudah memiliki data pendaftaran.',
        ], 409);
    }

    if ($lomba->pemenang()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Lomba tidak dapat dihapus karena sudah memiliki data pemenang.',
        ], 409);
    }

    if ($lomba->pertandingan()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Lomba tidak dapat dihapus karena sudah memiliki data pertandingan.',
        ], 409);
    }

    DB::transaction(function () use ($lomba) {
        // Hapus jadwal dan detail terlebih dahulu.
        $lomba->jadwal()->delete();
        $lomba->detail()->delete();

        // Hapus data lomba.
        $lomba->delete();
    });

    return response()->json([
        'success' => true,
        'message' => 'Lomba berhasil dihapus.',
        ]);
    }

}