<?php

namespace App\Exports\Sheets;

use App\Models\LaporanTransaksi;
use Illuminate\Database\Eloquent\Builder;
use App\Exports\Concerns\GayaTabelSoyaCore;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class DetailTransaksiSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, WithEvents
{
    use GayaTabelSoyaCore;

    /**
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [6, 7, 8, 9];
    }

    /** Baris impor CSV historis memang tidak merekam kasir. */
    private const KASIR_TIDAK_ADA = '—';

    public function __construct(
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly ?int $kasirUserId = null,
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
        if ($this->kasirUserId !== null) {
            $query->where('kasir_user_id', $this->kasirUserId);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID Transaksi', 'Tanggal', 'Platform', 'Kasir', 'Nama Pelanggan', 'No WhatsApp',
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
            // Sudah tanggal WIB sejak diproyeksikan/diimpor, jadi tidak perlu
            // dikonversi lagi di sini.
            $row->tanggal->format('Y-m-d'),
            $row->platform,
            // Sel kosong terbaca sebagai data hilang, sedangkan ini memang
            // transaksi dari sebelum SoyaCore dipakai, jadi diberi tanda.
            $row->kasir_nama ?? self::KASIR_TIDAK_ADA,
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
