<?php

namespace App\Exports\Sheets;

use App\Services\LaporanQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TimeSeriesSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $grain,
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
    ) {}

    public function title(): string
    {
        return 'Time Series';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Periode ('.$this->grain.')', 'Revenue (Rp)', 'Transaksi', 'Qty (pcs)'];
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    public function array(): array
    {
        return array_map(fn ($r) => [
            $r['periode'],
            $r['revenue'],
            $r['transaksi'],
            $r['qty'],
        ], $this->query->timeSeries($this->start, $this->end, $this->grain));
    }
}
