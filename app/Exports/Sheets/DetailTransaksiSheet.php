<?php

namespace App\Exports\Sheets;

use App\Models\LaporanTransaksi;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class DetailTransaksiSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private readonly ?string $start,
        private readonly ?string $end,
    ) {}

    public function title(): string
    {
        return 'Detail Transaksi';
    }

    public function query(): Builder
    {
        $query = LaporanTransaksi::query()->orderBy('tanggal')->orderBy('kode');

        if ($this->start !== null) {
            $query->whereDate('tanggal', '>=', $this->start);
        }
        if ($this->end !== null) {
            $query->whereDate('tanggal', '<=', $this->end);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID Transaksi', 'Tanggal', 'Platform', 'Nama Pelanggan', 'No WhatsApp',
            'Nama Produk', 'Rasa', 'Ukuran', 'Jumlah (pcs)', 'Harga Satuan (Rp)',
            'Total (Rp)', 'Poin Loyalty', 'Catatan',
        ];
    }

    /**
     * @param  LaporanTransaksi  $row
     * @return array<int, int|string|null>
     */
    public function map($row): array
    {
        return [
            $row->kode,
            $row->tanggal->format('Y-m-d'),
            $row->platform,
            $row->nama_pelanggan,
            $row->no_wa,
            $row->nama_produk,
            $row->rasa,
            $row->ukuran,
            $row->qty,
            $row->harga_satuan,
            $row->total,
            $row->poin_loyalty,
            $row->catatan,
        ];
    }
}
