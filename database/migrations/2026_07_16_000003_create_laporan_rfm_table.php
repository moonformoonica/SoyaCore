<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot RFM statis satu periode penuh (1 Jun 2026 – 30 Jul 2026).
 * Tanpa kolom tanggal — tidak menerima filter tanggal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_rfm', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pelanggan');
            $table->integer('recency');
            $table->unsignedInteger('frequency');
            $table->unsignedInteger('monetary');
            $table->unsignedTinyInteger('r_score');
            $table->unsignedTinyInteger('f_score');
            $table->unsignedTinyInteger('m_score');
            $table->unsignedInteger('rfm_total');
            $table->string('segmen')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_rfm');
    }
};
