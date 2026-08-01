<?php

namespace App\Console\Commands;

use App\Models\LaporanTransaksi;
use App\Models\Transaksi;
use App\Services\LaporanProjector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Jaring pengaman untuk layer laporan: membangun ulang proyeksi seluruh
 * transaksi yang sudah jadi penjualan.
 *
 * Dibutuhkan kalau proyeksi pernah gagal (mis. deploy di tengah transaksi) atau
 * rumusnya berubah. Aman dijalankan berkali-kali, hasilnya selalu sama.
 *
 * TIDAK menyentuh baris impor CSV historis: yang dihapus dan ditulis ulang
 * hanya baris berawalan `TRX-`.
 */
class ProyeksiUlangLaporan extends Command
{
    protected $signature = 'laporan:proyeksi-ulang';

    protected $description = 'Bangun ulang proyeksi transaksi POS ke laporan_transaksi (tidak menyentuh data CSV historis).';

    public function handle(LaporanProjector $projector): int
    {
        $transaksi = Transaksi::query()
            ->whereNotNull('waktu_lunas')
            ->whereIn('status', ['lunas', 'batal_sebagian'])
            ->orderBy('id')
            ->get();

        $bar = $this->output->createProgressBar($transaksi->count());

        DB::transaction(function () use ($transaksi, $projector, $bar) {
            // Dihapus lebih dulu supaya baris milik transaksi yang sudah tidak
            // layak lagi (dibatalkan penuh setelah proyeksi lama dibuat) ikut
            // hilang, bukan tertinggal sebagai omzet hantu.
            $dihapus = LaporanTransaksi::query()
                ->where('kode', 'like', LaporanTransaksi::PREFIX_POS.'%')
                ->delete();

            $this->newLine();
            $this->line("Baris proyeksi lama dihapus: {$dihapus}");
            $bar->start();

            foreach ($transaksi as $t) {
                $projector->sinkronkan($t);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $baris = LaporanTransaksi::query()
            ->where('kode', 'like', LaporanTransaksi::PREFIX_POS.'%')
            ->count();

        $this->info("Selesai: {$transaksi->count()} transaksi diproyeksikan menjadi {$baris} baris laporan.");

        return self::SUCCESS;
    }
}
