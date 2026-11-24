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
use OpenApi\Annotations as OA;

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
        ])->where('is_arsip', false);

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
                'is_arsip' => (bool) $item->is_arsip,
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

        // Statistik seluruh lomba aktif, tidak terpengaruh pencarian/filter.
        $semuaLomba = Lomba::where('is_arsip', false)->with('jadwal')->get();

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

    /**
     * @OA\Get(
     *     path="/api/lomba/manage/arsip",
     *     operationId="getDaftarLombaArsip",
     *     tags={"Manajemen Lomba"},
     *     summary="Mengambil daftar lomba arsip",
     *     description="Menampilkan hanya lomba yang sudah diarsipkan. Hanya dapat diakses oleh Admin dan Panitia. Mendukung pencarian dan pagination.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Pencarian berdasarkan nama lomba, kategori, atau lokasi",
     *         @OA\Schema(type="string", example="Futsal")
     *     ),
     *     @OA\Parameter(
     *         name="kategori",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan nama kategori",
     *         @OA\Schema(type="string", example="Umum")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Jumlah data per halaman (opsional, jika ingin menggunakan pagination)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Halaman pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar lomba arsip berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar lomba arsip berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id_lomba", type="integer", example=1),
     *                     @OA\Property(property="nama_lomba", type="string", example="Tarik Tambang"),
     *                     @OA\Property(property="jenis_pendaftaran", type="string", example="Beregu"),
     *                     @OA\Property(property="status", type="string", example="Dibuka"),
     *                     @OA\Property(property="status_lomba", type="string", example="Selesai"),
     *                     @OA\Property(property="kategori", type="string", example="Dewasa"),
     *                     @OA\Property(property="deskripsi", type="string", nullable=true),
     *                     @OA\Property(property="gambar", type="string", nullable=true),
     *                     @OA\Property(property="kapasitas_peserta", type="integer", nullable=true, example=8),
     *                     @OA\Property(property="batas_anggota_tim", type="integer", nullable=true, example=5),
     *                     @OA\Property(property="jumlah_pendaftar", type="integer", example=4),
     *                     @OA\Property(property="lokasi", type="string", nullable=true, example="Lapangan RW"),
     *                     @OA\Property(property="is_arsip", type="boolean", example=true),
     *                     @OA\Property(property="detail", type="object", nullable=true),
     *                     @OA\Property(property="jadwal", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin dan Panitia)")
     * )
     */
    public function arsipIndex(Request $request): JsonResponse
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        $query = Lomba::with([
            'kategori',
            'detail',
            'jadwal',
        ])->withCount([
            'pendaftaran as jumlah_pendaftar' => function ($query) {
                $query->whereNotIn('status', [
                    'Ditolak',
                    'Dibatalkan',
                ]);
            },
        ])->where('is_arsip', true);

        // Search nama lomba, kategori, dan lokasi.
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('nama_lomba', 'like', "%{$search}%")
                    ->orWhereHas('kategori', function ($kategori) use ($search) {
                        $kategori->where('nama_kategori', 'like', "%{$search}%");
                    })
                    ->orWhereHas('jadwal', function ($jadwal) use ($search) {
                        $jadwal->where('lokasi', 'like', "%{$search}%");
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

        $query->orderBy('updated_at', 'desc');

        $formatItem = function ($item) use ($today) {
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

            $lokasiList = $item->jadwal
                ->pluck('lokasi')
                ->filter()
                ->unique()
                ->values();

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
                'lokasi' => $lokasiList->implode(', ') ?: null,
                'is_arsip' => (bool) $item->is_arsip,
                'detail' => $item->detail,
                'jadwal' => $item->jadwal->map(function ($jadwal) {
                    return [
                        'id_jadwal' => $jadwal->id_jadwal,
                        'tanggal' => Carbon::parse($jadwal->tanggal)->toDateString(),
                        'waktu_mulai' => $jadwal->waktu_mulai,
                        'waktu_selesai' => $jadwal->waktu_selesai,
                        'lokasi' => $jadwal->lokasi,
                    ];
                })->values(),
            ];
        };

        if ($request->filled('per_page')) {
            $perPage = (int) $request->input('per_page', 15);
            $paginated = $query->paginate($perPage);
            $paginated->getCollection()->transform($formatItem);

            return response()->json([
                'success' => true,
                'message' => 'Daftar lomba arsip berhasil diambil.',
                'data' => $paginated,
            ]);
        }

        $lomba = $query->get()->map($formatItem);

        return response()->json([
            'success' => true,
            'message' => 'Daftar lomba arsip berhasil diambil.',
            'data' => $lomba->values(),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/lomba/manage/{id_lomba}/arsip",
     *     operationId="arsipLomba",
     *     tags={"Manajemen Lomba"},
     *     summary="Mengarsipkan lomba",
     *     description="Memindahkan lomba dari daftar aktif ke daftar arsip tanpa menghapus data pendaftaran, tim, jadwal, atau relasi lainnya. Hanya untuk Admin dan Panitia.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_lomba",
     *         in="path",
     *         required=true,
     *         description="ID Lomba yang akan diarsipkan",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lomba berhasil diarsipkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lomba berhasil diarsipkan."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_lomba", type="integer", example=1),
     *                 @OA\Property(property="nama_lomba", type="string", example="Tarik Tambang"),
     *                 @OA\Property(property="is_arsip", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin dan Panitia)"),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan"),
     *     @OA\Response(response=422, description="Lomba sudah berada dalam arsip")
     * )
     */
    public function arsip($id_lomba): JsonResponse
    {
        $lomba = Lomba::find($id_lomba);

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak ditemukan.',
            ], 404);
        }

        if ($lomba->is_arsip) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba sudah berada dalam arsip.',
            ], 422);
        }

        DB::transaction(function () use ($lomba) {
            $lomba->is_arsip = true;
            $lomba->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Lomba berhasil diarsipkan.',
            'data' => [
                'id_lomba' => $lomba->id_lomba,
                'nama_lomba' => $lomba->nama_lomba,
                'is_arsip' => (bool) $lomba->is_arsip,
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/lomba/manage/{id_lomba}/pulihkan",
     *     operationId="pulihkanLomba",
     *     tags={"Manajemen Lomba"},
     *     summary="Memulihkan lomba dari arsip",
     *     description="Mengembalikan lomba dari daftar arsip ke daftar lomba aktif dengan seluruh relasi data tetap utuh. Hanya untuk Admin dan Panitia.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_lomba",
     *         in="path",
     *         required=true,
     *         description="ID Lomba yang akan dipulihkan",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lomba berhasil dipulihkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lomba berhasil dipulihkan."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_lomba", type="integer", example=1),
     *                 @OA\Property(property="nama_lomba", type="string", example="Tarik Tambang"),
     *                 @OA\Property(property="is_arsip", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Hanya Admin dan Panitia)"),
     *     @OA\Response(response=404, description="Lomba tidak ditemukan"),
     *     @OA\Response(response=422, description="Lomba tidak berada dalam arsip atau sudah aktif")
     * )
     */
    public function pulihkan($id_lomba): JsonResponse
    {
        $lomba = Lomba::find($id_lomba);

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak ditemukan.',
            ], 404);
        }

        if (!$lomba->is_arsip) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak berada dalam arsip atau sudah aktif.',
            ], 422);
        }

        DB::transaction(function () use ($lomba) {
            $lomba->is_arsip = false;
            $lomba->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Lomba berhasil dipulihkan.',
            'data' => [
                'id_lomba' => $lomba->id_lomba,
                'nama_lomba' => $lomba->nama_lomba,
                'is_arsip' => (bool) $lomba->is_arsip,
            ],
        ]);
    }

}