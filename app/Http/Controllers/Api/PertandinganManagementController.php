<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PertandinganManagementController extends Controller
{
    /**
     * GET: Daftar pertandingan berdasarkan lomba.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_lomba' => [
                'required',
                'integer',
                'exists:lomba,id_lomba',
            ],
        ]);

        $pertandingan = DB::table('pertandingan as p')
            ->leftJoin('jadwal_lomba as j', 'p.id_jadwal', '=', 'j.id_jadwal')
            ->where('p.id_lomba', $validated['id_lomba'])
            ->select(
                'p.id_pertandingan',
                'p.id_lomba',
                'p.id_jadwal',
                'p.peserta_1',
                'p.peserta_2',
                'p.babak',
                'p.waktu_mulai',
                'p.waktu_selesai',
                'p.status',
                'p.created_at',
                'p.updated_at',
                'j.tanggal as tanggal_jadwal_lomba',
                'j.lokasi as lokasi_jadwal_lomba'
            )
            ->orderBy('p.waktu_mulai')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data pertandingan berhasil diambil.',
            'data' => $pertandingan,
        ]);
    }

    /**
     * POST: Tambah pertandingan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_lomba' => [
                'required',
                'integer',
                'exists:lomba,id_lomba',
            ],

            'id_jadwal' => [
                'nullable',
                'integer',
                'exists:jadwal_lomba,id_jadwal',
            ],

            'peserta_1' => [
                'required',
                'string',
                'max:150',
            ],

            'peserta_2' => [
                'required',
                'string',
                'max:150',
                'different:peserta_1',
            ],

            'babak' => [
                'nullable',
                'string',
                'max:50',
            ],

            'waktu_mulai' => [
                'required',
                'date',
            ],

            'waktu_selesai' => [
                'nullable',
                'date',
                'after:waktu_mulai',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:Dijadwalkan,Berlangsung,Selesai',
            ],
        ]);

        // Pastikan jadwal yang dipilih milik lomba yang sama.
        if (!empty($validated['id_jadwal'])) {
            $jadwalValid = DB::table('jadwal_lomba')
                ->where('id_jadwal', $validated['id_jadwal'])
                ->where('id_lomba', $validated['id_lomba'])
                ->exists();

            if (!$jadwalValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal yang dipilih bukan milik lomba tersebut.',
                ], 422);
            }
        }

        $idPertandingan = DB::transaction(function () use ($validated) {
            return DB::table('pertandingan')->insertGetId([
                'id_lomba' => $validated['id_lomba'],
                'id_jadwal' => $validated['id_jadwal'] ?? null,
                'peserta_1' => $validated['peserta_1'],
                'peserta_2' => $validated['peserta_2'],
                'babak' => $validated['babak'] ?? null,
                'waktu_mulai' => $validated['waktu_mulai'],
                'waktu_selesai' => $validated['waktu_selesai'] ?? null,
                'status' => $validated['status'] ?? 'Dijadwalkan',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $pertandingan = DB::table('pertandingan')
            ->where('id_pertandingan', $idPertandingan)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil ditambahkan.',
            'data' => $pertandingan,
        ], 201);
    }

    /**
     * PUT: Edit pertandingan.
     */
    public function update(Request $request, $id_pertandingan): JsonResponse
    {
        $pertandingan = DB::table('pertandingan')
            ->where('id_pertandingan', $id_pertandingan)
            ->first();

        if (!$pertandingan) {
            return response()->json([
                'success' => false,
                'message' => 'Pertandingan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'id_jadwal' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:jadwal_lomba,id_jadwal',
            ],

            'peserta_1' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'peserta_2' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'babak' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'waktu_mulai' => [
                'sometimes',
                'required',
                'date',
            ],

            'waktu_selesai' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:Dijadwalkan,Berlangsung,Selesai',
            ],
        ]);

        $peserta1 = array_key_exists('peserta_1', $validated)
            ? $validated['peserta_1']
            : $pertandingan->peserta_1;

        $peserta2 = array_key_exists('peserta_2', $validated)
            ? $validated['peserta_2']
            : $pertandingan->peserta_2;

        if ($peserta1 !== null && $peserta2 !== null && $peserta1 === $peserta2) {
            return response()->json([
                'success' => false,
                'message' => 'Peserta 1 dan Peserta 2 tidak boleh sama.',
            ], 422);
        }

        $idJadwal = array_key_exists('id_jadwal', $validated)
            ? $validated['id_jadwal']
            : $pertandingan->id_jadwal;

        // Pastikan jadwal tetap terkait dengan lomba yang sama.
        if ($idJadwal !== null) {
            $jadwalValid = DB::table('jadwal_lomba')
                ->where('id_jadwal', $idJadwal)
                ->where('id_lomba', $pertandingan->id_lomba)
                ->exists();

            if (!$jadwalValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal yang dipilih bukan milik lomba tersebut.',
                ], 422);
            }
        }

        $waktuMulai = array_key_exists('waktu_mulai', $validated)
            ? $validated['waktu_mulai']
            : $pertandingan->waktu_mulai;

        $waktuSelesai = array_key_exists('waktu_selesai', $validated)
            ? $validated['waktu_selesai']
            : $pertandingan->waktu_selesai;

        if (
            $waktuSelesai !== null
            && $waktuSelesai <= $waktuMulai
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus setelah waktu mulai.',
            ], 422);
        }

        DB::transaction(function () use (
            $validated,
            $pertandingan,
            $idJadwal
        ) {
            $dataUpdate = [];

            $fields = [
                'peserta_1',
                'peserta_2',
                'babak',
                'waktu_mulai',
                'waktu_selesai',
                'status',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $validated)) {
                    $dataUpdate[$field] = $validated[$field];
                }
            }

            if (array_key_exists('id_jadwal', $validated)) {
                $dataUpdate['id_jadwal'] = $idJadwal;
            }

            if (!empty($dataUpdate)) {
                $dataUpdate['updated_at'] = now();

                DB::table('pertandingan')
                    ->where('id_pertandingan', $pertandingan->id_pertandingan)
                    ->update($dataUpdate);
            }
        });

        $updated = DB::table('pertandingan')
            ->where('id_pertandingan', $id_pertandingan)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    /**
     * DELETE: Hapus pertandingan.
     */
    public function destroy($id_pertandingan): JsonResponse
    {
        $pertandingan = DB::table('pertandingan')
            ->where('id_pertandingan', $id_pertandingan)
            ->first();

        if (!$pertandingan) {
            return response()->json([
                'success' => false,
                'message' => 'Pertandingan tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($id_pertandingan) {
            DB::table('pertandingan')
                ->where('id_pertandingan', $id_pertandingan)
                ->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Pertandingan berhasil dihapus.',
        ]);
    }
}
