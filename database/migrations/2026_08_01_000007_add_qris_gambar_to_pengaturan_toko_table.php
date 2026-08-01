<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QRIS statis milik merchant, untuk ditampilkan di halaman pembayaran SoyaScan.
 *
 * Menyimpan PATH pada disk `public`, bukan berkasnya, supaya baris pengaturan
 * tetap ringan dan berkasnya bisa dilayani langsung oleh web server lewat
 * `php artisan storage:link`.
 *
 * Backend tidak memvalidasi, membaca, atau memproses pembayaran apa pun dari
 * gambar ini. Ia betul-betul hanya gambar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_toko', function (Blueprint $table) {
            $table->string('qris_gambar')->nullable()->after('jam_tutup');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_toko', function (Blueprint $table) {
            $table->dropColumn('qris_gambar');
        });
    }
};
