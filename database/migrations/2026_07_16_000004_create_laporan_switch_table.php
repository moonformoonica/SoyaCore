<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekomendasi switch ukuran, statis satu periode penuh
 * (1 Jun 2026 – 30 Jul 2026). Tanpa filter tanggal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_switch', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pelanggan');
            $table->string('rasa_favorit')->nullable();
            $table->string('ukuran_saat_ini')->nullable();
            $table->unsignedInteger('beli_reguler');
            $table->unsignedInteger('beli_large');
            $table->unsignedInteger('beli_botol');
            $table->unsignedInteger('total_transaksi');
            $table->decimal('qty_per_kunjungan', 4, 1);
            $table->unsignedInteger('total_belanja');
            $table->string('rekomendasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_switch');
    }
};
