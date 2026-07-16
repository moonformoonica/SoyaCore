<?php

namespace App\Exports;

use App\Exports\Sheets\DetailTransaksiSheet;
use App\Exports\Sheets\RevenueUkuranSheet;
use App\Exports\Sheets\RfmSheet;
use App\Exports\Sheets\RingkasanSheet;
use App\Exports\Sheets\SwitchSheet;
use App\Exports\Sheets\TimeSeriesSheet;
use App\Services\LaporanQuery;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Workbook laporan multi-sheet. Sheet yang relevan di-scope ke window
 * [start, end]; RFM & Switch statis periode-penuh.
 */
class LaporanExport implements WithMultipleSheets
{
    public function __construct(
        private readonly string $grain,
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new RingkasanSheet($this->grain, $this->start, $this->end, $this->query),
            new DetailTransaksiSheet($this->start, $this->end),
            new RevenueUkuranSheet($this->start, $this->end, $this->query),
            new TimeSeriesSheet($this->grain, $this->start, $this->end, $this->query),
            new RfmSheet,
            new SwitchSheet,
        ];
    }
}
