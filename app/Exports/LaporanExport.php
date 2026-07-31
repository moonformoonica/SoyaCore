<?php

namespace App\Exports;

use App\Exports\Sheets\DetailTransaksiSheet;
use App\Exports\Sheets\RekapKasirSheet;
use App\Exports\Sheets\RevenueUkuranSheet;
use App\Exports\Sheets\RfmSheet;
use App\Exports\Sheets\RingkasanSheet;
use App\Exports\Sheets\SwitchSheet;
use App\Exports\Sheets\TimeSeriesSheet;
use App\Services\LaporanQuery;
use App\Services\RekapKasirHarian;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanExport implements WithMultipleSheets
{
    public function __construct(
        private readonly string $grain,
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
        private readonly RekapKasirHarian $rekapKasir,
        private readonly ?int $kasirUserId = null,
        private readonly ?string $kasirNama = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        // `untukKasir()` menyaring seluruh agregasi yang lewat LaporanQuery
        // sekaligus — Ringkasan, Revenue per Ukuran, dan Time Series ikut
        // tersaring tanpa masing-masing perlu tahu ada filter kasir.
        $query = $this->query->untukKasir($this->kasirUserId);

        $sheets = [
            new RingkasanSheet($this->grain, $this->start, $this->end, $query, $this->kasirNama),
            new RekapKasirSheet($this->start, $this->end, $this->kasirUserId, $this->rekapKasir),
            new DetailTransaksiSheet($this->start, $this->end, $this->kasirUserId),
            new RevenueUkuranSheet($this->start, $this->end, $query),
            new TimeSeriesSheet($this->grain, $this->start, $this->end, $query),
        ];

        // RFM & Switch adalah analisis SEGMEN PELANGGAN dari data historis dan
        // tidak punya dimensi kasir sama sekali. Kalau tetap disertakan pada
        // export yang difilter satu kasir, isinya akan menampilkan seluruh
        // pelanggan toko — angka yang tidak tersaring di dalam file yang
        // judulnya menyebut satu nama kasir. Lebih jujur dikeluarkan.
        if ($this->kasirUserId === null) {
            $sheets[] = new RfmSheet;
            $sheets[] = new SwitchSheet;
        }

        return $sheets;
    }
}
