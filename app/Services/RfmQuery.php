<?php

namespace App\Services;

use App\Models\LaporanTransaksi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RFM dihitung dari `laporan_transaksi`, BUKAN dibaca dari snapshot
 * `laporan_rfm`.
 *
 * KENAPA DIHITUNG. `laporan_transaksi` sudah memuat dua sumber sekaligus:
 * baris impor CSV Juni-Juli (`kode` berawalan `TR-`) dan proyeksi transaksi POS
 * SoyaCore (`TRX-`), yang ditulis sinkron begitu kasir menandai lunas. Membaca
 * tabel itu membuat RFM otomatis historis DAN ikut bergerak tiap ada transaksi
 * baru selesai. Snapshot `laporan_rfm` tidak pernah berubah setelah di-impor,
 * jadi pelanggan yang baru belanja hari ini tidak pernah muncul di sana.
 *
 * FORMULANYA DIREKAYASA BALIK DARI SNAPSHOT KAMILA, dan cocok 345 dari 345
 * baris. Jangan diubah tanpa alasan: angka di sini harus tetap bisa
 * dibandingkan dengan analisis yang sudah pernah dibuat.
 *
 * - Recency  = (tanggal transaksi TERAKHIR di seluruh data + 1 hari) - tanggal
 *   transaksi terakhir pelanggan itu. Acuannya ujung data, bukan hari ini,
 *   sesuai konvensi RFM: dataset yang belum diperbarui tidak boleh membuat
 *   semua pelanggan terlihat makin lama tidak datang.
 * - Frequency = jumlah TRANSAKSI unik, bukan jumlah baris. Satu transaksi POS
 *   tersebar ke beberapa baris item, jadi menghitung baris akan menggelembungkan
 *   frequency pelanggan POS dibanding pelanggan CSV.
 * - Monetary = jumlah `total`.
 *
 * Skor 1-4 memakai kuartil berbasis PERINGKAT (jumlah anggota tiap kelompok
 * dibuat serata mungkin), sama seperti `qcut` di pandas yang dipakai Kamila.
 */
class RfmQuery
{
    /** Total belanja minimum untuk masuk segmen Loyal. */
    private const AMBANG_LOYAL = 150000;

    public const SEGMEN = ['Pelanggan Baru', 'Butuh Perhatian', 'Potensial', 'Loyal'];

    /**
     * @return list<array<string, mixed>> Terurut `rfm_total` menurun lalu nama.
     */
    public function semua(): array
    {
        $baris = $this->agregat();

        if ($baris === []) {
            return [];
        }

        $acuan = $this->tanggalAcuan();

        foreach ($baris as &$b) {
            // Arah pengurangannya penting: `diffInDays` mengembalikan nilai
            // BERTANDA, jadi `$acuan->diffInDays($terakhir)` menghasilkan angka
            // negatif dan seluruh peringkat recency terbalik.
            $b['recency'] = (int) Carbon::parse($b['terakhir'])->startOfDay()->diffInDays($acuan);
        }
        unset($b);

        // Recency dibalik: makin kecil jaraknya, makin tinggi skornya.
        $rSkor = $this->skorKuartil(array_map(fn ($b) => -$b['recency'], $baris));
        $fSkor = $this->skorKuartil(array_column($baris, 'frequency'));
        $mSkor = $this->skorKuartil(array_column($baris, 'monetary'));

        $hasil = [];
        foreach ($baris as $i => $b) {
            $r = $rSkor[$i];
            $f = $fSkor[$i];
            $m = $mSkor[$i];

            $hasil[] = [
                'nama_pelanggan' => $b['nama_pelanggan'],
                'recency' => $b['recency'],
                'frequency' => $b['frequency'],
                'monetary' => $b['monetary'],
                'total_poin_loyalty' => $b['total_poin_loyalty'],
                'r_score' => $r,
                'f_score' => $f,
                'm_score' => $m,
                'rfm_total' => $r + $f + $m,
                'segmen' => $this->segmen($b['frequency'], $r, $b['monetary']),
            ];
        }

        usort($hasil, fn ($a, $b) => [$b['rfm_total'], $a['nama_pelanggan']] <=> [$a['rfm_total'], $b['nama_pelanggan']]);

        return $hasil;
    }

    /**
     * Aturan segmen hasil rekayasa balik. Urutannya mengikat: pelanggan yang
     * lama tidak datang masuk "Butuh Perhatian" lebih dulu, sebesar apa pun
     * belanjanya, karena itulah yang perlu ditindaklanjuti.
     */
    private function segmen(int $frequency, int $rSkor, int $monetary): string
    {
        return match (true) {
            $frequency <= 1 => 'Pelanggan Baru',
            $rSkor <= 2 => 'Butuh Perhatian',
            $monetary >= self::AMBANG_LOYAL => 'Loyal',
            default => 'Potensial',
        };
    }

    /**
     * Satu baris per pelanggan.
     *
     * `frequency` menghitung transaksi unik. Untuk baris POS, `kode` berbentuk
     * `TRX-12-345` (transaksi 12, detail 345), jadi bagian sebelum segmen
     * terakhir yang menjadi identitas transaksinya. Baris CSV punya `kode`
     * unik per transaksi sehingga dipakai apa adanya.
     *
     * @return list<array<string, mixed>>
     */
    private function agregat(): array
    {
        $baris = [];

        foreach (LaporanTransaksi::query()->cursor() as $row) {
            $nama = trim((string) $row->nama_pelanggan);
            if ($nama === '') {
                continue; // baris tanpa identitas tidak bisa dimasukkan ke pelanggan mana pun
            }

            $baris[$nama] ??= [
                'nama_pelanggan' => $nama,
                'terakhir' => null,
                'monetary' => 0,
                'total_poin_loyalty' => 0,
                'transaksi' => [],
            ];

            $tanggal = $row->tanggal?->toDateString();
            if ($tanggal !== null && ($baris[$nama]['terakhir'] === null || $tanggal > $baris[$nama]['terakhir'])) {
                $baris[$nama]['terakhir'] = $tanggal;
            }

            $baris[$nama]['monetary'] += (int) $row->total;
            $baris[$nama]['total_poin_loyalty'] += (int) $row->poin_loyalty;
            $baris[$nama]['transaksi'][$this->kunciTransaksi((string) $row->kode)] = true;
        }

        $hasil = [];
        foreach ($baris as $b) {
            if ($b['terakhir'] === null) {
                continue;
            }

            $b['frequency'] = count($b['transaksi']);
            unset($b['transaksi']);
            $hasil[] = $b;
        }

        return $hasil;
    }

    private function kunciTransaksi(string $kode): string
    {
        if (! str_starts_with($kode, LaporanTransaksi::PREFIX_POS)) {
            return $kode;
        }

        $bagian = explode('-', $kode);

        return $bagian[0].'-'.($bagian[1] ?? '');
    }

    /**
     * Hari setelah transaksi terakhir di seluruh data. Ini yang membuat
     * pelanggan yang belanja di hari terbaru memperoleh `recency = 1`, bukan 0,
     * sama seperti snapshot aslinya.
     */
    private function tanggalAcuan(): Carbon
    {
        $maks = LaporanTransaksi::query()->max('tanggal');

        return Carbon::parse($maks)->startOfDay()->addDay();
    }

    /**
     * Skor 1-4 berbasis peringkat, anggota tiap kelompok dibuat serata mungkin.
     *
     * Memotong berdasarkan NILAI tidak bisa dipakai di sini: `frequency`
     * didominasi angka 1, sehingga potongan berbasis nilai menaruh lebih dari
     * separuh pelanggan di satu kelompok dan tiga kelompok sisanya nyaris
     * kosong.
     *
     * @param  list<int|float>  $nilai
     * @return list<int> Skor pada urutan yang sama dengan masukannya.
     */
    private function skorKuartil(array $nilai): array
    {
        $jumlah = count($nilai);

        $urut = array_keys($nilai);
        usort($urut, fn ($a, $b) => $nilai[$a] <=> $nilai[$b] ?: $a <=> $b);

        $skor = [];
        foreach ($urut as $peringkat => $indeks) {
            // Peringkat 0 sampai n-1 dibagi empat; +1 supaya skornya 1..4.
            $skor[$indeks] = (int) min(4, intdiv($peringkat * 4, $jumlah) + 1);
        }

        ksort($skor);

        return array_values($skor);
    }

    /**
     * Jumlah pelanggan per segmen, dipakai kartu ringkasan dan donut chart.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, int>
     */
    public function ringkasanSegmen(array $baris): array
    {
        $ringkasan = [];
        foreach ($baris as $b) {
            $ringkasan[$b['segmen']] = ($ringkasan[$b['segmen']] ?? 0) + 1;
        }

        arsort($ringkasan);

        return $ringkasan;
    }

    /** Rentang tanggal data yang sedang dihitung, untuk label periode di UI. */
    public function periode(): ?string
    {
        $batas = DB::table('laporan_transaksi')
            ->selectRaw('min(tanggal) as awal, max(tanggal) as akhir')
            ->first();

        if ($batas?->awal === null) {
            return null;
        }

        return Carbon::parse($batas->awal)->translatedFormat('j M Y')
            .' - '.Carbon::parse($batas->akhir)->translatedFormat('j M Y');
    }
}
