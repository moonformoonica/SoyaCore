<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\GayaTabelSoyaCore;
use App\Services\LaporanQuery;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RingkasanSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    use GayaTabelSoyaCore;

    public function __construct(
        private readonly string $grain,
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
        private readonly ?string $kasirNama = null,
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
            // Disebut eksplisit supaya file yang sudah di-download tetap bisa
            // dikenali: tanpa baris ini, laporan satu kasir dan laporan seluruh
            // toko terlihat sama persis dan gampang tertukar.
            ['Kasir', $this->kasirNama ?? 'Semua kasir'],
            ['Total Revenue (Rp)', $k['total_revenue']],
            ['Total Transaksi', $k['total_transaksi']],
            ['Total Qty (pcs)', $k['total_qty']],
            ['Rata-rata Transaksi (Rp)', $k['rata_rata_transaksi']],
            ['Total Poin Loyalty', $k['total_poin']],
            ['Pelanggan Unik', $k['pelanggan_unik']],
        ];
    }
}
