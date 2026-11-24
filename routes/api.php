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
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\AkunController;
use App\Http\Controllers\Api\LombaManagementController;
use App\Http\Controllers\Api\JadwalManagementController;
use App\Http\Controllers\Api\PertandinganManagementController;
use App\Http\Controllers\Api\DokumentasiController;
use App\Http\Controllers\Api\HistoriController;
use App\Http\Controllers\Api\PengaturanPembayaranController;
use App\Http\Controllers\Api\UmumController;

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
// PROFIL & PENGATURAN AKUN (SEMUA ROLE)
// ==============================

Route::middleware('auth:sanctum')->group(function () {

    Route::put('/akun/profil', [
        ProfilController::class,
        'update'
    ]);

    Route::put('/akun/username', [
        ProfilController::class,
        'updateUsername'
    ]);

    Route::put('/akun/ubah-password', [
        ProfilController::class,
        'updatePassword'
    ]);

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


    // AD-01: Data Warga dan Struktur RT/RW

    Route::apiResource('warga', WargaController::class);

    Route::apiResource('rt', RtController::class);

    Route::apiResource('rw', RwController::class);


    // AD-02: Manajemen Akun dan Role

    Route::get('/akun', [
        AkunController::class,
        'index'
    ]);

    Route::get('/akun/{id_akun}', [
        AkunController::class,
        'show'
    ])->whereNumber('id_akun');

    Route::patch('/akun/{id_akun}/role', [
        AkunController::class,
        'updateRole'
    ])->whereNumber('id_akun');

    Route::patch('/akun/{id_akun}/status', [
        AkunController::class,
        'updateStatus'
    ])->whereNumber('id_akun');


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
    "auth:sanctum",
    "role:Panitia",
])->prefix('panitia')->group(function () {

    // Dashboard Panitia

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

// Data publik yang tetap memakai URL legacy FE dengan prefix /warga.
Route::get('/warga/beranda', [
    BerandaController::class,
    'index'
]);

Route::get('/warga/lomba', [
    LombaController::class,
    'index'
]);

Route::get('/warga/lomba/{id}', [
    LombaController::class,
    'show'
])->whereNumber('id');

Route::middleware([
    "auth:sanctum",
    "role:Warga",
])->prefix('warga')->group(function () {

    // Dashboard Warga

    Route::get('/dashboard', [
        DashboardController::class,
        'warga'
    ]);


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


    // PB-05: Iuran Pribadi

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
    "auth:sanctum",
    "role:Admin,Panitia",
])->group(function () {

    // PB-03: Manajemen Lomba

   Route::get('/lomba/manage', [
    LombaManagementController::class,
    'index'
]);

Route::get('/lomba/manage/arsip', [
    LombaManagementController::class,
    'arsipIndex'
]);

Route::post('/lomba/manage', [
    LombaManagementController::class,
    'store'
]);

Route::patch('/lomba/manage/{id_lomba}/arsip', [
    LombaManagementController::class,
    'arsip'
])->whereNumber('id_lomba');

Route::patch('/lomba/manage/{id_lomba}/pulihkan', [
    LombaManagementController::class,
    'pulihkan'
])->whereNumber('id_lomba');


Route::put('/lomba/manage/{id_lomba}', [
    LombaManagementController::class,
    'update'
]);


Route::delete('/lomba/manage/{id_lomba}', [
    LombaManagementController::class,
    'destroy'
]);

// PB-03: Kelola Pertandingan
Route::get('/pertandingan/manage', [
    PertandinganManagementController::class,
    'index'
]);

Route::post('/pertandingan/manage', [
    PertandinganManagementController::class,
    'store'
]);

Route::put('/pertandingan/manage/{id_pertandingan}', [
    PertandinganManagementController::class,
    'update'
]);

Route::delete('/pertandingan/manage/{id_pertandingan}', [
    PertandinganManagementController::class,
    'destroy'
]);

    // PB-05: Manajemen Iuran

    Route::get('/manajemen/iuran', [
        IuranController::class,
        'daftarIuranManajemen'
    ]);

    Route::patch('/manajemen/pembayaran/{id_pembayaran}/verifikasi', [
        IuranController::class,
        'verifikasiPembayaran'
    ])->whereNumber('id_pembayaran');


Route::get('/jadwal/manage', [
    JadwalManagementController::class,
    'index'
]);

Route::post('/jadwal/manage', [
    JadwalManagementController::class,
    'store'
]);

Route::put('/jadwal/manage/{id_jadwal}', [
    JadwalManagementController::class,
    'update'
]);

Route::delete('/jadwal/manage/{id_jadwal}', [
    JadwalManagementController::class,
    'destroy'
]);


});
// ==============================
// AKSES BUKTI PEMBAYARAN
// ==============================
// Admin dan Panitia dapat mengakses semua bukti.
// Warga hanya dapat mengakses bukti miliknya sendiri.
// Pemeriksaan kepemilikan dilakukan di IuranController.

Route::middleware('auth:sanctum')->get(
    '/pembayaran/{id_pembayaran}/bukti',
    [IuranController::class, 'buktiBayar']
)->whereNumber('id_pembayaran');


// ==============================
// TRANSPARANSI IURAN (PUBLIK / GUEST)
// ==============================
// Dapat diakses secara publik tanpa login oleh semua orang (Guest, Warga, Panitia, Admin).
Route::get('/warga/iuran/transparansi', [
    IuranController::class,
    'transparansi'
]);
Route::get('/iuran/transparansi', [
    IuranController::class,
    'transparansi'
]);

// API Umum / guest: data publik untuk landing page.
Route::get('/beranda', [BerandaController::class, 'index']);
Route::get('/lomba', [LombaController::class, 'index']);
Route::get('/lomba/{id}', [LombaController::class, 'show'])->whereNumber('id');
Route::get('/jadwal', [UmumController::class, 'jadwal']);
Route::get('/pengumuman', [UmumController::class, 'pengumuman']);

// PT-04: Dokumentasi. Semua role dapat membaca; Admin dan Panitia dapat mengelola.
Route::middleware(['auth:sanctum', 'role:Admin,Panitia,Warga'])->group(function () {
    Route::get('/dokumentasi', [DokumentasiController::class, 'index']);
    Route::get('/dokumentasi/{id_album}', [DokumentasiController::class, 'show'])->whereNumber('id_album');
});

Route::middleware(['auth:sanctum', 'role:Admin,Panitia'])->group(function () {
    Route::post('/dokumentasi', [DokumentasiController::class, 'store']);
    Route::put('/dokumentasi/{id_album}', [DokumentasiController::class, 'update'])->whereNumber('id_album');
    Route::delete('/dokumentasi/{id_album}', [DokumentasiController::class, 'destroy'])->whereNumber('id_album');
    Route::post('/dokumentasi/{id_album}/media', [DokumentasiController::class, 'uploadMedia'])->whereNumber('id_album');
    Route::delete('/dokumentasi/{id_album}/media/{id_dokumentasi}', [DokumentasiController::class, 'destroyMedia'])->whereNumber(['id_album', 'id_dokumentasi']);
});

// PT-03: Pengaturan rekening dan QRIS.
Route::middleware(['auth:sanctum', 'role:Admin,Panitia,Warga'])->group(function () {
    Route::get('/pengaturan-pembayaran', [PengaturanPembayaranController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:Admin,Panitia'])->group(function () {
    Route::post('/pengaturan-pembayaran', [PengaturanPembayaranController::class, 'update']);
    Route::put('/pengaturan-pembayaran', [PengaturanPembayaranController::class, 'update']);
});

// PT-04: Histori. Semua role dapat membaca; Admin dan Panitia dapat mengelola.
Route::middleware(['auth:sanctum', 'role:Admin,Panitia,Warga'])->group(function () {
    Route::get('/histori', [HistoriController::class, 'index']);
    Route::get('/histori/{id_periode}', [HistoriController::class, 'show'])->whereNumber('id_periode');
});

Route::middleware(['auth:sanctum', 'role:Admin,Panitia'])->group(function () {
    Route::put('/histori/{id_periode}', [HistoriController::class, 'update'])->whereNumber('id_periode');
    Route::post('/histori/{id_periode}/lomba/{id_lomba}/pemenang', [HistoriController::class, 'storeWinner'])->whereNumber(['id_periode', 'id_lomba']);
    Route::put('/pemenang/{id_pemenang}', [HistoriController::class, 'updateWinner'])->whereNumber('id_pemenang');
    Route::delete('/pemenang/{id_pemenang}', [HistoriController::class, 'destroyWinner'])->whereNumber('id_pemenang');
});
