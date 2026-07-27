<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot rate poin di transaksi — mengikuti prinsip snapshot yang sudah
 * dipakai harga_satuan di detail_transaksi.
 *
 * Alasannya: begitu rate bisa diubah manager, `point_earned` sebuah transaksi
 * lama tidak lagi bisa diverifikasi dari total-nya saja (Rp 50.000 -> 50 poin
 * atau 5 poin tergantung rate saat itu). Kolom ini merekam rate yang BENAR-BENAR
 * dipakai saat Tandai Lunas.
 *
 * Nullable karena transaksi yang sudah lunas sebelum migration ini tidak punya
 * rekaman rate — dibaca sebagai "rate lama (Rp 1.000)".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->unsignedInteger('rupiah_per_poin')->nullable()->after('point_earned');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn('rupiah_per_poin');
        });
    }
};
