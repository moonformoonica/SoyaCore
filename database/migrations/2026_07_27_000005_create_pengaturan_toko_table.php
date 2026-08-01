<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Info toko untuk halaman Pengaturan > Info Toko, dipakai di header nota
 * dan laporan, jadi datanya harus satu sumber, bukan diketik ulang per tempat.
 *
 * SINGLETON dengan pola yang sama seperti pengaturan_loyalty: tabel berisi
 * 0 atau 1 baris, dan 0 baris berarti "pakai nilai bawaan di
 * PengaturanToko::DEFAULT_*". Tidak di-seed supaya `updated_by` tetap jujur
 * mencatat siapa yang pertama kali benar-benar menyimpan.
 *
 * jam_buka/jam_tutup TIDAK divalidasi urutannya, toko yang tutup lewat
 * tengah malam (buka 08:00, tutup 02:00) tetap sah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_toko', function (Blueprint $table) {
            $table->id();
            $table->string('nama_toko');
            $table->string('no_telepon')->nullable();
            $table->text('alamat')->nullable();
            $table->time('jam_buka')->nullable();
            $table->time('jam_tutup')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_toko');
    }
};
