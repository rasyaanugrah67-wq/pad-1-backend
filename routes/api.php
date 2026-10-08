<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BerandaController;

// ==============================
// AUTHENTICATION
// ==============================

Route::prefix('auth')->group(function () {

    // Login tidak membutuhkan token
    Route::post('/login', [
        AuthController::class,
        'login'
    ]);

    // Harus sudah login
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [
            AuthController::class,
            'logout'
        ]);

        Route::get('/me', [
            AuthController::class,
            'me'
        ]);
    });
});


// ==============================
// ADMIN
// ==============================

Route::middleware([
    'auth:sanctum',
    'role:Admin',
])->prefix('admin')->group(function () {

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Dashboard Admin'
        ]);
    });

});


// ==============================
// PANITIA
// ==============================

Route::middleware([
    'auth:sanctum',
    'role:Panitia',
])->prefix('panitia')->group(function () {

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Dashboard Panitia'
        ]);
    });

});



 // ==============================
 // WARGA
 // ==============================

Route::middleware([
    'auth:sanctum',
    'role:Warga',
])->prefix('warga')->group(function () {

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Dashboard Warga'
        ]);
    });

    // PB-02: Beranda Warga
    Route::get('/beranda', [
        BerandaController::class,
        'index'
    ]);

});


// ==============================
// ADMIN + PANITIA
// ==============================

Route::middleware([
    'auth:sanctum',
    'role:Admin,Panitia',
])->group(function () {

    Route::get('/lomba/manage', function () {
        return response()->json([
            'message' => 'Halaman ini dapat diakses Admin dan Panitia'
        ]);
    });

});