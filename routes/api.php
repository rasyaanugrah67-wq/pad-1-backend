<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BerandaController;
use App\Http\Controllers\Api\LombaController;
use App\Http\Controllers\Api\PendaftaranController;
use App\Http\Controllers\Api\IuranController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\DashboardController;

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

   Route::get('/dashboard', [
    DashboardController::class,
    'admin',
    ]);
});


// ==============================
// PANITIA
// ==============================

Route::middleware([
    'auth:sanctum',
    'role:Panitia',
])->prefix('panitia')->group(function () {

    Route::get('/dashboard', [
    DashboardController::class,
    'panitia',
    ]);
});



 // ==============================
 // WARGA
 // ==============================

Route::middleware([
    'auth:sanctum',
    'role:Warga',
])->prefix('warga')->group(function () {

    Route::get('/dashboard', [
    DashboardController::class,
    'warga',
    ]);

    // PB-02: Beranda Warga
    Route::get('/beranda', [
        BerandaController::class,
        'index'
    ]);

    
    // PB-03: Daftar Lomba Warga
    Route::get('/lomba', [
        LombaController::class,
        'index'
    ]);

    // PB-03: Detail Lomba Warga
    Route::get('/lomba/{id}', [
        LombaController::class,
        'show'
    ])->whereNumber('id');

    // PB-04: Pendaftaran Lomba Warga
    Route::get('/pendaftaran', [
        PendaftaranController::class,
        'index'
    ]);

    Route::post('/pendaftaran', [
        PendaftaranController::class,
        'store'
    ]);

    // PB-05: Iuran dan Pembayaran Warga
    Route::get('/iuran', [
        IuranController::class,
        'index'
    ]);

    Route::get('/iuran/{id_iuran}', [
        IuranController::class,
        'show'
    ])->whereNumber('id_iuran');

    Route::post('/iuran/{id_iuran}/pembayaran', [
        IuranController::class,
        'bayar'
    ])->whereNumber('id_iuran');

    // PB-06: Notifikasi Warga
    Route::get('/notifikasi', [
        NotifikasiController::class,
        'index'
    ]);

    Route::get('/notifikasi/unread-count', [
        NotifikasiController::class,
        'unreadCount'
    ]);

    Route::patch('/notifikasi/{id_notifikasi}/read', [
        NotifikasiController::class,
        'markAsRead'
    ])->whereNumber('id_notifikasi');

    Route::patch('/notifikasi/read-all', [
        NotifikasiController::class,
        'markAllAsRead'
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