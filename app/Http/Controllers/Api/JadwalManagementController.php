<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalLomba;
use App\Models\Lomba;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalManagementController extends Controller
{
    /**
     * GET: Daftar jadwal berdasarkan lomba.
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

        $jadwal = JadwalLomba::with('lomba')
            ->where('id_lomba', $validated['id_lomba'])
            ->orderBy('tanggal')
            ->orderBy('waktu_mulai')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal lomba berhasil diambil.',
            'data' => $jadwal,
        ]);
    }

    /**
     * POST: Tambah jadwal lomba.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_lomba' => [
                'required',
                'integer',
                'exists:lomba,id_lomba',
            ],
            'tanggal' => [
                'required',
                'date',
            ],
            'waktu_mulai' => [
                'nullable',
                'date_format:H:i',
            ],
            'waktu_selesai' => [
                'nullable',
                'date_format:H:i',
            ],
            'lokasi' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        if (
            !empty($validated['waktu_mulai'])
            && !empty($validated['waktu_selesai'])
            && $validated['waktu_selesai'] <= $validated['waktu_mulai']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus setelah waktu mulai.',
            ], 422);
        }

        $jadwal = DB::transaction(function () use ($validated) {
            return JadwalLomba::create($validated);
        });

        $jadwal->load('lomba');

        return response()->json([
            'success' => true,
            'message' => 'Jadwal lomba berhasil ditambahkan.',
            'data' => $jadwal,
        ], 201);
    }

    /**
     * PUT: Edit jadwal lomba.
     */
    public function update(Request $request, $id_jadwal): JsonResponse
    {
        $jadwal = JadwalLomba::with('lomba')
            ->find($id_jadwal);

        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal lomba tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'tanggal' => [
                'sometimes',
                'required',
                'date',
            ],
            'waktu_mulai' => [
                'sometimes',
                'nullable',
                'date_format:H:i',
            ],
            'waktu_selesai' => [
                'sometimes',
                'nullable',
                'date_format:H:i',
            ],
            'lokasi' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        $waktuMulai = array_key_exists('waktu_mulai', $validated)
            ? $validated['waktu_mulai']
            : $jadwal->waktu_mulai;

        $waktuSelesai = array_key_exists('waktu_selesai', $validated)
            ? $validated['waktu_selesai']
            : $jadwal->waktu_selesai;

        if (
            !empty($waktuMulai)
            && !empty($waktuSelesai)
            && $waktuSelesai <= $waktuMulai
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus setelah waktu mulai.',
            ], 422);
        }

        DB::transaction(function () use ($jadwal, $validated) {
            $jadwal->update($validated);
        });

        $jadwal->refresh()->load('lomba');

        return response()->json([
            'success' => true,
            'message' => 'Jadwal lomba berhasil diperbarui.',
            'data' => $jadwal,
        ]);
    }

    /**
     * DELETE: Hapus jadwal lomba.
     */
    public function destroy($id_jadwal): JsonResponse
    {
        $jadwal = JadwalLomba::find($id_jadwal);

        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal lomba tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($jadwal) {
            $jadwal->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Jadwal lomba berhasil dihapus.',
        ]);
    }
}
