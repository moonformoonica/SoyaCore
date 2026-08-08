<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\GayaTabelSoyaCore;
use App\Services\LaporanQuery;
use App\Support\GolonganUkuran;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Revenue per ukuran, DIKELOMPOKKAN per golongan kemasan.
 *
 * Sebelumnya sheet ini hanya daftar datar semua ukuran terurut revenue, jadi
 * `250ml` bisa berdiri di antara `Reguler` dan `Large`. Pertanyaan yang
 * sebenarnya ingin dijawab manager, "ukuran berapa ml yang paling sering
 * keluar", tidak bisa dibaca dari susunan seperti itu: membandingkan botol
 * dengan cup tidak berarti apa-apa, keduanya kemasan untuk keperluan berbeda.
 *
 * Sekarang tiap golongan berdiri sendiri, isinya terurut jumlah terjual
 * menurun (yang paling sering keluar di baris pertama), dan ditutup subtotal.
 * Kolom `% dari Golongan` membandingkan di dalam golongannya sendiri.
 *
 * Angkanya datang dari {@see LaporanQuery::revenueUkuran()} yang sama dengan
 * dashboard, bukan query terpisah, supaya file Excel dan layar tidak pernah
 * menyebut angka berbeda untuk hal yang sama.
 */
class RevenueUkuranSheet implements FromArray, WithEvents, WithTitle
{
    use GayaTabelSoyaCore;

    /**
     * Nomor baris data yang berisi subtotal, diisi saat {@see self::array()}
     * menyusun tabelnya. Posisinya tidak bisa diketahui di muka karena jumlah
     * ukuran per golongan tergantung data yang benar-benar terjual.
     *
     * @var list<int>
     */
    private array $barisSubtotal = [];

    public function __construct(
        private readonly ?string $start,
        private readonly ?string $end,
        private readonly LaporanQuery $query,
    ) {}

    public function title(): string
    {
        return 'Revenue per Ukuran';
    }

    protected function barisHeader(): int
    {
        return 4;
    }

    /**
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [3, 4, 5, 6, 7];
    }

    /**
     * @return list<int>
     */
    protected function barisTotal(): array
    {
        return $this->barisSubtotal;
    }

    /**
     * @return array<int, array<int, float|int|string|null>>
     */
    public function array(): array
    {
        $ukuran = $this->query->revenueUkuran($this->start, $this->end);
        $golongan = $this->query->revenueGolongan($this->start, $this->end);

        $rows = [
            ['Catatan: khusus minuman, dessert & cookies (Cup/Pack) tidak termasuk, jadi totalnya lebih kecil dari sheet Ringkasan.'],
            [$this->catatanTerlaris($golongan)],
            [],
            [
                'Golongan', 'Ukuran', 'Jumlah Terjual', '% dari Golongan',
                'Total Revenue (Rp)', 'Jumlah Transaksi', 'Rata-rata Transaksi (Rp)',
            ],
        ];

        $this->barisSubtotal = [];
        $subtotalPerGolongan = collect($golongan)->keyBy('golongan');

        foreach (GolonganUkuran::semua() as $kode) {
            $baris = array_values(array_filter($ukuran, fn ($u) => $u['golongan'] === $kode));

            if ($baris === []) {
                continue; // golongan yang memang tidak ada penjualannya
            }

            foreach ($baris as $u) {
                $rows[] = [
                    $this->labelGolongan($kode),
                    $u['ukuran'],
                    $u['jumlah_terjual'],
                    $u['persen_dari_golongan'],
                    $u['total_revenue'],
                    $u['jumlah_transaksi'],
                    $u['rata_rata_transaksi'],
                ];
            }

            $total = $subtotalPerGolongan->get($kode);

            $rows[] = [
                'Subtotal '.$this->labelGolongan($kode),
                '',
                $total['jumlah_terjual'],
                100.0,
                $total['total_revenue'],
                $total['jumlah_transaksi'],
                $total['jumlah_transaksi'] > 0
                    ? (int) round($total['total_revenue'] / $total['jumlah_transaksi'])
                    : 0,
            ];

            // Nomor baris DATA (1 = baris pertama sesudah header), sesuai yang
            // diharapkan GayaTabelSoyaCore::barisTotal().
            $this->barisSubtotal[] = count($rows) - $this->barisHeader();
        }

        return $rows;
    }

    /**
     * Catatan yang menyebut langsung ukuran paling sering keluar tiap golongan.
     * Ditaruh di kepala sheet supaya terbaca tanpa perlu menelusuri tabelnya.
     *
     * @param  list<array<string, mixed>>  $golongan
     */
    private function catatanTerlaris(array $golongan): string
    {
        $bagian = [];

        foreach ($golongan as $g) {
            if ($g['ukuran_terlaris'] === null) {
                continue;
            }

            $bagian[] = $this->labelGolongan($g['golongan']).': '.$g['ukuran_terlaris']
                .' ('.number_format((float) $g['jumlah_terjual'], 0, ',', '.').' pcs segolongan)';
        }

        return $bagian === []
            ? 'Belum ada penjualan di rentang ini.'
            : 'Ukuran yang paling sering keluar, '.implode('; ', $bagian).'.';
    }

    private function labelGolongan(string $kode): string
    {
        return match ($kode) {
            GolonganUkuran::CUP => 'Cup (Hot/Reguler/Large)',
            GolonganUkuran::BOTOL => 'Botol (250ml/500ml/1000ml)',
            default => 'Lainnya',
        };
    }
}
