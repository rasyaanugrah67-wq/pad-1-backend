<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfilController extends Controller
{
    /**
     * PUT /api/warga/profil
     * Memperbarui profil warga yang sedang login.
     */
    public function update(Request $request): JsonResponse
    {
        $akun = $request->user();
        $warga = $akun->warga;

        if (!$warga) {
            return response()->json([
                'success' => false,
                'message' => 'Data warga tidak ditemukan.',
            ], 404);
        }

        if (!$request->hasAny([
            'nama',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'no_hp',
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data profil yang dikirim.',
            ], 422);
        }

        $validated = $request->validate([
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'tempat_lahir' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'tanggal_lahir' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'alamat' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'no_hp' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        $warga->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'id_warga' => $warga->id_warga,
                'nama' => $warga->nama,
                'tempat_lahir' => $warga->tempat_lahir,
                'tanggal_lahir' => $warga->tanggal_lahir
                    ? $warga->tanggal_lahir->format('Y-m-d')
                    : null,
                'alamat' => $warga->alamat,
                'no_hp' => $warga->no_hp,
            ],
        ]);
    }

    /**
     * PUT /api/warga/username
     * Mengubah username akun warga.
     */
    public function updateUsername(Request $request): JsonResponse
    {
        $akun = $request->user();

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique('akun', 'username')
                    ->ignore($akun->id_akun, 'id_akun'),
            ],
            'current_password' => [
                'required',
                'string',
            ],
        ]);

        if (!Hash::check(
            $validated['current_password'],
            $akun->password
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah.',
            ], 422);
        }

        $akun->update([
            'username' => $validated['username'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Username berhasil diperbarui.',
            'data' => [
                'id_akun' => $akun->id_akun,
                'username' => $akun->username,
            ],
        ]);
    }

    /**
     * PUT /api/warga/ubah-password
     * Mengubah password akun warga.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $akun = $request->user();

        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        if (!Hash::check(
            $validated['current_password'],
            $akun->password
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah.',
            ], 422);
        }

        $akun->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
