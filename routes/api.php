<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\TransaksiItemController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Read: kasir & manager (dibutuhkan saat menyusun transaksi)
    Route::apiResource('kategori', KategoriController::class)
        ->only(['index', 'show'])
        ->parameters(['kategori' => 'kategori']);
    Route::apiResource('menu', MenuController::class)
        ->only(['index', 'show'])
        ->parameters(['menu' => 'menu']);

    // Alur transaksi kasir (kasir & manager)
    Route::apiResource('transaksi', TransaksiController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['transaksi' => 'transaksi']);
    Route::post('transaksi/{transaksi}/items', [TransaksiItemController::class, 'store']);
    Route::patch('transaksi/{transaksi}/items/{item}', [TransaksiItemController::class, 'update']);
    Route::delete('transaksi/{transaksi}/items/{item}', [TransaksiItemController::class, 'destroy']);
    Route::post('transaksi/{transaksi}/diskon', [TransaksiController::class, 'diskon']);
    Route::post('transaksi/{transaksi}/bayar', [TransaksiController::class, 'bayar']);
    Route::post('transaksi/{transaksi}/batal', [TransaksiController::class, 'batal']);

    // Write: hanya manager
    Route::middleware('role:manager')->group(function () {
        Route::apiResource('kategori', KategoriController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['kategori' => 'kategori']);
        Route::apiResource('menu', MenuController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['menu' => 'menu']);

        // Reporting dashboard + export (manager-only)
        Route::prefix('dashboard')->group(function () {
            Route::get('meta', [DashboardController::class, 'meta']);
            Route::get('ringkasan', [DashboardController::class, 'ringkasan']);
            Route::get('time-series', [DashboardController::class, 'timeSeries']);
            Route::get('revenue-ukuran', [DashboardController::class, 'revenueUkuran']);
            Route::get('produk-terlaris', [DashboardController::class, 'produkTerlaris']);
            Route::get('platform', [DashboardController::class, 'platform']);
            Route::get('loyalty', [DashboardController::class, 'loyalty']);
            Route::get('rfm', [DashboardController::class, 'rfm']);
            Route::get('switch', [DashboardController::class, 'switch']);
        });

        Route::get('laporan/export', [ExportController::class, 'export']);
    });
});
