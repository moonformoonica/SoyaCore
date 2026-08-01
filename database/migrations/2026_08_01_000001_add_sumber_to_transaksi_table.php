<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Channel asal pesanan (kasir vs SoyaScan) naik ke level transaksi.
 *
 * `detail_transaksi.sumber` sudah ada, tapi ia menjawab pertanyaan lain: item
 * mana yang datang dari mana. Satu transaksi selalu berasal dari SATU channel,
 * dan menurunkannya dari item memaksa query anak di setiap baris daftar
 * transaksi manager. Kolom di sini membuat filter `?sumber=` menjadi satu
 * WHERE biasa.
 *
 * `detail_transaksi.sumber` TETAP tinggal, LoyaltyService memakainya saat
 * membuat item reward supaya item gratis ikut menandai channel asalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->string('sumber')->default('kasir')->after('user_id'); // 'kasir' | 'self_order'
        });

        // Backfill baris lama: turunkan dari item pertama transaksi itu.
        // Transaksi tanpa item (pesanan yang ditinggalkan kasir) jatuh ke
        // 'kasir', SoyaScan tidak pernah membuat transaksi kosong.
        DB::statement(<<<'SQL'
            UPDATE transaksi
            SET sumber = COALESCE((
                SELECT d.sumber
                FROM detail_transaksi d
                WHERE d.transaksi_id = transaksi.id
                ORDER BY d.id
                LIMIT 1
            ), 'kasir')
        SQL);
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
