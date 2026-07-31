<?php

use App\Support\OpsiMinuman;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Level sugar & ice yang dipilih pelanggan, disimpan per item karena satu
 * pesanan bisa berisi Original less sugar dan Original normal sekaligus.
 *
 * Nullable, dan itu bukan kelalaian: item lama tidak punya pilihan ini,
 * kemasan botol diproduksi batch (tidak bisa diracik per pesanan), dan dessert
 * bukan minuman. Aturan ukuran mana boleh memilih apa ada di
 * {@see OpsiMinuman} — bukan di sini, dan bukan di FormRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table) {
            $table->string('level_sugar')->nullable()->after('is_reward'); // normal|less|no|extra
            $table->string('level_ice')->nullable()->after('level_sugar');
        });
    }

    public function down(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table) {
            $table->dropColumn(['level_sugar', 'level_ice']);
        });
    }
};
