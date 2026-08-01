<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kedaluwarsa poin (redeem poin v2). Tanpa ini poin adalah utang yang menumpuk
 * tanpa batas di pembukuan dan suatu hari bisa ditebus serentak.
 *
 * Diisi ulang tiap kali pelanggan bertransaksi (now() + 12 bulan), jadi yang
 * masih aktif tidak pernah kehilangan poin.
 *
 * Null punya arti: belum berlaku kedaluwarsa. Baris yang sudah ada saat
 * migrasi ini jalan sengaja dibiarkan null, poin yang dikumpulkan pelanggan
 * sebelum aturan ini ada tidak dihanguskan surut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty', function (Blueprint $table) {
            $table->timestamp('poin_kedaluwarsa_pada')->nullable()->after('poin');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty', function (Blueprint $table) {
            $table->dropColumn('poin_kedaluwarsa_pada');
        });
    }
};
