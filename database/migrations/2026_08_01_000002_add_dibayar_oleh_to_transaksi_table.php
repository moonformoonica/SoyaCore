<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan dua peran kasir yang sebelumnya berebut satu kolom.
 *
 * Sebelum ini `bayar()` menimpa `transaksi.user_id` dengan akun yang menandai
 * lunas. Pada terminal yang dipakai satu akun, penimpaan itu tidak berakibat
 * apa-apa. Celahnya muncul tepat pada skenario pergantian akun: Kasir 1
 * membuat pesanan pukul 13.55 lalu logout, Kasir 2 login, pelanggan membayar
 * pukul 14.05, transaksi tercatat seolah Kasir 1 tidak pernah menyentuhnya,
 * tanpa error apa pun yang muncul.
 *
 * - `user_id`      = akun kasir PEMBUAT pesanan, tidak pernah ditimpa lagi.
 * - `dibayar_oleh` = akun kasir yang MENYELESAIKAN pembayaran.
 *
 * Nullable karena transaksi `pending` belum dibayar siapa pun, dan pesanan
 * SoyaScan sebaliknya: `user_id` null (tidak ada kasir pembuat) tapi
 * `dibayar_oleh` terisi saat kasir menerimanya di konter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->foreignId('dibayar_oleh')->nullable()->after('user_id')->constrained('users');
        });

        // Baris lunas yang sudah ada: `user_id`-nya memang berisi akun
        // penyelesai (itu yang ditulis `bayar()` versi lama), jadi menyalinnya
        // ke kolom baru membuat laporan kasir langsung benar untuk data yang
        // sudah ada. Yang tidak bisa dipulihkan hanyalah siapa pembuatnya,
        // informasi itu memang sudah tertimpa sebelum migrasi ini ada.
        DB::table('transaksi')
            ->where('status', 'lunas')
            ->whereNotNull('user_id')
            ->update(['dibayar_oleh' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropForeign(['dibayar_oleh']);
            $table->dropColumn('dibayar_oleh');
        });
    }
};
