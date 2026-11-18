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
     * Memperbarui profil pengguna yang sedang login.
     *
     * @OA\Put(
     *     path="/api/akun/profil",
     *     operationId="updateProfilAkun",
         *     tags={"Akun - Profil"},
         *     summary="Memperbarui profil akun",
         *     description="Admin, Panitia, dan Warga dapat memperbarui data profil masing-masing. NIK, RT, dan RW tidak dapat diubah melalui endpoint ini.",
         *     security={{"sanctum":{}}},
         *     @OA\RequestBody(
         *         required=true,
         *         @OA\JsonContent(
         *             @OA\Property(property="nama", type="string", example="Budi Santoso"),
         *             @OA\Property(property="tempat_lahir", type="string", nullable=true, example="Yogyakarta"),
         *             @OA\Property(property="tanggal_lahir", type="string", format="date", nullable=true, example="2005-08-17"),
         *             @OA\Property(property="alamat", type="string", nullable=true, example="Jl. Melati No. 10"),
         *             @OA\Property(property="no_hp", type="string", nullable=true, example="081234567890")
         *         )
         *     ),
         *     @OA\Response(response=200, description="Profil berhasil diperbarui"),
         *     @OA\Response(response=401, description="Unauthenticated"),
         *     @OA\Response(response=404, description="Data profil tidak ditemukan"),
         *     @OA\Response(response=422, description="Validasi gagal")
         * )
         */
    public function update(Request $request): JsonResponse
    {
        $akun = $request->user();
        $warga = $akun->warga;

        if (!$warga) {
            return response()->json([
                'success' => false,
                'message' => 'Data profil tidak ditemukan.',
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
     * Mengubah username akun milik pengguna yang sedang login.
     *
     * @OA\Put(
     *     path="/api/akun/username",
         *     operationId="updateUsernameAkun",
         *     tags={"Akun - Pengaturan"},
         *     summary="Mengubah username akun",
         *     description="Admin, Panitia, dan Warga dapat mengubah username akun masing-masing.",
         *     security={{"sanctum":{}}},
         *     @OA\RequestBody(
         *         required=true,
         *         @OA\JsonContent(
         *             required={"username","current_password"},
         *             @OA\Property(property="username", type="string", minLength=3, maxLength=50, example="budi_santoso"),
         *             @OA\Property(property="current_password", type="string", format="password", example="password_lama")
         *         )
         *     ),
         *     @OA\Response(response=200, description="Username berhasil diperbarui"),
         *     @OA\Response(response=401, description="Unauthenticated"),
         *     @OA\Response(response=422, description="Password salah atau username tidak valid")
         * )
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
     * Mengubah password akun milik pengguna yang sedang login.
     *
     * @OA\Put(
     *     path="/api/akun/ubah-password",
         *     operationId="updatePasswordAkun",
         *     tags={"Akun - Pengaturan"},
         *     summary="Mengubah password akun",
         *     description="Admin, Panitia, dan Warga dapat mengubah password akun masing-masing.",
         *     security={{"sanctum":{}}},
         *     @OA\RequestBody(
         *         required=true,
         *         @OA\JsonContent(
         *             required={"current_password","new_password","new_password_confirmation"},
         *             @OA\Property(property="current_password", type="string", format="password", example="password_lama"),
        *             @OA\Property(property="new_password", type="string", format="password", minLength=8, example="passwordBaru123"),
        *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="passwordBaru123")
        *         )
        *     ),
        *     @OA\Response(response=200, description="Password berhasil diperbarui"),
        *     @OA\Response(response=401, description="Unauthenticated"),
        *     @OA\Response(response=422, description="Password lama salah atau validasi gagal")
        * )
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
