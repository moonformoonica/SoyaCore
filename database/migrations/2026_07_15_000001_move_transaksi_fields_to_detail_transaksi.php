<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi ERD 15 Juli 2026 (keputusan Monica): kolom nomor_meja, sumber,
 * platform, diskon_persen, diskon_nilai, catatan, dan subtotal dipindah
 * dari `transaksi` ke `detail_transaksi`. Tabel `transaksi` hanya
 * menyimpan `total` sebagai agregat uang.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table) {
            $table->string('nomor_meja')->nullable()->after('is_reward');
            // default 'kasir' dibutuhkan agar ALTER TABLE aman di SQLite (testing)
            $table->string('sumber')->default('kasir')->after('nomor_meja'); // 'self_order' | 'kasir'
            $table->string('platform')->nullable()->after('sumber'); // Shopee | GoJek | Grab
            $table->unsignedInteger('diskon_persen')->default(0)->after('platform');
            $table->unsignedInteger('diskon_nilai')->default(0)->after('diskon_persen');
            $table->text('catatan')->nullable()->after('diskon_nilai');
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_meja',
                'sumber',
                'platform',
                'subtotal',
                'diskon_persen',
                'diskon_nilai',
                'catatan',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->string('nomor_meja')->nullable();
            $table->string('sumber')->default('kasir');
            $table->string('platform')->nullable();
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('diskon_persen')->default(0);
            $table->unsignedInteger('diskon_nilai')->default(0);
            $table->text('catatan')->nullable();
        });

        Schema::table('detail_transaksi', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_meja',
                'sumber',
                'platform',
                'diskon_persen',
                'diskon_nilai',
                'catatan',
            ]);
        });
    }
};
