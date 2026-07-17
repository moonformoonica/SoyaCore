<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 LoyalSeed: kolom pendukung redeem poin + guard idempotency earning.
 * kode_pesanan, metode_bayar, dan total sudah ada sejak M1/M2 — hanya
 * menambah yang belum ada + index kode_pesanan (non-unique, kode boleh
 * berulang di hari berbeda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            // dicek sebelum poin ditambahkan — Tandai Lunas 2x tidak boleh
            // menambah poin 2x
            $table->timestamp('loyalty_applied_at')->nullable();
            // salah satu kode katalog redeem, null = tidak redeem apa pun
            $table->string('kode_redeem')->nullable();
            $table->unsignedInteger('poin_ditukar')->nullable()->default(0);

            $table->index('kode_pesanan');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropIndex(['kode_pesanan']);
            $table->dropColumn(['loyalty_applied_at', 'kode_redeem', 'poin_ditukar']);
        });
    }
};
