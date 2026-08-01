<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override katalog redeem (M3), supaya manager bisa mengubah biaya poin
 * tiap reward tanpa ngoding (mis. Gratis Original 150 -> 200 poin).
 *
 * PENTING, tabel ini menyimpan SELISIH dari default, bukan katalognya:
 * - Struktur item (label, tipe, persen, min_subtotal, mapping menu gratis,
 *   preferensi ukuran) tetap didefinisikan di LoyaltyRedemptionCatalog,
 *   itu bagian yang menentukan PERILAKU dan tidak aman diketik bebas.
 * - Yang boleh diubah manager cuma `poin` dan `is_active`.
 *
 * Baris hanya dibuat saat sebuah kode benar-benar diedit. Kode yang tidak
 * punya baris di sini otomatis memakai nilai bawaan, jadi tabel kosong
 * berarti perilaku persis sama seperti sebelum fitur ini ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('katalog_redeem', function (Blueprint $table) {
            $table->id();
            // salah satu kunci LoyaltyRedemptionCatalog::defaults()
            $table->string('kode')->unique();
            $table->unsignedInteger('poin');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('katalog_redeem');
    }
};
