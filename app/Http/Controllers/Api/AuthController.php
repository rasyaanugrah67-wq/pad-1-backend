<?php

namespace App\Http\Controllers\Api;

use App\Models\Akun;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    /**
 * @OA\Post(
 *     path="/api/auth/login",
 *     summary="Login pengguna",
 *     description="Login menggunakan username dan password untuk mendapatkan token Sanctum.",
 *     tags={"Authentication"},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"username","password"},
 *             @OA\Property(
 *                 property="username",
 *                 type="string",
 *                 example="admin"
 *             ),
 *             @OA\Property(
 *                 property="password",
 *                 type="string",
 *                 format="password",
 *                 example="Admin123!"
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Login berhasil"
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Username atau password salah"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Akun tidak aktif"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validasi gagal"
 *     )
 * )
 */
    public function login(LoginRequest $request): JsonResponse
    {
        $akun = Akun::with([
            'role',
            'warga.rt.rw',
        ])
            ->where('username', $request->username)
            ->first();

        if (
            !$akun ||
            !Hash::check($request->password, $akun->password)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah.',
            ], 401);
        }

        if (!$akun->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang tidak aktif.',
            ], 403);
        }

        if (
            !$akun->warga ||
            $akun->warga->status !== 'Aktif'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Data warga tidak aktif.',
            ], 403);
        }

        $akun->update([
            'last_login' => now(),
        ]);

        /*
         * Opsional:
         * hapus token sebelumnya supaya satu akun
         * hanya memiliki satu session API.
         */

        // $akun->tokens()->delete();

        $token = $akun->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',

            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',

                'akun' => [
                    'id_akun' => $akun->id_akun,
                    'username' => $akun->username,

                    'role' => [
                        'id_role' => $akun->role->id_role,
                        'nama_role' => $akun->role->nama_role,
                    ],

                    'warga' => [
                        'id_warga' => $akun->warga->id_warga,
                        'nama' => $akun->warga->nama,
                    ],

                    'last_login' => $akun->last_login,
                ],
            ],
        ]);
    }

    /**
 * @OA\Post(
 *     path="/api/auth/logout",
 *     summary="Logout pengguna",
 *     description="Menghapus token Sanctum yang sedang digunakan.",
 *     tags={"Authentication"},
 *     security={{"sanctum":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Logout berhasil"
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 */
    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
 * @OA\Get(
 *     path="/api/auth/me",
 *     summary="Mengambil data pengguna yang sedang login",
 *     description="Mengambil informasi akun berdasarkan token Sanctum.",
 *     tags={"Authentication"},
 *     security={{"sanctum":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Data pengguna berhasil diambil"
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 */
    public function me(Request $request): JsonResponse
    {
        $akun = $request->user()->load([
            'role',
            'warga.rt.rw',
        ]);

        return response()->json([
            'success' => true,

            'data' => [
                'id_akun' => $akun->id_akun,
                'username' => $akun->username,
                'status' => $akun->status,

                'role' => [
                    'id_role' => $akun->role->id_role,
                    'nama_role' => $akun->role->nama_role,
                ],

                'warga' => [
                    'id_warga' => $akun->warga->id_warga,
                    'nama' => $akun->warga->nama,
                    'no_hp' => $akun->warga->no_hp,
                ],
            ],
        ]);
    }
}