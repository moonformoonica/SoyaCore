<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuka `maks_potongan` dan `min_subtotal` ke manager (redeem poin v2).
 * Keduanya keputusan bisnis harian yang sebelumnya cuma bisa diubah lewat
 * deploy, sama seperti `poin` dan `is_active` yang sudah dibuka lebih dulu.
 *
 * Tetap mengikuti pola tabel ini: yang disimpan SELISIH dari default, bukan
 * katalognya. Null = ikut nilai bawaan LoyaltyRedemptionCatalog::defaults().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('katalog_redeem', function (Blueprint $table) {
            $table->unsignedInteger('maks_potongan')->nullable()->after('poin');
            $table->unsignedInteger('min_subtotal')->nullable()->after('maks_potongan');
        });
    }

    public function down(): void
    {
        Schema::table('katalog_redeem', function (Blueprint $table) {
            $table->dropColumn(['maks_potongan', 'min_subtotal']);
        });
    }
};
