<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/header', function () {
    return view('header.index');
});

Route::view('/login', 'auth.login')->name('login');

Route::view('/transaksi', 'manager.transaksi.index')->name('manager.transaksi');

// Menu
Route::view('/menu', 'manager.menu.index')->name('manager.menu');
Route::get('/menu/create', function () {
    return view('manager.menu.create');
});
Route::get('/menu/{id}/edit', function ($id) {
    return view('manager.menu.edit', ['id' => $id]);
})->name('manager.menu.edit');

// Loyalty
Route::view('/loyalty', 'manager.loyalty.index')->name('manager.loyalty');

// Laporan
Route::view('/laporan', 'manager.laporan.index')->name('manager.laporan');

// Pengaturan (termasuk Profil Saya sebagai tab di dalamnya)
Route::view('/pengaturan', 'pengaturan.index')->name('pengaturan');

Route::view('/dashboard', 'dashboard.index')->name('dashboard');

// SoyaScan — halaman self-order pelanggan (publik, mobile). QR meja: /scan?meja=5
Route::view('/scan', 'scan.index')->name('scan');

Route::view('/pesanan', 'kasir/pesanan')->name('pesanan');