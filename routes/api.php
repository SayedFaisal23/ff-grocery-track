<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\AuthenticateApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Laluan Terbuka (Public routes)
Route::post('/login', [ApiController::class, 'login']);

// Laluan Dilindungi (Authenticated API routes)
Route::middleware([AuthenticateApi::class])->group(function () {

    Route::post('/logout', [ApiController::class, 'logout']);
    Route::get('/user', [ApiController::class, 'user']);

    // Ciri-ciri utama (Semua peranan boleh capai mengikut sekatan tertentu)
    Route::get('/inventori', [ApiController::class, 'inventoriList']);
    Route::get('/kategori', [ApiController::class, 'kategoriList']);
    Route::get('/inventori/restok', [ApiController::class, 'restokList']);

    // Keizinan Tambah / Edit / Padam Inventori
    Route::post('/inventori', [ApiController::class, 'inventoriStore']);
    Route::put('/inventori/{inventori}', [ApiController::class, 'inventoriUpdate']);
    Route::put('/inventori/{inventori}/adjust', [ApiController::class, 'inventoriAdjust']);
    Route::delete('/inventori/{inventori}', [ApiController::class, 'inventoriDestroy']);

    // Permohonan pembelian
    Route::get('/tuntutan', [ApiController::class, 'tuntutanList']);
    Route::post('/tuntutan', [ApiController::class, 'tuntutanStore']);
    Route::get('/tuntutan/{tuntutan}/supporting-document', [ApiController::class, 'tuntutanShowPurchaseAttachment'])
        ->name('api.tuntutan.purchase-attachment');
    Route::get('/tuntutan/{tuntutan}/payment-proof', [ApiController::class, 'tuntutanShowPaymentProofAttachment'])
        ->name('api.tuntutan.payment-proof');
    Route::get('/tuntutan/{tuntutan}/lampiran', [ApiController::class, 'tuntutanShowAttachment'])
        ->name('api.tuntutan.attachment');
    Route::patch('/tuntutan/{tuntutan}/status', [ApiController::class, 'tuntutanUpdateStatus']);
    Route::post('/tuntutan/{tuntutan}/lampiran', [ApiController::class, 'tuntutanUploadAttachment']);
    Route::post('/tuntutan/{tuntutan}/payment-proof', [ApiController::class, 'tuntutanUploadPaymentProof']);
    Route::post('/tuntutan/{tuntutan}/detail-reviewed', [ApiController::class, 'tuntutanRecordDetailsViewed'])
        ->name('api.tuntutan.detail-reviewed');
    Route::get('/tuntutan-preset', [ApiController::class, 'tuntutanPresetList']);
    Route::post('/tuntutan-preset', [ApiController::class, 'tuntutanPresetStore']);
    Route::put('/tuntutan-preset/{tuntutanPreset}', [ApiController::class, 'tuntutanPresetUpdate']);
    Route::delete('/tuntutan-preset/{tuntutanPreset}', [ApiController::class, 'tuntutanPresetDestroy']);

    // Khas untuk Superadmin sahaja
    Route::get('/pengguna', [ApiController::class, 'penggunaList']);
    Route::post('/pengguna', [ApiController::class, 'penggunaStore']);
    Route::put('/pengguna/{user}', [ApiController::class, 'penggunaUpdate']);
    Route::delete('/pengguna/{user}', [ApiController::class, 'penggunaDestroy']);

    Route::get('/log-aktiviti', [ApiController::class, 'logAktivitiList']);
});
