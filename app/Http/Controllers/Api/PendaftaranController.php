<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimLomba;
use App\Models\AnggotaPendaftaran;
use App\Models\Lomba;
use App\Models\Notifikasi;
use App\Models\Pendaftaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PendaftaranController extends Controller
{


    /**
     * PB-04: Mengambil riwayat pendaftaran milik warga yang sedang login (Individu & Beregu).
     *
     * @OA\Get(
     *     path="/api/warga/pendaftaran",
     *     operationId="getRiwayatPendaftaranWarga",
     *     tags={"Warga - Pendaftaran"},
     *     summary="Melihat riwayat pendaftaran lomba",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Riwayat pendaftaran berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Belum login"),
     *     @OA\Response(response=403, description="Akun tidak terhubung dengan warga")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $pendaftaran = Pendaftaran::with([
            'lomba' => function ($query) {
                $query->with([
                    'kategori',
                    'jadwal' => function ($q) {
                        $q->orderBy('tanggal')->orderBy('waktu_mulai');
                    },
                ]);
            },
            'anggota',
        ])
            ->where('id_warga', $idWarga)
            ->orderByDesc('tanggal_daftar')
            ->orderByDesc('id_pendaftaran')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar riwayat pendaftaran lomba berhasil diambil.',
            'data' => $pendaftaran,
        ]);
    }


    
    /**
     * PB-04: Mendaftar lomba (Mendukung Individu dan Regu/Tim berdasarkan data lomba di DB).
     *
     * @OA\Post(
     *     path="/api/warga/pendaftaran",
     *     operationId="buatPendaftaranLombaWarga",
     *     tags={"Warga - Pendaftaran"},
     *     summary="Mendaftarkan warga ke perlombaan (Individu atau Beregu)",
     *     description="Mendaftar ke lomba yang berstatus Dibuka. Untuk lomba individu (Catur, Makan Kerupuk, Mewarnai), cukup mengirim 'id_lomba'. Untuk lomba beregu (Tarik Tambang, Futsal Sarung), wajib menyertakan 'nama_tim' dan array 'anggota' minimal 1 orang.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data pendaftaran lomba",
     *         @OA\JsonContent(
     *             required={"id_lomba"},
     *             @OA\Property(property="id_lomba", type="integer", example=1, description="ID lomba yang ingin didaftarkan"),
     *             @OA\Property(property="nama_tim", type="string", maxLength=100, nullable=true, example="Garuda Muda", description="Wajib untuk lomba beregu, tidak boleh diisi untuk lomba individu"),
     *             @OA\Property(
     *                 property="anggota",
     *                 type="array",
     *                 nullable=true,
     *                 description="Daftar anggota tim (wajib untuk lomba beregu, minimal 1 anggota)",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"nama","no_hp"},
     *                     @OA\Property(property="nama", type="string", maxLength=100, example="Ahmad Dahlan"),
     *                     @OA\Property(property="no_hp", type="string", maxLength=20, example="081234567891")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pendaftaran lomba berhasil diajukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pendaftaran lomba berhasil diajukan."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Akses ditolak (Bukan warga atau akun belum terhubung data warga)"),
     *     @OA\Response(response=409, description="Conflict - Anda sudah terdaftar pada perlombaan ini"),
     *     @OA\Response(response=422, description="Unprocessable Entity - Validasi gagal, lomba tidak ditemukan, lomba ditutup, atau format data tim tidak sesuai")
     * )
     */
    
    public function store(Request $request): JsonResponse
    {
        $akun = $request->user();
        $idWarga = $akun->id_warga;

        if (!$idWarga) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung dengan data warga.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_lomba' => ['required', 'integer', 'exists:lomba,id_lomba'],
        ], [
            'id_lomba.required' => 'ID lomba wajib diisi.',
            'id_lomba.integer' => 'ID lomba harus berupa angka.',
            'id_lomba.exists' => 'Lomba tidak ditemukan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $hasil = DB::transaction(function () use ($request, $idWarga) {

                // Kunci data lomba agar kuota tidak terlewati
                // jika beberapa warga mendaftar bersamaan.
                $lomba = Lomba::where('id_lomba', $request->id_lomba)
                    ->lockForUpdate()
                    ->first();

                if (!$lomba || $lomba->status !== 'Dibuka') {
                    return [
                        'error' => 'Lomba tidak ditemukan atau pendaftarannya sudah ditutup.',
                        'http_status' => 422,
                    ];
                }

                $isBeregu = $lomba->isBeregu()
                    || strtolower(trim($lomba->jenis_pendaftaran ?? '')) === 'beregu';

                // Warga tidak boleh mendaftar dua kali pada lomba yang sama.
                $sudahTerdaftar = Pendaftaran::where('id_warga', $idWarga)
                    ->where('id_lomba', $lomba->id_lomba)
                    ->exists();

                if ($sudahTerdaftar) {
                    return [
                        'error' => 'Anda sudah terdaftar pada perlombaan ini.',
                        'http_status' => 409,
                    ];
                }

                // ==========================================
                // PENDAFTARAN LOMBA INDIVIDU
                // ==========================================
                if (!$isBeregu) {

                    $jumlahPendaftar = Pendaftaran::where(
                        'id_lomba',
                        $lomba->id_lomba
                    )
                        ->whereIn('status', ['Menunggu', 'Diterima'])
                        ->count();

                    if (
                        $lomba->kapasitas_peserta !== null &&
                        $jumlahPendaftar >= $lomba->kapasitas_peserta
                    ) {
                        return [
                            'error' => 'Mohon maaf, kuota peserta lomba sudah penuh.',
                            'http_status' => 422,
                        ];
                    }

                    $pendaftaran = Pendaftaran::create([
                        'id_warga' => $idWarga,
                        'id_lomba' => $lomba->id_lomba,
                        'id_tim' => null,
                        'nama_tim' => null,
                        'tanggal_daftar' => now(),
                        'status' => 'Menunggu',
                        'catatan' => null,
                    ]);

                    Notifikasi::create([
                        'id_warga' => $idWarga,
                        'judul' => 'Pendaftaran Lomba Berhasil Diajukan',
                        'pesan' => "Pendaftaran Anda untuk lomba '{$lomba->nama_lomba}' berhasil diajukan dan sedang menunggu verifikasi panitia.",
                        'tipe' => 'Lomba',
                        'status_baca' => false,
                    ]);

                    $jumlahPendaftar++;

                    return [
                        'pendaftaran' => $pendaftaran,
                        'is_beregu' => false,
                        'kuota' => [
                            'jumlah_pendaftar' => $jumlahPendaftar,
                            'kapasitas_peserta' => $lomba->kapasitas_peserta,
                            'sisa_kapasitas' => $lomba->kapasitas_peserta !== null
                                ? max(0, $lomba->kapasitas_peserta - $jumlahPendaftar)
                                : null,
                            'kuota_penuh' => $lomba->kapasitas_peserta !== null
                                ? $jumlahPendaftar >= $lomba->kapasitas_peserta
                                : false,
                            'pendaftaran_dibuka' => $lomba->kapasitas_peserta === null
                                || $jumlahPendaftar < $lomba->kapasitas_peserta,
                        ],
                    ];
                }

                // ==========================================
                // PENDAFTARAN LOMBA BEREGU
                // ==========================================

                $kapasitasTim = (int) $lomba->kapasitas_peserta;
                $batasAnggota = (int) $lomba->batas_anggota_tim;

                // Kapasitas dan batas anggota wajib diatur Admin/Panitia.
                if ($kapasitasTim <= 0 || $batasAnggota <= 0) {
                    return [
                        'error' => 'Kapasitas tim dan batas anggota tim belum diatur. Silakan hubungi panitia.',
                        'http_status' => 422,
                    ];
                }

                // Pendaftaran beregu baru dilakukan per orang.
                // Tidak lagi menggunakan nama_tim atau anggota[] dari warga.
                if (
                    $request->filled('nama_tim') ||
                    ($request->has('anggota') && !empty($request->input('anggota')))
                ) {
                    return [
                        'error' => 'Pendaftaran beregu dilakukan secara individu. Cukup kirim id_lomba tanpa nama_tim dan anggota.',
                        'http_status' => 422,
                    ];
                }

                // Tim lama yang belum dipindahkan ke tabel tim_lomba
                // tetap dihitung sebagai tim yang memakai kapasitas.
                $jumlahTimLama = Pendaftaran::where(
                    'id_lomba',
                    $lomba->id_lomba
                )
                    ->whereIn('status', ['Menunggu', 'Diterima'])
                    ->whereNotNull('nama_tim')
                    ->whereNull('id_tim')
                    ->count();

                // Tim yang sudah dibentuk otomatis.
                $jumlahTimOtomatis = TimLomba::where(
                    'id_lomba',
                    $lomba->id_lomba
                )->count();

                // Peserta baru yang belum mendapatkan tim.
                $jumlahMenungguTim = Pendaftaran::where(
                    'id_lomba',
                    $lomba->id_lomba
                )
                    ->whereIn('status', ['Menunggu', 'Diterima'])
                    ->whereNull('id_tim')
                    ->whereNull('nama_tim')
                    ->count();

                // Periksa apakah peserta baru masih bisa ditampung.
                // Sisa peserta harus bisa membentuk tim dalam kapasitas yang tersedia.
                $slotTimDibutuhkan = (int) ceil(
                    ($jumlahMenungguTim + 1) / $batasAnggota
                );

                if (
                    $jumlahTimLama +
                    $jumlahTimOtomatis +
                    $slotTimDibutuhkan > $kapasitasTim
                ) {
                    return [
                        'error' => 'Mohon maaf, kapasitas seluruh tim untuk lomba ini sudah penuh.',
                        'http_status' => 422,
                    ];
                }

                // Simpan satu pendaftaran untuk satu warga.
                $pendaftaran = Pendaftaran::create([
                    'id_warga' => $idWarga,
                    'id_lomba' => $lomba->id_lomba,
                    'id_tim' => null,
                    'nama_tim' => null,
                    'tanggal_daftar' => now(),
                    'status' => 'Menunggu',
                    'catatan' => null,
                ]);

                // Ambil seluruh peserta aktif yang belum memiliki tim.
                $pesertaMenunggu = Pendaftaran::where(
                    'id_lomba',
                    $lomba->id_lomba
                )
                    ->whereIn('status', ['Menunggu', 'Diterima'])
                    ->whereNull('id_tim')
                    ->whereNull('nama_tim')
                    ->orderBy('tanggal_daftar')
                    ->orderBy('id_pendaftaran')
                    ->get()
                    ->all();

                // Bentuk tim setiap kali jumlah peserta mencukupi.
                $jumlahTimTerbentuk = 0;

                while (
                    count($pesertaMenunggu) >= $batasAnggota &&
                    ($jumlahTimLama + $jumlahTimOtomatis + $jumlahTimTerbentuk)
                        < $kapasitasTim
                ) {
                    // Acak urutan peserta.
                    shuffle($pesertaMenunggu);

                    // Ambil peserta sesuai batas anggota per tim.
                    $anggotaTim = array_splice(
                        $pesertaMenunggu,
                        0,
                        $batasAnggota
                    );

                    $nomorTim = $jumlahTimLama
                        + $jumlahTimOtomatis
                        + $jumlahTimTerbentuk
                        + 1;

                    // Buat tim baru.
                    $tim = TimLomba::create([
                        'id_lomba' => $lomba->id_lomba,
                        'nama_tim' => 'Tim ' . $nomorTim,
                    ]);

                    // Hubungkan setiap peserta dengan tim yang terbentuk.
                    foreach ($anggotaTim as $peserta) {
                        $peserta->update([
                            'id_tim' => $tim->id_tim,
                        ]);
                    }

                    $jumlahTimTerbentuk++;
                }

                // Notifikasi pendaftaran.
                Notifikasi::create([
                    'id_warga' => $idWarga,
                    'judul' => 'Pendaftaran Lomba Berhasil Diajukan',
                    'pesan' => "Pendaftaran Anda untuk lomba '{$lomba->nama_lomba}' berhasil diajukan. Sistem akan mengacak dan membentuk tim ketika jumlah peserta mencukupi.",
                    'tipe' => 'Lomba',
                    'status_baca' => false,
                ]);

                // Hitung kembali kondisi kuota setelah pembentukan tim.
                $jumlahTim = $jumlahTimLama
                    + $jumlahTimOtomatis
                    + $jumlahTimTerbentuk;

                $jumlahMenungguTim = count($pesertaMenunggu);

                $jumlahPeserta = Pendaftaran::where(
                    'id_lomba',
                    $lomba->id_lomba
                )
                    ->whereIn('status', ['Menunggu', 'Diterima'])
                    ->count()
                    + AnggotaPendaftaran::whereHas('pendaftaran', function ($query) use ($lomba) {
                        $query->where('id_lomba', $lomba->id_lomba)
                            ->whereIn('status', ['Menunggu', 'Diterima'])
                            ->whereNotNull('nama_tim')
                            ->whereNull('id_tim');
                    })->count();

                $sisaKapasitas = max(
                    0,
                    (($kapasitasTim - $jumlahTim) * $batasAnggota)
                        - $jumlahMenungguTim
                );

                return [
                    'pendaftaran' => $pendaftaran,
                    'is_beregu' => true,
                    'kuota' => [
                        'jumlah_peserta' => $jumlahPeserta,
                        'jumlah_tim' => $jumlahTim,
                        'kapasitas_tim' => $kapasitasTim,
                        'batas_anggota_tim' => $batasAnggota,
                        'peserta_menunggu_tim' => $jumlahMenungguTim,
                        'sisa_kapasitas' => $sisaKapasitas,
                        'kuota_penuh' => $sisaKapasitas <= 0,
                        'pendaftaran_dibuka' => $sisaKapasitas > 0,
                    ],
                ];
            });

            if (isset($hasil['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $hasil['error'],
                ], $hasil['http_status']);
            }

            $pendaftaran = $hasil['pendaftaran'];

            // Muat data lomba, kategori, jadwal, dan tim.
            $pendaftaran->load([
                'lomba.kategori',
                'lomba.jadwal',
                'anggota',
                'tim.pendaftaran.warga',
            ]);

            return response()->json([
                'success' => true,
                'message' => $hasil['is_beregu']
                    ? 'Pendaftaran berhasil. Sistem akan membentuk tim secara otomatis ketika jumlah peserta mencukupi.'
                    : 'Pendaftaran lomba berhasil diajukan.',
                'data' => $pendaftaran,
                'kuota' => $hasil['kuota'],
            ], 201);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses pendaftaran lomba.',
            ], 500);
        }
    }

}
