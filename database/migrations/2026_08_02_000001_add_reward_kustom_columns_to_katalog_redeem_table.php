<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuka pembuatan JENIS reward baru dari halaman Loyalty.
 *
 * Sampai sekarang tabel ini cuma menyimpan SELISIH dari
 * LoyaltyRedemptionCatalog::defaults(): manager boleh mengubah poin dan
 * mematikan reward, tapi delapan jenis yang ada adalah semuanya yang pernah
 * bisa ada. Menambah satu reward promo berarti menunggu deploy.
 *
 * Kolom di bawah membuat satu baris bisa berdiri sendiri sebagai definisi
 * reward, bukan cuma override. Yang membedakan keduanya `is_custom`:
 *
 * - `is_custom = false` : baris override milik kode bawaan, seperti sebelumnya.
 *   Kolom-kolom baru dibiarkan null dan strukturnya tetap dibaca dari kode.
 * - `is_custom = true`  : reward buatan manager. Kode PHP tidak tahu apa-apa
 *   soal baris ini, jadi seluruh strukturnya harus ada di sini.
 *
 * Nullable semua, jadi baris override yang sudah ada tidak perlu disentuh dan
 * tetap terbaca persis seperti sebelum migrasi ini jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('katalog_redeem', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('kode');
            $table->string('label')->nullable()->after('is_custom');
            // 'diskon' atau 'gratis_menu', sama dengan tipe di defaults().
            $table->string('tipe')->nullable()->after('label');
            $table->unsignedTinyInteger('persen')->nullable()->after('tipe');

            // Tiga kolom ini yang dipakai LoyaltyService mencari menu hadiahnya
            // saat redeem. Disimpan sebagai nama, bukan `menu_id`, mengikuti
            // bentuk defaults(): menu yang dihapus lalu dibuat ulang dengan
            // nama sama tetap cocok, dan pencocokannya case-insensitive.
            $table->string('kategori')->nullable()->after('persen');
            $table->string('menu')->nullable()->after('kategori');
            // Daftar ejaan ukuran yang diterima, urut preferensi.
            $table->json('ukuran')->nullable()->after('menu');
        });
    }

    public function down(): void
    {
        Schema::table('katalog_redeem', function (Blueprint $table) {
            $table->dropColumn(['is_custom', 'label', 'tipe', 'persen', 'kategori', 'menu', 'ukuran']);
        });
    }
};
