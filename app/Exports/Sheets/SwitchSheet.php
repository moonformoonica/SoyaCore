<?php

namespace App\Exports\Sheets;

use App\Models\LaporanSwitch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Statis periode-penuh. Baris pertama adalah catatan periode tetap
 * (bukan hasil filter tanggal), lalu header, lalu data.
 */
class SwitchSheet implements FromArray, WithTitle
{
    private const PERIODE_LABEL = '1 Jun 2026 – 30 Jul 2026';

    public function title(): string
    {
        return 'Rekomendasi Switch';
    }

    /**
     * @return array<int, array<int, float|int|string|null>>
     */
    public function array(): array
    {
        $rows = [
            ['Catatan: snapshot periode penuh '.self::PERIODE_LABEL.' — tidak difilter tanggal.'],
            [],
            ['Nama Pelanggan', 'Rasa Favorit', 'Ukuran Saat Ini', 'Beli Reguler (pcs)', 'Beli Large (pcs)', 'Beli Botol (pcs)', 'Total Transaksi', 'Qty per Kunjungan', 'Total Belanja (Rp)', 'Rekomendasi'],
        ];

        foreach (LaporanSwitch::query()->orderByDesc('total_belanja')->orderBy('nama_pelanggan')->get() as $r) {
            $rows[] = [
                $r->nama_pelanggan, $r->rasa_favorit, $r->ukuran_saat_ini,
                $r->beli_reguler, $r->beli_large, $r->beli_botol,
                $r->total_transaksi, $r->qty_per_kunjungan, $r->total_belanja, $r->rekomendasi,
            ];
        }

        return $rows;
    }
}
