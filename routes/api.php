<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\MenuController;
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

    // Write: hanya manager
    Route::middleware('role:manager')->group(function () {
        Route::apiResource('kategori', KategoriController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['kategori' => 'kategori']);
        Route::apiResource('menu', MenuController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['menu' => 'menu']);
    });
});
