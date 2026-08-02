<?php

namespace App\Exports\Sheets;

use App\Services\SwitchQuery;
use App\Exports\Concerns\GayaTabelSoyaCore;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Membaca {@see SwitchQuery}, sumber yang SAMA dengan halaman Laporan. Dulu
 * sheet ini membaca snapshot `laporan_switch` sementara halaman web sudah
 * menghitung ulang, dan dua tampilan angka yang berbeda untuk hal yang sama
 * adalah cara tercepat membuat manager berhenti memercayai keduanya.
 */
class SwitchSheet implements FromArray, WithTitle, WithEvents
{
    use GayaTabelSoyaCore;

    protected function barisHeader(): int
    {
        return 3;
    }

    /**
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [4, 5, 6, 7, 9];
    }

    public function __construct(private readonly SwitchQuery $query = new SwitchQuery) {}

    public function title(): string
    {
        return 'Rekomendasi Switch';
    }

    /**
     * @return array<int, array<int, float|int|string|null>>
     */
    public function array(): array
    {
        $periode = $this->query->periode();

        $rows = [
            ['Catatan: seluruh periode data '.($periode ?? '-').', tidak difilter tanggal. Khusus minuman, dessert dan cookies tidak dihitung.'],
            [],
            ['Nama Pelanggan', 'Rasa Favorit', 'Ukuran Saat Ini', 'Beli Reguler (pcs)', 'Beli Large (pcs)', 'Beli Botol (pcs)', 'Total Transaksi', 'Qty per Kunjungan', 'Total Belanja (Rp)', 'Rekomendasi'],
        ];

        foreach ($this->query->semua() as $r) {
            $rows[] = [
                $r['nama_pelanggan'], $r['rasa_favorit'], $r['ukuran_saat_ini'],
                $r['beli_reguler'], $r['beli_large'], $r['beli_botol'],
                $r['total_transaksi'], $r['qty_per_kunjungan'], $r['total_belanja'], $r['rekomendasi'],
            ];
        }

        return $rows;
    }
}
