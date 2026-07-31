<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\LaporanKasirController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PembatalanController;
use App\Http\Controllers\PengaturanLoyaltyController;
use App\Http\Controllers\PengaturanTokoController;
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

    // Pengaturan > Profil Saya. Selalu menyasar akun pemanggil sendiri —
    // tidak ada id user di path maupun body, jadi tidak ada jalan mengedit
    // akun orang lain lewat sini.
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateProfil']);
    Route::post('/me/password', [AuthController::class, 'ubahPassword']);

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

    // Auto-detect pelanggan lama/baru di halaman Pesanan — read-only.
    Route::get('customers/cari', [CustomerController::class, 'cari']);

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
    // Alias lama pembatalan penuh — sekarang ikut melewati alur pembatalan
    // baru (poin redeem dikembalikan, dokumen pembatalan dicatat, proyeksi
    // laporan disinkronkan).
    Route::post('transaksi/{transaksi}/batal', [TransaksiController::class, 'batal']);

    // Pembatalan / koreksi pesanan yang salah — BUKAN pengembalian uang.
    // Kasir ikut boleh karena dialah yang berhadapan dengan pelanggan saat
    // kesalahan pesanan ketahuan; jejaknya dijaga oleh alasan yang wajib diisi
    // dan pencatatan akun pemroses.
    Route::post('transaksi/{transaksi}/pembatalan', [PembatalanController::class, 'store']);
    Route::get('transaksi/{transaksi}/pembatalan', [PembatalanController::class, 'index']);

    // Pengaturan loyalty — BACA saja di sini. Kasir ikut butuh: rate dipakai
    // menampilkan estimasi poin di struk, katalog dipakai merender tombol
    // redeem (termasuk menyembunyikan reward yang dinonaktifkan manager).
    Route::get('pengaturan/loyalty', [PengaturanLoyaltyController::class, 'show']);
    Route::get('pengaturan/loyalty/katalog', [PengaturanLoyaltyController::class, 'katalog']);

    // Info toko — kasir ikut baca karena dia yang mencetak nota berheader ini.
    Route::get('pengaturan/toko', [PengaturanTokoController::class, 'show']);

    // Dashboard porsi kasir — cukup untuk memantau performa harian sendiri.
    // Sengaja dibatasi: tidak ada data per-pelanggan (RFM/loyalty/switch) dan
    // tidak ada export. `meta` ikut karena date-picker kedua halaman di bawah
    // butuh rentang tanggal; isinya cuma daftar tanggal/ukuran/platform/segmen.
    Route::prefix('dashboard')->group(function () {
        Route::get('meta', [DashboardController::class, 'meta']);
        Route::get('ringkasan', [DashboardController::class, 'ringkasan']);
        Route::get('produk-terlaris', [DashboardController::class, 'produkTerlaris']);
    });

    // Write: hanya manager
    Route::middleware('role:manager')->group(function () {
        Route::apiResource('kategori', KategoriController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['kategori' => 'kategori']);
        Route::apiResource('menu', MenuController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['menu' => 'menu']);

        // Setting rate poin & biaya poin katalog redeem (LoyalSeed).
        // Berdampak langsung ke uang, jadi sengaja manager-only.
        Route::patch('pengaturan/loyalty', [PengaturanLoyaltyController::class, 'update']);
        Route::patch('pengaturan/loyalty/katalog/{kode}', [PengaturanLoyaltyController::class, 'updateKatalog']);

        // Info toko dipakai di header nota & laporan, jadi bukan preferensi
        // pribadi kasir — manager yang pegang.
        Route::patch('pengaturan/toko', [PengaturanTokoController::class, 'update']);

        // Reporting lanjutan + export (manager-only)
        Route::prefix('dashboard')->group(function () {
            Route::get('time-series', [DashboardController::class, 'timeSeries']);
            Route::get('revenue-ukuran', [DashboardController::class, 'revenueUkuran']);
            Route::get('platform', [DashboardController::class, 'platform']);
            Route::get('loyalty', [DashboardController::class, 'loyalty']);
            Route::get('rfm', [DashboardController::class, 'rfm']);
            Route::get('switch', [DashboardController::class, 'switch']);
        });

        // Perbandingan antar akun kasir + rekap pembatalan seluruh toko.
        // Manager-only: keduanya menyandingkan performa akun orang lain.
        Route::get('laporan/kasir', [LaporanKasirController::class, 'index']);
        Route::get('pembatalan', [PembatalanController::class, 'semua']);

        Route::get('laporan/export', [ExportController::class, 'export']);

        // QRIS statis merchant & QR menu meja — keduanya menyentuh identitas
        // toko, jadi bukan wewenang kasir.
        Route::post('pengaturan/toko/qris', [PengaturanTokoController::class, 'uploadQris']);
        Route::delete('pengaturan/toko/qris', [PengaturanTokoController::class, 'hapusQris']);
        Route::get('pengaturan/toko/qr-menu', [PengaturanTokoController::class, 'qrMenu']);
    });
});
