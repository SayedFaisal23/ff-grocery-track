<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoriController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\LogAktivitiController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\TuntutanController;
use Illuminate\Support\Facades\Route;

// Laluan Log Masuk / Keluar
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Halaman utama dan ciri-ciri aplikasi yang dilindungi oleh middleware auth
Route::middleware(['auth'])->group(function () {

    // Redirect halaman utama ke senarai inventori
    Route::get('/', function () {
        return redirect()->route('inventori.index');
    })->name('dashboard');

    // Pengurusan Inventori
    Route::get('/restok', [InventoriController::class, 'restockList'])->name('inventori.restok');
    Route::put('/inventori/{inventori}/adjust', [InventoriController::class, 'adjustStock'])->name('inventori.adjust');
    Route::resource('inventori', InventoriController::class);

    // Pengurusan Tuntutan (Reimbursement)
    Route::middleware(['role:Superadmin|Stocker'])->group(function () {
        Route::get('/tuntutan', [TuntutanController::class, 'index'])->name('tuntutan.index');
        Route::get('/tuntutan/tambah', [TuntutanController::class, 'create'])->name('tuntutan.create');
        Route::post('/tuntutan', [TuntutanController::class, 'store'])->name('tuntutan.store');
        Route::get('/tuntutan/{tuntutan}/lampiran', [TuntutanController::class, 'showAttachment'])->name('tuntutan.attachment');
        Route::patch('/tuntutan/{tuntutan}/status', [TuntutanController::class, 'updateStatus'])->name('tuntutan.status');
    });

    // Laluan Khas untuk Superadmin sahaja
    Route::middleware(['role:Superadmin'])->group(function () {
        // Tetapan kategori inventori
        Route::put('/kategori', [KategoriController::class, 'updateAll'])
            ->name('kategori.update-all');
        Route::resource('kategori', KategoriController::class)
            ->only(['index', 'store', 'destroy']);

        // Pengurusan Akaun Pengguna
        Route::resource('pengguna', PenggunaController::class);

        // Log Aktiviti Sistem
        Route::get('/log-aktiviti', [LogAktivitiController::class, 'index'])->name('log_aktiviti.index');
    });
});
