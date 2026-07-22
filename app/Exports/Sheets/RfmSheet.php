<?php

namespace App\Exports\Sheets;

use App\Models\LaporanRfm;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Statis periode-penuh. Baris pertama adalah catatan periode tetap
 * (bukan hasil filter tanggal), lalu header, lalu data.
 */
class RfmSheet implements FromArray, WithTitle
{
    private const PERIODE_LABEL = '1 Jun 2026 – 30 Jul 2026';

    public function title(): string
    {
        return 'RFM Pelanggan';
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        $rows = [
            ['Catatan: snapshot periode penuh '.self::PERIODE_LABEL.' — tidak difilter tanggal.'],
            [],
            [
                'Nama Pelanggan', 'Recency (hari)', 'Kunjungan', 'Total Pcs',
                'Monetary (Rp)', 'Total Poin', 'Skor Frekuensi',
                'R', 'F', 'M', 'RFM Total', 'Segmen',
            ],
        ];

        foreach (LaporanRfm::query()->orderByDesc('rfm_total')->orderBy('nama_pelanggan')->get() as $r) {
            $rows[] = [
                $r->nama_pelanggan, $r->recency, $r->frequency, $r->total_pcs_dibeli,
                $r->monetary, $r->total_poin_loyalty, $r->frequency_skor,
                $r->r_score, $r->f_score, $r->m_score, $r->rfm_total, $r->segmen,
            ];
        }

        return $rows;
    }
}
