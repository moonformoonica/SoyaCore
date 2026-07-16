<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sesuai ERD revisi: tabel transaksi hanya menyimpan agregat `total`.
     * Atribut nomor_meja, sumber, platform, subtotal, diskon_persen,
     * diskon_nilai, dan catatan berada di tabel detail_transaksi.
     */
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customer'); // nullable: walk-in
            $table->foreignId('user_id')->nullable()->constrained('users'); // kasir yang memproses
            $table->string('kode_pesanan'); // contoh: #K001 (kasir) / #A23 (self-order)
            $table->unsignedInteger('total'); // agregat: SUM(detail.subtotal) - SUM(detail.diskon_nilai)
            $table->string('metode_bayar')->nullable(); // 'cash' | 'qris'
            $table->string('status')->default('pending'); // 'pending' | 'lunas' | 'batal'
            $table->unsignedInteger('point_earned')->default(0);
            $table->timestamp('waktu_lunas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
