<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\TransaksiItemController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Publik — kontrak v1 self-order (konsumen: SoyaScan, tanpa auth)
Route::get('/menu', [MenuController::class, 'katalog']);
Route::post('/order', [OrderController::class, 'store']);
Route::get('/loyalty/{nomorWa}', [LoyaltyController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Read: kasir & manager (dibutuhkan saat menyusun transaksi).
    // Catatan: list menu internal (flat + filter, termasuk nonaktif) pindah
    // ke /api/menu-internal karena GET /api/menu kini milik katalog publik
    // kontrak v1.
    Route::apiResource('kategori', KategoriController::class)
        ->only(['index', 'show'])
        ->parameters(['kategori' => 'kategori']);
    Route::get('menu-internal', [MenuController::class, 'index']);
    Route::apiResource('menu', MenuController::class)
        ->only(['show'])
        ->parameters(['menu' => 'menu']);

    // Alur transaksi kasir (kasir & manager)
    Route::apiResource('transaksi', TransaksiController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['transaksi' => 'transaksi']);
    Route::post('transaksi/{transaksi}/items', [TransaksiItemController::class, 'store']);
    Route::patch('transaksi/{transaksi}/items/{item}', [TransaksiItemController::class, 'update']);
    Route::delete('transaksi/{transaksi}/items/{item}', [TransaksiItemController::class, 'destroy']);
    Route::post('transaksi/{transaksi}/diskon', [TransaksiController::class, 'diskon']);
    Route::post('transaksi/{transaksi}/redeem-poin', [TransaksiController::class, 'redeemPoin']);
    Route::post('transaksi/{transaksi}/bayar', [TransaksiController::class, 'bayar']);
    // alias sesuai penamaan M3 — action yang sama dengan /bayar
    Route::post('transaksi/{transaksi}/tandai-lunas', [TransaksiController::class, 'bayar']);
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
