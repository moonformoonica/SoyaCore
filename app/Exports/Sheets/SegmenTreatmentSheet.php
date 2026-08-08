<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\GayaTabelSoyaCore;
use App\Services\LaporanQuery;
use App\Services\RfmQuery;
use App\Support\SegmenTreatment;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Pola treatment tiap segmen RFM, berdampingan dengan jumlah anggotanya.
 *
 * Sheet `RFM Pelanggan` menjawab "siapa masuk segmen mana". Sheet ini menjawab
 * pertanyaan lanjutannya, "lalu harus diapakan", yang selama ini tidak pernah
 * ikut keluar dari sistem, sehingga hasil segmentasinya berhenti sebagai angka
 * dan tidak pernah jadi tindakan.
 *
 * Jumlah pelanggannya dihitung dari {@see RfmQuery} pada rentang yang sama
 * dengan sheet lain di file ini, jadi porsinya bisa langsung dibandingkan
 * dengan sheet RFM tanpa perlu menghitung ulang.
 */
class SegmenTreatmentSheet implements FromArray, WithEvents, WithTitle
{
    use GayaTabelSoyaCore;

    public function __construct(
        private readonly ?string $start = null,
        private readonly ?string $end = null,
        private readonly RfmQuery $rfm = new RfmQuery(new LaporanQuery),
    ) {}

    public function title(): string
    {
        return 'Segmen & Treatment';
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
        return [2, 3];
    }

    /**
     * @return array<int, array<int, float|int|string|null>>
     */
    public function array(): array
    {
        $jumlah = $this->rfm->ringkasanSegmen($this->rfm->semua($this->start, $this->end));

        $rows = [
            ['Catatan: segmen diurutkan menurut prioritas penanganan, bukan menurut jumlah pelanggannya.'],
            ['Segmen Loyal sengaja TIDAK diberi diskon. Mereka sudah membeli tanpa insentif, jadi diskon di sana hanya memotong margin dari penjualan yang toh tetap terjadi.'],
            [],
            [
                'Segmen', 'Jumlah Pelanggan', '% dari Total', 'Karakteristik',
                'Tujuan', 'Treatment', 'Reward Disarankan', 'Alasan Reward',
            ],
        ];

        foreach (SegmenTreatment::denganJumlah($jumlah) as $s) {
            $rows[] = [
                $s['segmen'],
                $s['jumlah_pelanggan'],
                $s['persen'],
                $s['karakteristik'],
                $s['tujuan'],
                // Digabung jadi satu sel bernomor, bukan satu baris per aksi.
                // Satu segmen = satu baris membuat kolom jumlah pelanggan dan
                // persennya tidak berulang, sehingga tidak ada yang keliru
                // menjumlahkannya.
                $this->daftarBernomor($s['treatment']),
                $s['reward_disarankan'],
                $s['alasan_reward'],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $aksi
     */
    private function daftarBernomor(array $aksi): string
    {
        $baris = [];

        foreach ($aksi as $i => $isi) {
            $baris[] = ($i + 1).'. '.$isi;
        }

        return implode("\n", $baris);
    }
}
