<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor layer reporting dari CSV bersih di database/seeders/data/.
 *
 * Idempotent: setiap tabel di-truncate lalu diisi ulang, jadi aman
 * dijalankan berkali-kali. Dipakai bersama oleh LaporanSeeder dan
 * perintah `php artisan laporan:import`.
 *
 * PEMETAAN BERDASARKAN NAMA HEADER, bukan posisi kolom. Versi lama
 * memetakan secara posisional dan itu berbahaya: begitu CSV baru
 * menyisipkan kolom di tengah, data masuk ke kolom yang salah tanpa
 * error sama sekali. Sekarang kolom dicari lewat namanya, dan header
 * yang hilang langsung melempar exception dengan pesan jelas.
 *
 * Kolom CSV yang tidak terdaftar di sini diabaikan — menambah kolom
 * baru di CSV tidak akan merusak impor.
 */
class LaporanImporter
{
    /**
     * Spesifikasi tiap tabel: kolom DB => [nama header CSV, tipe].
     * Tipe: 'str' (wajib), 'opt' (boleh kosong => null), 'int', 'float'.
     *
     * @var array<string, array{file: string, tabel: string, kolom: array<string, array{0: string, 1: string}>}>
     */
    private const SPEC = [
        'transaksi' => [
            'file' => 'Data_Transaksi_Bersih.csv',
            'tabel' => 'laporan_transaksi',
            'kolom' => [
                'kode' => ['ID Transaksi', 'str'],
                'tanggal' => ['Tanggal', 'str'],
                'platform' => ['Platform', 'opt'],
                'nama_pelanggan' => ['Nama Pelanggan', 'opt'],
                'no_wa' => ['No WhatsApp', 'opt'],
                'nama_produk' => ['Nama Produk', 'str'],
                'rasa' => ['Rasa', 'opt'],
                'ukuran' => ['Ukuran', 'opt'],
                'qty' => ['Jumlah (pcs)', 'int'],
                'harga_satuan' => ['Harga Satuan (Rp)', 'int'],
                'total' => ['Total (Rp)', 'int'],
                'poin_loyalty' => ['Poin Loyalty', 'int'],
                'catatan' => ['Catatan', 'opt'],
            ],
        ],
        'revenue_ukuran' => [
            'file' => 'Data_Revenue_Ukuran.csv',
            'tabel' => 'laporan_revenue_ukuran',
            'kolom' => [
                'ukuran' => ['Ukuran', 'str'],
                'jumlah_terjual' => ['Jumlah_Terjual', 'int'],
                'total_revenue' => ['Total_Revenue', 'int'],
                'jumlah_transaksi' => ['Jumlah_Transaksi', 'int'],
                'rata_rata_transaksi' => ['Rata_rata_Transaksi', 'int'],
            ],
        ],
        'rfm' => [
            'file' => 'Data_RFM_Pelanggan.csv',
            'tabel' => 'laporan_rfm',
            'kolom' => [
                'nama_pelanggan' => ['Nama Pelanggan', 'str'],
                'recency' => ['Recency', 'int'],
                // Frekuensi_Kedatangan = jumlah kunjungan. Jangan tertukar
                // dengan kolom "Frequency" yang berisi skor terbobot desimal.
                'frequency' => ['Frekuensi_Kedatangan', 'int'],
                'total_pcs_dibeli' => ['Total_Pcs_Dibeli', 'int'],
                'monetary' => ['Monetary', 'int'],
                'total_poin_loyalty' => ['Total_Poin_Loyalty', 'int'],
                'frequency_skor' => ['Frequency', 'float'],
                'r_score' => ['R_Score', 'int'],
                'f_score' => ['F_Score', 'int'],
                'm_score' => ['M_Score', 'int'],
                'rfm_total' => ['RFM_Total', 'int'],
                'segmen' => ['Segmen', 'str'],
            ],
        ],
        'switch' => [
            'file' => 'Data_Switch_Ukuran.csv',
            'tabel' => 'laporan_switch',
            'kolom' => [
                'nama_pelanggan' => ['Nama Pelanggan', 'str'],
                'rasa_favorit' => ['Rasa Favorit', 'opt'],
                'ukuran_saat_ini' => ['Ukuran Saat Ini', 'opt'],
                'beli_reguler' => ['Beli Reguler (pcs)', 'int'],
                'beli_large' => ['Beli Large (pcs)', 'int'],
                'beli_botol' => ['Beli Botol (pcs)', 'int'],
                'total_transaksi' => ['Total Transaksi', 'int'],
                'qty_per_kunjungan' => ['Qty per Kunjungan', 'float'],
                'total_belanja' => ['Total Belanja (Rp)', 'int'],
                'rekomendasi' => ['Rekomendasi', 'opt'],
            ],
        ],
    ];

    public function __construct(private ?string $dir = null)
    {
        $this->dir = rtrim($dir ?? database_path('seeders/data'), '/\\');
    }

    /**
     * Impor seluruh CSV. Mengembalikan jumlah baris per tabel.
     *
     * @return array<string, int>
     */
    public function import(): array
    {
        $hasil = [];

        foreach (self::SPEC as $spec) {
            $hasil[$spec['tabel']] = $this->importSatu($spec);
        }

        return $hasil;
    }

    /**
     * @param  array{file: string, tabel: string, kolom: array<string, array{0: string, 1: string}>}  $spec
     */
    private function importSatu(array $spec): int
    {
        $path = $this->dir.DIRECTORY_SEPARATOR.$spec['file'];

        if (! is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');

        try {
            $indeks = $this->petakanHeader($handle, $spec['file'], $spec['kolom']);

            DB::table($spec['tabel'])->truncate();

            $now = now();
            $buffer = [];
            $jumlah = 0;

            while (($row = fgetcsv($handle)) !== false) {
                // Baris kosong di akhir file: fgetcsv mengembalikan [null].
                if ($row === [null] || $row === []) {
                    continue;
                }

                $buffer[] = $this->petakanBaris($row, $indeks, $spec['kolom']) + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $jumlah++;

                if (count($buffer) >= 200) {
                    DB::table($spec['tabel'])->insert($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                DB::table($spec['tabel'])->insert($buffer);
            }

            return $jumlah;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Baca baris header dan tentukan indeks tiap kolom yang dibutuhkan.
     *
     * @param  resource  $handle
     * @param  array<string, array{0: string, 1: string}>  $kolom
     * @return array<string, int>
     */
    private function petakanHeader($handle, string $file, array $kolom): array
    {
        $header = fgetcsv($handle);

        if ($header === false) {
            throw new RuntimeException("File CSV kosong: {$file}");
        }

        $posisi = [];
        foreach ($header as $i => $nama) {
            $posisi[$this->normalisasi($nama)] = $i;
        }

        $indeks = [];
        $hilang = [];

        foreach ($kolom as $dbCol => [$headerCsv]) {
            $kunci = $this->normalisasi($headerCsv);

            if (! array_key_exists($kunci, $posisi)) {
                $hilang[] = $headerCsv;

                continue;
            }

            $indeks[$dbCol] = $posisi[$kunci];
        }

        if ($hilang !== []) {
            throw new RuntimeException(sprintf(
                "Kolom wajib tidak ada di %s: %s.\nHeader yang terbaca: %s.\n".
                'Perbaiki header CSV-nya, atau sesuaikan LaporanImporter::SPEC kalau nama kolomnya memang sengaja berubah.',
                $file,
                implode(', ', $hilang),
                implode(', ', array_map(trim(...), $header)),
            ));
        }

        return $indeks;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $indeks
     * @param  array<string, array{0: string, 1: string}>  $kolom
     * @return array<string, mixed>
     */
    private function petakanBaris(array $row, array $indeks, array $kolom): array
    {
        $hasil = [];

        foreach ($kolom as $dbCol => [, $tipe]) {
            $nilai = $row[$indeks[$dbCol]] ?? null;
            $nilai = $nilai === null ? '' : trim($nilai);

            $hasil[$dbCol] = match ($tipe) {
                'int' => (int) $nilai,
                'float' => (float) $nilai,
                'opt' => $nilai === '' ? null : $nilai,
                default => $nilai,
            };
        }

        return $hasil;
    }

    /**
     * Samakan header supaya beda BOM/spasi/kapital tidak bikin gagal.
     */
    private function normalisasi(?string $nama): string
    {
        $nama = str_replace("\u{FEFF}", '', (string) $nama);

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($nama)));
    }
}
