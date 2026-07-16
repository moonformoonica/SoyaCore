<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture statis revenue per ukuran (referensi/validasi). Endpoint live
 * revenue-per-ukuran dihitung dari laporan_transaksi, bukan dari tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_revenue_ukuran', function (Blueprint $table) {
            $table->id();
            $table->string('ukuran');
            $table->unsignedInteger('jumlah_terjual');
            $table->unsignedInteger('total_revenue');
            $table->unsignedInteger('jumlah_transaksi');
            $table->unsignedInteger('rata_rata_transaksi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_revenue_ukuran');
    }
};
