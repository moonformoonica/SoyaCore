<?php

namespace App\Exports\Sheets;

use App\Services\LaporanQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RingkasanSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $grain,
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function headings(): array
    {
        return ['Metrik', 'Nilai'];
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        $k = $this->query->ringkasan($this->start, $this->end);

        return [
            ['Grain', $this->grain],
            ['Rentang', ($this->start ?? '-').' s/d '.($this->end ?? '-')],
            ['Total Revenue (Rp)', $k['total_revenue']],
            ['Total Transaksi', $k['total_transaksi']],
            ['Total Qty (pcs)', $k['total_qty']],
            ['Rata-rata Transaksi (Rp)', $k['rata_rata_transaksi']],
            ['Total Poin Loyalty', $k['total_poin']],
            ['Pelanggan Unik', $k['pelanggan_unik']],
        ];
    }
}
