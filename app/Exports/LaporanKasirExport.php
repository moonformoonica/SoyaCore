<?php

namespace App\Exports;

use App\Exports\Concerns\GayaTabelSoyaCore;
use App\Services\LaporanKasirQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Unduhan halaman Laporan Kasir: SATU sheet berisi tabel yang sedang dilihat
 * manager di layar, bukan seluruh isi {@see LaporanExport}.
 *
 * Sengaja dipisah dari export Laporan. Manager yang menekan Unduh di halaman
 * Laporan Kasir sedang membandingkan kinerja antar akun; menyerahkan file
 * tujuh sheet berisi RFM dan Switch memaksa dia mencari lagi tabel yang tadi
 * sudah ada di depannya.
 *
 * SUMBER DATANYA SAMA PERSIS dengan endpoint yang mengisi tabel di layar
 * ({@see LaporanKasirQuery}), jadi angka di Excel tidak bisa menyimpang dari
 * angka di halaman, dan keduanya ikut rentang tanggal yang sama.
 *
 * Kolom Tunai/QRIS dipecah jadi jumlah transaksi dan nilai rupiah, berbeda
 * dengan layar yang meringkasnya jadi "1× · Rp 41.600". Di Excel gabungan itu
 * akan jadi teks yang tidak bisa dijumlahkan, dan satu-satunya alasan orang
 * mengunduh Excel adalah supaya angkanya bisa dihitung lagi.
 */
class LaporanKasirExport implements FromArray, WithEvents, WithHeadings, WithTitle
{
    use GayaTabelSoyaCore;

    /** @var array{data: list<array<string, mixed>>, meta: array<string, mixed>}|null */
    private ?array $hasil = null;

    public function __construct(
        private readonly ?string $mulai,
        private readonly ?string $selesai,
        private readonly LaporanKasirQuery $query,
    ) {}

    /**
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];
    }

    /**
     * Baris TOTAL selalu yang terakhir. Tanpa dibedakan, angkanya ikut terbaca
     * sebagai satu kasir lagi dan orang yang menjumlahkan kolomnya sendiri akan
     * menghitung seluruh isi tabel dua kali.
     *
     * @return list<int>
     */
    protected function barisTotal(): array
    {
        $jumlah = count($this->hasil()['data']);

        return $jumlah === 0 ? [] : [$jumlah + 1];
    }

    public function title(): string
    {
        return 'Laporan Kasir';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Kasir', 'Jumlah Transaksi', 'Total Omzet (Rp)', 'Total Qty',
            'Rata-rata per Transaksi (Rp)', 'Transaksi Tunai', 'Total Tunai (Rp)',
            'Transaksi QRIS', 'Total QRIS (Rp)', 'Total Diskon (Rp)',
            'Poin Diberikan', 'Poin Ditukar', 'Jumlah Pembatalan', 'Nilai Dibatalkan (Rp)',
        ];
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        $hasil = $this->hasil();
        $baris = array_map(fn (array $r) => $this->petakan($r['nama'], $r), $hasil['data']);

        // Baris TOTAL ikut diunduh, seperti di layar. Diambil dari `meta`
        // bawaan backend, bukan dijumlah ulang di sini, supaya angkanya tidak
        // bisa berbeda dari yang sudah ditampilkan.
        if ($hasil['data'] !== []) {
            $baris[] = $this->petakan('TOTAL', $hasil['meta'] + $this->totalMetode($hasil['data']));
        }

        return $baris;
    }

    /**
     * Rentang yang benar-benar terwakili file ini, dipakai menyusun nama
     * filenya. Batas yang tidak diisi manager disimpulkan dari datanya sendiri,
     * supaya nama file tetap menyebut tanggal alih-alih "Awal Hingga Akhir".
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function rentang(): array
    {
        return $this->query->resolveRentang($this->mulai, $this->selesai);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function hasil(): array
    {
        return $this->hasil ??= $this->query->laporan($this->mulai, $this->selesai);
    }

    /**
     * Baris kasir dan baris TOTAL memakai nama kunci yang sama, jadi keduanya
     * lewat pemetaan ini. Fallback `?? 0` dipasang karena `meta` tidak memuat
     * seluruh kunci yang dipunyai baris per kasir.
     *
     * @param  array<string, mixed>  $r
     * @return array<int, int|string|null>
     */
    private function petakan(string $nama, array $r): array
    {
        $metode = $r['rincian_metode_bayar'] ?? [];

        return [
            $nama,
            (int) ($r['jumlah_transaksi'] ?? 0),
            (int) ($r['total_omzet'] ?? 0),
            (int) ($r['total_qty'] ?? 0),
            (int) ($r['rata_rata_transaksi'] ?? 0),
            (int) ($metode['cash']['jumlah'] ?? 0),
            (int) ($metode['cash']['total'] ?? 0),
            (int) ($metode['qris']['jumlah'] ?? 0),
            (int) ($metode['qris']['total'] ?? 0),
            (int) ($r['total_diskon'] ?? 0),
            (int) ($r['total_poin_diberikan'] ?? 0),
            (int) ($r['total_poin_ditukar'] ?? 0),
            (int) ($r['jumlah_pembatalan'] ?? 0),
            (int) ($r['nilai_dibatalkan'] ?? 0),
        ];
    }

    /**
     * `meta` tidak memuat rincian metode bayar, jadi baris TOTAL menjumlahkan
     * kolom itu dari baris-barisnya. Sama seperti yang dilakukan halaman
     * Laporan Kasir di layar.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array{rincian_metode_bayar: array<string, array{jumlah: int, total: int}>}
     */
    private function totalMetode(array $baris): array
    {
        $hasil = [
            'cash' => ['jumlah' => 0, 'total' => 0],
            'qris' => ['jumlah' => 0, 'total' => 0],
        ];

        foreach ($baris as $r) {
            foreach (['cash', 'qris'] as $kunci) {
                $hasil[$kunci]['jumlah'] += (int) ($r['rincian_metode_bayar'][$kunci]['jumlah'] ?? 0);
                $hasil[$kunci]['total'] += (int) ($r['rincian_metode_bayar'][$kunci]['total'] ?? 0);
            }
        }

        return ['rincian_metode_bayar' => $hasil];
    }
}
