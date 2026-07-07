<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customer'); // nullable: walk-in
            $table->foreignId('user_id')->nullable()->constrained('users'); // kasir yang memproses
            $table->string('kode_pesanan'); // contoh: #A23
            $table->string('nomor_meja')->nullable();
            $table->string('sumber'); // 'self_order' | 'kasir'
            $table->string('platform')->nullable(); // catatan manual (Shopee/GoJek/Grab), tanpa logic
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('diskon_persen')->default(0);
            $table->unsignedInteger('diskon_nilai')->default(0);
            $table->unsignedInteger('total'); // subtotal - diskon_nilai
            $table->string('metode_bayar')->nullable(); // 'cash' | 'qris'
            $table->string('status')->default('pending'); // 'pending' | 'lunas' | 'batal'
            $table->unsignedInteger('point_earned')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamp('waktu_lunas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
