<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metrik tambahan snapshot RFM (revisi data Juni–Juli 2026).
 *
 * - total_pcs_dibeli   : jumlah pcs, beda dari `frequency` yang menghitung
 *                        kunjungan (1 kunjungan bisa banyak pcs).
 * - total_poin_loyalty : akumulasi poin LoyalSeed (1 poin per Rp 1.000,
 *                        item non-minuman tidak menghasilkan poin).
 * - frequency_skor     : frekuensi terbobot = 0,6 × frequency
 *                        + 0,4 × total_pcs_dibeli. Dipakai untuk F_Score
 *                        supaya pembeli borongan tidak kalah dari pembeli
 *                        yang sering datang tapi beli sedikit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_rfm', function (Blueprint $table) {
            $table->unsignedInteger('total_pcs_dibeli')->default(0)->after('frequency');
            $table->unsignedInteger('total_poin_loyalty')->default(0)->after('monetary');
            $table->decimal('frequency_skor', 6, 1)->default(0)->after('total_poin_loyalty');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_rfm', function (Blueprint $table) {
            $table->dropColumn(['total_pcs_dibeli', 'total_poin_loyalty', 'frequency_skor']);
        });
    }
};
