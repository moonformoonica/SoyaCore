<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERUBAHAN KONTRAK: nomor meja dihapus dari SoyaScan sepenuhnya, termasuk
 * kolomnya.
 *
 * Boleh dihapus permanen karena SoyaScan masih dalam revisi dan belum berjalan
 * produksi — tidak ada riwayat pesanan asli yang perlu dijaga. Kalau nanti
 * ternyata dibutuhkan lagi, ia kembali sebagai kolom baru, bukan sebagai data
 * yang dipulihkan.
 *
 * `down()` mengembalikan kolomnya supaya rollback tetap sah secara skema.
 * Datanya memang tidak kembali, dan itu diterima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table) {
            $table->dropColumn('nomor_meja');
        });
    }

    public function down(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table) {
            $table->string('nomor_meja')->nullable()->after('level_ice');
        });
    }
};
