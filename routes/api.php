<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BerandaController;
use App\Http\Controllers\Api\LombaController;
use App\Http\Controllers\Api\PendaftaranController;
use App\Http\Controllers\Api\PendaftaranManagementController;
use App\Http\Controllers\Api\IuranController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\WargaController;
use App\Http\Controllers\Api\RtController;
use App\Http\Controllers\Api\RwController;

// ==============================
// AUTHENTICATION
// ==============================

Route::prefix('auth')->group(function () {

    Route::post('/login', [
        AuthController::class,
        'login'
    ]);

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [
            AuthController::class,
            'me'
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout'
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

    // Dashboard Admin
    Route::get('/dashboard', [
        DashboardController::class,
        'admin'
    ]);

    // AD-01: Data Warga
    Route::apiResource('warga', WargaController::class);

    // AD-01: Data RT
    Route::apiResource('rt', RtController::class);

    // AD-01: Data RW
    Route::apiResource('rw', RwController::class);

    // PB-04: Pengelolaan Pendaftaran
    Route::get('/pendaftaran', [
        PendaftaranManagementController::class,
        'index'
    ]);

    Route::get('/pendaftaran/{id}', [
        PendaftaranManagementController::class,
        'show'
    ])->whereNumber('id');

    Route::patch('/pendaftaran/{id}/status', [
        PendaftaranManagementController::class,
        'updateStatus'
    ])->whereNumber('id');

    Route::get('/lomba/{id}/pendaftaran', [
        PendaftaranManagementController::class,
        'byLomba'
    ])->whereNumber('id');

    Route::get('/lomba/{id}/kuota', [
        PendaftaranManagementController::class,
        'kuota'
    ])->whereNumber('id');

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
        'panitia'
    ]);

    // PB-04: Pengelolaan Pendaftaran
    Route::get('/pendaftaran', [
        PendaftaranManagementController::class,
        'index'
    ]);

    Route::get('/pendaftaran/{id}', [
        PendaftaranManagementController::class,
        'show'
    ])->whereNumber('id');

    Route::patch('/pendaftaran/{id}/status', [
        PendaftaranManagementController::class,
        'updateStatus'
    ])->whereNumber('id');

    Route::get('/lomba/{id}/pendaftaran', [
        PendaftaranManagementController::class,
        'byLomba'
    ])->whereNumber('id');

    Route::get('/lomba/{id}/kuota', [
        PendaftaranManagementController::class,
        'kuota'
    ])->whereNumber('id');

});


// ==============================
// WARGA
// ==============================

Route::middleware([
    'auth:sanctum',
    'role:Warga',
])->prefix('warga')->group(function () {

    // Dashboard
    Route::get('/dashboard', [
        DashboardController::class,
        'warga'
    ]);

    // PB-02: Beranda
    Route::get('/beranda', [
        BerandaController::class,
        'index'
    ]);

    // PB-03: Lomba
    Route::get('/lomba', [
        LombaController::class,
        'index'
    ]);

    Route::get('/lomba/{id}', [
        LombaController::class,
        'show'
    ])->whereNumber('id');

    // PB-04: Pendaftaran
    Route::get('/pendaftaran', [
        PendaftaranController::class,
        'index'
    ]);

    Route::post('/pendaftaran', [
        PendaftaranController::class,
        'store'
    ]);

    Route::get('/pendaftaran/{id}', [
        PendaftaranController::class,
        'show'
    ])->whereNumber('id');

    Route::delete('/pendaftaran/{id}', [
        PendaftaranController::class,
        'destroy'
    ])->whereNumber('id');

    // PB-05: Iuran
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

    // PB-06: Notifikasi
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
