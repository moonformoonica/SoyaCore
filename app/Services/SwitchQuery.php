<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use App\Support\GolonganUkuran;
use Illuminate\Support\Carbon;

/**
 * Rekomendasi switch/upsell ukuran, DIHITUNG dari `laporan_transaksi`, bukan
 * dibaca dari snapshot `laporan_switch`.
 *
 * Alasannya sama seperti {@see RfmQuery}: `laporan_transaksi` sudah memuat CSV
 * historis Juni-Juli DAN proyeksi transaksi POS yang ditulis sinkron begitu
 * kasir menandai lunas, jadi hasilnya otomatis historis sekaligus ikut bergerak
 * saat ada transaksi baru selesai.
 *
 * KHUSUS MINUMAN. Dessert dan cookies (`ukuran` kosong, golongan `lainnya`)
 * dikeluarkan dari seluruh hitungan. Rekomendasinya soal naik ukuran gelas ke
 * botol, dan sepotong cookies tidak punya padanan ukuran, jadi memasukkannya
 * cuma menggelembungkan "qty per kunjungan" pelanggan yang gemar dessert lalu
 * menyarankan botol 1 liter kepada orang yang tidak pernah membeli minuman.
 *
 * ATURAN, hasil rekayasa balik dari snapshot Kamila:
 *
 * - Hanya pelanggan yang BELUM PERNAH membeli botol yang masuk daftar. Yang
 *   sudah beralih ke botol tidak perlu ditawari beralih lagi.
 * - Ukuran dominannya (Reguler atau Large) minimal 3 pcs, supaya sarannya
 *   berdasar kebiasaan, bukan satu dua pembelian.
 * - Dari situ: yang tiap datang membeli banyak (>= 2 pcs per kunjungan)
 *   diarahkan ke botol 1L, sisanya dinaikkan satu tingkat, Reguler ke Large
 *   dan Large ke botol 500ml.
 *
 * BEDANYA DENGAN SNAPSHOT LAMA, dan ini disengaja. Diuji terhadap 35 baris
 * `laporan_switch`: SELURUH 35 pelanggan itu tetap muncul, dengan
 * `beli_reguler`, `beli_large`, `beli_botol`, `total_transaksi`, dan
 * `total_belanja` yang cocok 100%. Dua hal tetap berbeda dan tidak bisa
 * disamakan dari data yang ada:
 *
 * 1. Daftarnya jadi lebih panjang (55, bukan 35). Aturan di atas diterapkan
 *    rata ke semua pelanggan, sementara 35 baris snapshot adalah pilihan
 *    tangan: ada pelanggan berprofil identik yang satu masuk dan satu tidak
 *    (mis. Anggi reg=3 trx=3 masuk, Tata reg=3 trx=3 tidak). Tidak ada aturan
 *    yang bisa memisahkan keduanya, jadi yang dipakai aturan yang konsisten.
 * 2. `qty_per_kunjungan` dan `rasa_favorit` meleset di beberapa baris karena
 *    snapshot dihitung pipeline spreadsheet terpisah dengan pembulatan dan
 *    sumber kolom yang tidak ikut terbawa saat impor.
 */
class SwitchQuery
{
    /** Minimal pcs pada ukuran dominan sebelum seorang pelanggan disarankan. */
    private const MIN_PCS = 3;

    /** Ambang "beli banyak sekaligus", dalam pcs per kunjungan. */
    private const AMBANG_BORONG = 2.0;

    private const REKOMENDASI = [
        'large' => 'Tawarkan naik ke Large, sudah sering beli Reguler, waktunya naik kelas',
        'botol_500' => 'Tawarkan Botol 500ml, sudah langganan Large, saatnya coba ukuran botol',
        'borong_reguler' => 'Tawarkan Botol 1L, pelanggan ini biasa beli banyak sekaligus, lebih hemat pakai botol',
        'borong_large' => 'Tawarkan Botol 1L, volume belanjanya sudah besar tiap kunjungan',
    ];

    /**
     * @return list<array<string, mixed>> Terurut total belanja menurun lalu nama.
     */
    public function semua(): array
    {
        $hasil = [];

        foreach ($this->agregat() as $b) {
            $reguler = $b['reguler'];
            $large = $b['large'];
            $botol = $b['botol'];

            // Sudah pernah botol, atau kebiasaannya belum cukup terbentuk.
            if ($botol > 0 || max($reguler, $large) < self::MIN_PCS) {
                continue;
            }

            $transaksi = count($b['transaksi']);

            $qtyPerKunjungan = $transaksi > 0 ? round($b['qty'] / $transaksi, 1) : 0.0;
            $dominan = $large > $reguler ? 'Large' : 'Reguler';

            $hasil[] = [
                'nama_pelanggan' => $b['nama_pelanggan'],
                'rasa_favorit' => $this->terbanyak($b['rasa']),
                'ukuran_saat_ini' => $dominan,
                'beli_reguler' => $reguler,
                'beli_large' => $large,
                'beli_botol' => $botol,
                'total_transaksi' => $transaksi,
                'qty_per_kunjungan' => $qtyPerKunjungan,
                'total_belanja' => $b['belanja'],
                'rekomendasi' => $this->rekomendasi($dominan, $qtyPerKunjungan),
            ];
        }

        usort($hasil, fn ($a, $b) => [$b['total_belanja'], $a['nama_pelanggan']] <=> [$a['total_belanja'], $b['nama_pelanggan']]);

        return $hasil;
    }

    private function rekomendasi(string $dominan, float $qtyPerKunjungan): string
    {
        $borong = $qtyPerKunjungan >= self::AMBANG_BORONG;

        return match (true) {
            $dominan === 'Large' && $borong => self::REKOMENDASI['borong_large'],
            $dominan === 'Large' => self::REKOMENDASI['botol_500'],
            $borong => self::REKOMENDASI['borong_reguler'],
            default => self::REKOMENDASI['large'],
        };
    }

    /**
     * Satu baris per pelanggan, khusus baris minuman.
     *
     * @return list<array<string, mixed>>
     */
    private function agregat(): array
    {
        $baris = [];

        foreach (LaporanTransaksi::query()->cursor() as $row) {
            $nama = trim((string) $row->nama_pelanggan);
            if ($nama === '') {
                continue;
            }

            $golongan = GolonganUkuran::dari($row->ukuran);
            if ($golongan === GolonganUkuran::LAINNYA) {
                continue; // dessert & cookies tidak punya padanan ukuran
            }

            $baris[$nama] ??= [
                'nama_pelanggan' => $nama,
                'reguler' => 0,
                'large' => 0,
                'botol' => 0,
                'qty' => 0,
                'belanja' => 0,
                'rasa' => [],
                'transaksi' => [],
            ];

            $qty = (int) $row->qty;
            $ukuran = mb_strtolower(trim((string) $row->ukuran));

            // `Hot` dan `Reguler` sama-sama gelas ukuran standar, jadi
            // dihitung sebagai satu kelompok. Memisahkannya membuat pelanggan
            // yang selalu memesan Hot tidak pernah mencapai ambang mana pun.
            $kelompok = match (true) {
                $golongan === GolonganUkuran::BOTOL => 'botol',
                $ukuran === 'large' => 'large',
                default => 'reguler',
            };

            $baris[$nama][$kelompok] += $qty;
            $baris[$nama]['qty'] += $qty;
            $baris[$nama]['belanja'] += (int) $row->total;
            $baris[$nama]['transaksi'][$this->kunciTransaksi((string) $row->kode)] = true;

            // Rasa favorit memakai NAMA PRODUK, bukan kolom `rasa`. Kolom
            // `rasa` berisi komposisi panjang ("Soya Original Premium + Taro
            // Premium + Brown Sugar") yang tidak terbaca sebagai nama menu di
            // tabel rekomendasi.
            $produk = $this->namaMenu((string) $row->nama_produk);
            if ($produk !== '') {
                $baris[$nama]['rasa'][$produk] = ($baris[$nama]['rasa'][$produk] ?? 0) + $qty;
            }
        }

        return array_values($baris);
    }

    /**
     * Nama produk di `laporan_transaksi` ditulis "Soya Choco Maniac", sementara
     * tabel rekomendasi menampilkan rasanya saja ("Choco Maniac"). Awalan itu
     * dibuang di sini, bukan di frontend, supaya export Excel dan halaman web
     * tidak bisa berbeda ejaan.
     */
    private function namaMenu(string $namaProduk): string
    {
        $bersih = trim($namaProduk);

        return trim(preg_replace('/^Soya\s+/i', '', $bersih) ?? $bersih);
    }

    /**
     * @param  array<string, int>  $hitungan
     */
    private function terbanyak(array $hitungan): ?string
    {
        if ($hitungan === []) {
            return null;
        }

        // Diurutkan nama saat qty-nya seri, supaya hasilnya tidak berubah-ubah
        // antar pemanggilan hanya karena urutan baris di database berbeda.
        ksort($hitungan);
        arsort($hitungan);

        return array_key_first($hitungan);
    }

    private function kunciTransaksi(string $kode): string
    {
        if (! str_starts_with($kode, LaporanTransaksi::PREFIX_POS)) {
            return $kode;
        }

        $bagian = explode('-', $kode);

        return $bagian[0].'-'.($bagian[1] ?? '');
    }

    /** Rentang tanggal data yang dihitung, untuk label periode di UI. */
    public function periode(): ?string
    {
        $awal = LaporanTransaksi::query()->min('tanggal');
        $akhir = LaporanTransaksi::query()->max('tanggal');

        if ($awal === null) {
            return null;
        }

        return Carbon::parse($awal)->translatedFormat('j M Y')
            .' - '.Carbon::parse($akhir)->translatedFormat('j M Y');
    }
}
