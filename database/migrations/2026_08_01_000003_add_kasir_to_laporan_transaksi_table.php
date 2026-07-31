<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel laporan sama sekali belum menyimpan kasir, sehingga export per-kasir
 * mustahil tanpa dua kolom ini.
 *
 * Kenapa dua, bukan satu:
 * - `kasir_user_id` untuk pengelompokan yang benar — dua kasir bisa bernama sama.
 * - `kasir_nama` sebagai snapshot, supaya laporan lama tidak berubah isinya
 *   kalau kasir mengganti nama atau akunnya dihapus. Pola denormalisasi yang
 *   sama sudah dipakai kolom `nama_pelanggan` di tabel ini.
 *
 * Nullable karena baris impor CSV Juni–Juli 2026 memang tidak merekam kasir —
 * itu diterima, bukan cacat. Tapi ada invarian yang dijaga dan diuji:
 * SETIAP baris berawalan `TRX-` (hasil proyeksi SoyaCore) WAJIB punya
 * `kasir_user_id`. Proyeksi hanya terjadi di `bayar()`, dan di sana selalu ada
 * user terautentikasi — baris `TRX-` ber-kasir kosong berarti ada yang bocor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_transaksi', function (Blueprint $table) {
            $table->foreignId('kasir_user_id')->nullable()->after('poin_loyalty')->constrained('users')->nullOnDelete();
            $table->string('kasir_nama')->nullable()->after('kasir_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_transaksi', function (Blueprint $table) {
            $table->dropForeign(['kasir_user_id']);
            $table->dropColumn(['kasir_user_id', 'kasir_nama']);
        });
    }
};
