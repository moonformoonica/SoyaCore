<?php

namespace App\Exports\Sheets;

use App\Services\LaporanQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class RevenueUkuranSheet implements FromArray, WithTitle
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
     * @return array<int, array<int, int|string|null>>
     */
    public function array(): array
    {
        $rows = [
            ['Catatan: khusus minuman — dessert & cookies (Cup/Pack) tidak termasuk, jadi totalnya lebih kecil dari sheet Ringkasan.'],
            [],
            ['Ukuran', 'Jumlah Terjual', 'Total Revenue (Rp)', 'Jumlah Transaksi', 'Rata-rata Transaksi (Rp)'],
        ];

        foreach ($this->query->revenueUkuran($this->start, $this->end) as $r) {
            $rows[] = [
                $r['ukuran'],
                $r['jumlah_terjual'],
                $r['total_revenue'],
                $r['jumlah_transaksi'],
                $r['rata_rata_transaksi'],
            ];
        }

        return $rows;
    }
}
