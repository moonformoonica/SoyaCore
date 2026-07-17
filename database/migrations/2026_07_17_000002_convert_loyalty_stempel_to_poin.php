<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi model loyalty (M3): dari "stempel/kartu punch" (0-9, reset tiap 10)
 * menjadi POIN sebagai mata uang (1 poin per Rp 1.000, redeem via katalog).
 *
 * - stempel di-rename jadi poin (sekarang saldo poin aktual)
 * - total_gratis di-drop (redemption sekarang lewat katalog poin, bukan
 *   saldo gratis hasil stempel)
 *
 * Aman dieksekusi: tabel loyalty diverifikasi 0 baris di Supabase saat
 * migration ini dibuat (17 Jul 2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty', function (Blueprint $table) {
            $table->renameColumn('stempel', 'poin');
            $table->dropColumn('total_gratis');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty', function (Blueprint $table) {
            $table->renameColumn('poin', 'stempel');
            $table->unsignedInteger('total_gratis')->default(0);
        });
    }
};
