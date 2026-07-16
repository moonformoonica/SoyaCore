<?php

namespace App\Exports\Sheets;

use App\Services\LaporanQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RevenueUkuranSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
    ) {}

    public function title(): string
    {
        return 'Revenue per Ukuran';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Ukuran', 'Jumlah Terjual', 'Total Revenue (Rp)', 'Jumlah Transaksi', 'Rata-rata Transaksi (Rp)'];
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['ukuran'],
            $r['jumlah_terjual'],
            $r['total_revenue'],
            $r['jumlah_transaksi'],
            $r['rata_rata_transaksi'],
        ], $this->query->revenueUkuran($this->start, $this->end));
    }
}
