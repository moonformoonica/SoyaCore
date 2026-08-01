<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan Loyalty (M3), memindahkan rate poin dari hardcode
 * LoyaltyService (intdiv(total, 1000)) ke tabel yang bisa diedit manager.
 *
 * Tabel ini SINGLETON: hanya pernah berisi 0 atau 1 baris.
 * - 0 baris  = belum pernah diubah  -> pakai default kode (Rp 1.000/poin),
 *              jadi deploy migration ini TIDAK mengubah perilaku apa pun.
 * - 1 baris  = manager sudah menyetel rate.
 *
 * Sengaja tidak di-seed supaya "nilai bawaan" tetap satu sumber di
 * PengaturanLoyalty::RUPIAH_PER_POIN_DEFAULT, bukan tercecer di migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_loyalty', function (Blueprint $table) {
            $table->id();
            // berapa rupiah belanja untuk dapat 1 poin (pembagi, bukan pengali)
            $table->unsignedInteger('rupiah_per_poin')->default(1000);
            // audit: setting ini berdampak ke uang, jadi perlu bisa ditelusuri
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_loyalty');
    }
};
