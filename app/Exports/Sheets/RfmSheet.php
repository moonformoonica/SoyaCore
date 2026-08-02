<?php

namespace App\Exports\Sheets;

use App\Services\RfmQuery;
use App\Exports\Concerns\GayaTabelSoyaCore;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Angkanya DIHITUNG lewat {@see RfmQuery}, sumbernya sama persis dengan yang
 * dipakai halaman Laporan. Sebelumnya sheet ini membaca snapshot `laporan_rfm`
 * sementara dashboard sudah dihitung ulang, dan dua tempat yang menjawab
 * pertanyaan sama dengan angka berbeda adalah cara tercepat kehilangan
 * kepercayaan pada laporannya.
 */
class RfmSheet implements FromArray, WithTitle, WithEvents
{
    use GayaTabelSoyaCore;

    protected function barisHeader(): int
    {
        return 4;
    }

    /**
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [2, 3, 4, 5, 6, 7, 8, 9];
    }

    public function __construct(private readonly RfmQuery $rfm = new RfmQuery) {}

    public function title(): string
    {
        return 'RFM Pelanggan';
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        $periode = $this->rfm->periode();

        $rows = [
            ['Catatan: seluruh periode data'.($periode === null ? '' : ' ('.$periode.')').', tidak difilter tanggal.'],
            ['Recency dihitung dari hari setelah transaksi terakhir di data, dan ikut bergerak saat ada transaksi baru.'],
            [],
            [
                'Nama Pelanggan', 'Recency (hari)', 'Kunjungan',
                'Monetary (Rp)', 'Total Poin',
                'R', 'F', 'M', 'RFM Total', 'Segmen',
            ],
        ];

        foreach ($this->rfm->semua() as $r) {
            $rows[] = [
                $r['nama_pelanggan'], $r['recency'], $r['frequency'],
                $r['monetary'], $r['total_poin_loyalty'],
                $r['r_score'], $r['f_score'], $r['m_score'], $r['rfm_total'], $r['segmen'],
            ];
        }

        return $rows;
    }
}
