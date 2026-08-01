<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor telepon user untuk halaman Pengaturan > Profil Saya.
 *
 * Nullable tanpa default: user yang sudah ada tidak punya nomor dan tidak
 * boleh dipaksa mengisi saat login berikutnya.
 *
 * Sengaja TIDAK dinormalisasi seperti customer.no_wa, nomor ini cuma untuk
 * ditampilkan di profil, bukan kunci pencarian, jadi format ketikan manager
 * ("+62 812 3456 789") dibiarkan apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_telepon')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('no_telepon');
        });
    }
};
