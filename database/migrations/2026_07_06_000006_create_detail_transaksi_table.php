<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sesuai ERD revisi: nomor_meja, sumber, platform, diskon_persen,
     * diskon_nilai, dan catatan disimpan di level item (per baris) di sini,
     * bukan di tabel transaksi.
     */
    public function up(): void
    {
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi');
            $table->foreignId('menu_id')->constrained('menu');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('harga_satuan'); // snapshot harga menu saat transaksi
            $table->unsignedInteger('subtotal'); // qty x harga_satuan; 0 jika is_reward
            $table->boolean('is_reward')->default(false); // item gratis hasil redeem loyalty
            $table->string('nomor_meja')->nullable();
            $table->string('sumber')->default('kasir'); // 'self_order' | 'kasir'
            $table->string('platform')->nullable(); // catatan manual (Shopee/GoJek/Grab), tanpa logic
            $table->unsignedInteger('diskon_persen')->default(0);
            $table->unsignedInteger('diskon_nilai')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_transaksi');
    }
};
