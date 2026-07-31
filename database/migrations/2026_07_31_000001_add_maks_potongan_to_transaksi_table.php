<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plafon potongan yang berlaku di sebuah transaksi (redeem poin v2).
 *
 * Kenapa perlu disimpan, bukan cukup dihitung saat redeem: recalculateTotals()
 * menurunkan ulang diskon dari persen tiap kali item transaksi berubah. Tanpa
 * plafon yang ikut tersimpan, kasir yang menambah item setelah pelanggan
 * redeem membuat potongan ikut membengkak melewati plafonnya — persis cacat
 * yang mau ditutup.
 *
 * Null = transaksi tanpa redeem berplafon (termasuk semua transaksi lama dan
 * diskon manual kasir, yang memang sengaja tidak diberi plafon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->unsignedInteger('maks_potongan')->nullable()->after('poin_ditukar');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn('maks_potongan');
        });
    }
};
