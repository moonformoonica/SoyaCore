<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatalan / koreksi pesanan yang salah — BUKAN pengembalian uang.
 *
 * Karena itu penamaannya `pembatalan`, dan nilainya `nilai_dibatalkan` yang
 * artinya "penjualan sebesar ini tidak jadi", bukan "uang sebesar ini
 * dikembalikan". Tidak ada kas keluar, metode pengembalian dana, maupun
 * integrasi payment gateway di sini.
 *
 * Transaksi aslinya tidak pernah dihapus atau diubah isinya: ia hanya berubah
 * status, dan pembatalannya dicatat sebagai dokumen tersendiri supaya selalu
 * bisa ditelusuri siapa membatalkan apa, kapan, dan kenapa. Nilainya tetap
 * wajib dicatat karena omzet dashboard dan laporan kasir harus ikut terkoreksi.
 *
 * CATATAN STATUS: `transaksi.status` menerima nilai baru `batal_sebagian`.
 * Kolomnya `string` tanpa enum/check constraint, jadi tidak ada perubahan skema
 * yang perlu dilakukan untuk itu — status `batal` yang sudah ada tetap dipakai
 * untuk pembatalan penuh, dan tidak ada status baru untuk kasus itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembatalan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi');
            // Akun kasir yang MEMPROSES pembatalan — bukan pembuat penjualan
            // aslinya. Pembatalan berlebih dari satu akun adalah pola yang
            // perlu terlihat di laporan kasir.
            $table->foreignId('user_id')->constrained('users');
            // Wajib. Ini satu-satunya pagar terhadap penyalahgunaan; tanpa
            // alasan, pembatalan jadi cara menghapus penjualan tanpa jejak.
            $table->string('alasan');
            $table->unsignedInteger('nilai_dibatalkan')->default(0);
            $table->unsignedInteger('poin_ditarik')->default(0);
            $table->unsignedInteger('poin_dikembalikan')->default(0);
            $table->timestamps();
        });

        Schema::create('pembatalan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembatalan_id')->constrained('pembatalan')->cascadeOnDelete();
            $table->foreignId('detail_transaksi_id')->constrained('detail_transaksi');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('nilai_dibatalkan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembatalan_item');
        Schema::dropIfExists('pembatalan');
    }
};
