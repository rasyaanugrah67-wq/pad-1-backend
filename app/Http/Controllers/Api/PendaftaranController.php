<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // 1. Validasi awal: id_lomba wajib integer
        $initialValidator = Validator::make($request->all(), [
            'id_lomba' => ['required', 'integer'],
        ], [
            'id_lomba.required' => 'ID lomba wajib diisi.',
            'id_lomba.integer' => 'ID lomba harus berupa angka.',
        ]);

        if ($initialValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $initialValidator->errors()->first(),
                'errors' => $initialValidator->errors(),
            ], 422);
        }

        // 2. Ambil data lomba dari database dan periksa status
        $lomba = Lomba::with('kategori')->find($request->id_lomba);

        if (!$lomba) {
            return response()->json([
                'success' => false,
                'message' => 'Lomba tidak ditemukan.',
            ], 422);
        }

        if ($lomba->status !== 'Dibuka') {
            return response()->json([
                'success' => false,
                'message' => "Pendaftaran tidak dapat dilakukan karena status lomba saat ini '{$lomba->status}'.",
            ], 422);
        }

        // 3. Tentukan jenis pendaftaran berdasarkan data lomba di database
        $isBeregu = $lomba->isBeregu()
            || (strtolower($lomba->jenis_pendaftaran ?? '') === 'beregu')
            || in_array($lomba->nama_lomba, ['Tarik Tambang', 'Futsal Sarung'], true);

        // 4. Validasi berdasarkan jenis pendaftaran lomba
        if ($isBeregu) {
            // Lomba beregu WAJIB menyertakan nama_tim dan daftar anggota
            $teamValidator = Validator::make($request->all(), [
                'nama_tim' => ['required', 'string', 'min:2', 'max:100'],
                'anggota' => ['required', 'array', 'min:1'],
                'anggota.*.nama' => ['required', 'string', 'max:100'],
                'anggota.*.no_hp' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^(\+62|62|0)[0-9\-\s]{7,18}$/',
                ],
            ], [
                'nama_tim.required' => 'Nama tim wajib diisi untuk lomba beregu.',
                'nama_tim.string' => 'Nama tim harus berupa teks.',
                'nama_tim.max' => 'Nama tim maksimal 100 karakter.',
                'anggota.required' => 'Daftar anggota tim wajib diisi untuk lomba beregu.',
                'anggota.array' => 'Daftar anggota harus berupa array.',
                'anggota.min' => 'Daftar anggota tim minimal memiliki 1 anggota.',
                'anggota.*.nama.required' => 'Nama setiap anggota tim wajib diisi.',
                'anggota.*.nama.string' => 'Nama anggota harus berupa teks.',
                'anggota.*.nama.max' => 'Nama anggota maksimal 100 karakter.',
                'anggota.*.no_hp.required' => 'Nomor HP setiap anggota tim wajib diisi.',
                'anggota.*.no_hp.regex' => 'Format nomor HP anggota tidak valid (contoh: 081234567890).',
            ]);

            if ($teamValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $teamValidator->errors()->first(),
                    'errors' => $teamValidator->errors(),
                ], 422);
            }
        } else {
            // Lomba individu CUKUP id_lomba. Tolak jika mengirim field nama_tim atau anggota
            $hasNamaTim = $request->filled('nama_tim');
            $hasAnggota = $request->has('anggota') && !empty($request->input('anggota'));

            if ($hasNamaTim || $hasAnggota) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lomba ini adalah lomba individu, tidak menerima data nama tim atau anggota.',
                ], 422);
            }
        }

        // 5. Cegah perwakilan mendaftar dua kali untuk lomba yang sama
        $sudahTerdaftar = Pendaftaran::where('id_warga', $idWarga)
            ->where('id_lomba', $lomba->id_lomba)
            ->exists();

        if ($sudahTerdaftar) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah terdaftar pada perlombaan ini.',
            ], 409);
        }

        // 6. Simpan pendaftaran, anggota tim, dan notifikasi dalam satu DB Transaction
        $namaTim = $isBeregu ? trim($request->input('nama_tim')) : null;
        $anggotaList = $isBeregu ? $request->input('anggota', []) : [];

        $pendaftaran = DB::transaction(function () use ($idWarga, $lomba, $isBeregu, $namaTim, $anggotaList) {
            $p = Pendaftaran::create([
                'id_warga' => $idWarga,
                'id_lomba' => $lomba->id_lomba,
                'nama_tim' => $namaTim,
                'tanggal_daftar' => now(),
                'status' => 'Menunggu',
                'catatan' => null,
            ]);

            if ($isBeregu) {
                foreach ($anggotaList as $item) {
                    AnggotaPendaftaran::create([
                        'id_pendaftaran' => $p->id_pendaftaran,
                        'nama' => trim($item['nama']),
                        'no_hp' => trim($item['no_hp']),
                    ]);
                }
            }

            // Notifikasi pendaftaran
            $pesanNotif = $isBeregu
                ? "Pendaftaran tim '{$namaTim}' untuk lomba '{$lomba->nama_lomba}' telah diajukan dan sedang menunggu verifikasi panitia."
                : "Pendaftaran Anda untuk lomba '{$lomba->nama_lomba}' telah diajukan dan sedang menunggu verifikasi panitia.";

            Notifikasi::create([
                'id_warga' => $idWarga,
                'judul' => 'Pendaftaran Lomba Berhasil Diajukan',
                'pesan' => $pesanNotif,
                'tipe' => 'Lomba',
                'status_baca' => false,
            ]);

            return $p;
        });

        // 7. Load relasi lomba, kategori, jadwal, dan anggota
        $pendaftaran->load([
            'lomba' => function ($query) {
                $query->with([
                    'kategori',
                    'jadwal' => function ($q) {
                        $q->orderBy('tanggal')->orderBy('waktu_mulai');
                    },
                ]);
            },
            'anggota',
        ]);

        return response()->json([
            'success' => true,
            'message' => $isBeregu
                ? "Pendaftaran tim '{$namaTim}' berhasil diajukan."
                : 'Pendaftaran lomba berhasil diajukan.',
            'data' => $pendaftaran,
        ], 201);
    }
}
