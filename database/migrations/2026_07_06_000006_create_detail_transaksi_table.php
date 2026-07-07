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
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi');
            $table->foreignId('menu_id')->constrained('menu');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('harga_satuan'); // snapshot harga menu saat transaksi
            $table->unsignedInteger('subtotal'); // qty x harga_satuan; 0 jika is_reward
            $table->boolean('is_reward')->default(false); // item gratis hasil redeem loyalty
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
