<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeder layer reporting — TERPISAH dari seeder POS live (DatabaseSeeder).
 * Impor idempotent (truncate lalu insert) dari 4 CSV bersih di
 * database/seeders/data/. Kolom dipetakan secara POSISIONAL supaya header
 * yang mengandung spasi/kurung/em-dash/BOM tidak jadi masalah.
 *
 * Jalankan: php artisan db:seed --class=LaporanSeeder
 */
class LaporanSeeder extends Seeder
{
    private const DIR = __DIR__.'/data/';

    public function run(): void
    {
        $this->importTransaksi();
        $this->importRevenueUkuran();
        $this->importRfm();
        $this->importSwitch();
    }

    private function importTransaksi(): void
    {
        DB::table('laporan_transaksi')->truncate();
        $now = now();

        $this->eachRow('Data_Transaksi_Bersih_-_Data_Transaksi_Bersih.csv', 13, function (array $buffer) use ($now) {
            $rows = [];
            foreach ($buffer as $r) {
                $rows[] = [
                    'kode' => trim($r[0]),
                    'tanggal' => trim($r[1]),
                    'platform' => $this->nullable($r[2]),
                    'nama_pelanggan' => $this->nullable($r[3]),
                    'no_wa' => $this->nullable($r[4]),
                    'nama_produk' => trim($r[5]),
                    'rasa' => $this->nullable($r[6]),
                    'ukuran' => $this->nullable($r[7]),
                    'qty' => (int) $r[8],
                    'harga_satuan' => (int) $r[9],
                    'total' => (int) $r[10],
                    'poin_loyalty' => (int) $r[11],
                    'catatan' => $this->nullable($r[12]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('laporan_transaksi')->insert($rows);
        });
    }

    private function importRevenueUkuran(): void
    {
        DB::table('laporan_revenue_ukuran')->truncate();
        $now = now();

        $this->eachRow('Data_Revenue_Ukuran_-_Data_Revenue_Ukuran.csv', 5, function (array $buffer) use ($now) {
            $rows = [];
            foreach ($buffer as $r) {
                $rows[] = [
                    'ukuran' => trim($r[0]),
                    'jumlah_terjual' => (int) $r[1],
                    'total_revenue' => (int) $r[2],
                    'jumlah_transaksi' => (int) $r[3],
                    'rata_rata_transaksi' => (int) $r[4],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('laporan_revenue_ukuran')->insert($rows);
        });
    }

    private function importRfm(): void
    {
        DB::table('laporan_rfm')->truncate();
        $now = now();

        $this->eachRow('Data_RFM_Pelanggan_-_Data_RFM_Pelanggan.csv', 9, function (array $buffer) use ($now) {
            $rows = [];
            foreach ($buffer as $r) {
                $rows[] = [
                    'nama_pelanggan' => trim($r[0]),
                    'recency' => (int) $r[1],
                    'frequency' => (int) $r[2],
                    'monetary' => (int) $r[3],
                    'r_score' => (int) $r[4],
                    'f_score' => (int) $r[5],
                    'm_score' => (int) $r[6],
                    'rfm_total' => (int) $r[7],
                    'segmen' => trim($r[8]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('laporan_rfm')->insert($rows);
        });
    }

    private function importSwitch(): void
    {
        DB::table('laporan_switch')->truncate();
        $now = now();

        $this->eachRow('Data_Switch_Ukuran_-_Data_Switch_Ukuran.csv', 10, function (array $buffer) use ($now) {
            $rows = [];
            foreach ($buffer as $r) {
                $rows[] = [
                    'nama_pelanggan' => trim($r[0]),
                    'rasa_favorit' => $this->nullable($r[1]),
                    'ukuran_saat_ini' => $this->nullable($r[2]),
                    'beli_reguler' => (int) $r[3],
                    'beli_large' => (int) $r[4],
                    'beli_botol' => (int) $r[5],
                    'total_transaksi' => (int) $r[6],
                    'qty_per_kunjungan' => (float) $r[7],
                    'total_belanja' => (int) $r[8],
                    'rekomendasi' => $this->nullable($r[9]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('laporan_switch')->insert($rows);
        });
    }

    /**
     * Buka CSV, buang header, dan panggil $insert per chunk (200 baris).
     * $minCols menyaring baris rusak/kosong (kurang dari jumlah kolom wajib).
     */
    private function eachRow(string $file, int $minCols, callable $insert): void
    {
        $path = self::DIR.$file;
        if (! is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // buang header

        $buffer = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < $minCols) {
                continue; // lewati baris kosong/rusak
            }
            $buffer[] = $row;
            if (count($buffer) >= 200) {
                $insert($buffer);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            $insert($buffer);
        }
        fclose($handle);
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
