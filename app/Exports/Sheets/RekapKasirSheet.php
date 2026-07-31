<?php

namespace App\Exports\Sheets;

use App\Exports\LaporanExport;
use App\Services\RekapKasirHarian;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Rincian tiap kasir per tanggal — inti permintaan "file Excel harus memuat
 * rincian tiap kasir".
 *
 * Diletakkan tepat setelah `Ringkasan` di {@see LaporanExport}
 * supaya terlihat lebih dulu daripada sheet detail: ini yang dibaca manager,
 * sedangkan `Detail Transaksi` adalah lampirannya.
 *
 * Baris TOTAL per tanggal dan satu TOTAL KESELURUHAN sudah dihitung di
 * {@see RekapKasirHarian}, jadi manager tidak perlu membuat pivot sendiri.
 */
class RekapKasirSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly ?int $kasirUserId,
        private readonly RekapKasirHarian $rekap,
    ) {}

    public function title(): string
    {
        return 'Rekap Kasir';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Tanggal', 'Kasir', 'Jumlah Transaksi', 'Total Qty', 'Total Omzet (Rp)',
            'Rata-rata per Transaksi (Rp)', 'Cash (Rp)', 'QRIS (Rp)', 'Total Diskon (Rp)',
            'Poin Diberikan', 'Poin Ditukar', 'Jumlah Pembatalan', 'Nilai Dibatalkan (Rp)',
        ];
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        return array_map(fn (array $b) => [
            $b['tanggal'],
            $b['kasir'],
            $b['jumlah_transaksi'],
            $b['total_qty'],
            $b['total_omzet'],
            $b['rata_rata_transaksi'],
            $b['cash'],
            $b['qris'],
            $b['total_diskon'],
            $b['poin_diberikan'],
            $b['poin_ditukar'],
            $b['jumlah_pembatalan'],
            $b['nilai_dibatalkan'],
        ], $this->rekap->baris($this->start, $this->end, $this->kasirUserId));
    }
}
