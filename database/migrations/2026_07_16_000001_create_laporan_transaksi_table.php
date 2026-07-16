<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layer reporting TERPISAH dari tabel POS live. Diisi dari CSV historis
 * yang sudah dibersihkan (database/seeders/data). Satu baris = satu
 * transaksi satu item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();           // "ID Transaksi"
            $table->date('tanggal')->index();            // kunci filter waktu
            $table->string('platform')->nullable()->index(); // campur metode bayar + channel; simpan mentah
            $table->string('nama_pelanggan')->nullable();
            $table->string('no_wa')->nullable();
            $table->string('nama_produk');
            $table->string('rasa')->nullable();
            $table->string('ukuran')->nullable()->index();
            $table->unsignedInteger('qty');              // "Jumlah (pcs)"
            $table->unsignedInteger('harga_satuan');     // "Harga Satuan (Rp)"
            $table->unsignedInteger('total');            // "Total (Rp)"
            $table->unsignedInteger('poin_loyalty')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_transaksi');
    }
};
