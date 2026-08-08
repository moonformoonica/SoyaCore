<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gaya tabel bersama untuk seluruh sheet export.
 *
 * SATU TEMPAT, BUKAN PER SHEET. Tujuh sheet yang masing-masing mengatur warna
 * sendiri pasti lambat laun berbeda: satu memakai hijau tua, satu hijau muda,
 * dan file yang sama terbaca seperti disusun dua orang. Warnanya diambil dari
 * tema web SoyaCore supaya file Excel dan layar terlihat satu keluarga:
 * hijau `#2F6B3F` yang sama dengan header tabel Laporan, dan baris belang
 * `#F4F8F2` yang sama dengan latar halaman.
 *
 * Sheet yang memakainya cukup `use GayaTabelSoyaCore` lalu mengimplementasikan
 * {@see WithEvents} dan menyebut `barisHeader()` bila headernya tidak di baris
 * pertama, misalnya karena ada catatan periode di atasnya.
 */
trait GayaTabelSoyaCore
{
    private const HIJAU_TUA = 'FF2F6B3F';

    private const HIJAU_MUDA = 'FFF4F8F2';

    /** Latar baris total, sengaja lebih pekat dari baris belang biasa. */
    private const HIJAU_TOTAL = 'FFDDE9DE';

    private const ABU_GARIS = 'FFE3E8E3';

    private const ABU_TEKS = 'FF6B7280';

    /**
     * Baris tempat judul kolom berada. Sheet yang menaruh catatan di atas
     * tabelnya menimpa method ini.
     */
    protected function barisHeader(): int
    {
        return 1;
    }

    /**
     * Kolom yang isinya angka, dihitung dari 1. Dipakai untuk rata kanan dan
     * pemisah ribuan. Sheet yang punya kolom angka menimpa method ini.
     *
     * @return list<int>
     */
    protected function kolomAngka(): array
    {
        return [];
    }

    /**
     * Nomor baris DATA yang berisi total (1 = baris pertama sesudah header).
     *
     * Baris total yang tampil sama persis dengan baris data di atasnya membuat
     * angkanya ikut terbaca sebagai transaksi, dan orang yang menjumlahkan
     * kolom sendiri akan menghitungnya dua kali. Sheet yang punya baris total
     * menimpa method ini supaya barisnya kelihatan beda.
     *
     * @return list<int>
     */
    protected function barisTotal(): array
    {
        return [];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->terapkanGaya($event->sheet->getDelegate());
            },
        ];
    }

    private function terapkanGaya(Worksheet $sheet): void
    {
        $barisHeader = $this->barisHeader();
        $barisTerakhir = $sheet->getHighestRow();
        $kolomTerakhir = $sheet->getHighestColumn();

        // Sheet kosong: hanya catatan, tidak ada tabel untuk dihias.
        if ($barisTerakhir < $barisHeader) {
            return;
        }

        $this->gayaCatatan($sheet, $barisHeader, $kolomTerakhir);
        $this->gayaHeader($sheet, $barisHeader, $kolomTerakhir);

        if ($barisTerakhir > $barisHeader) {
            $this->gayaIsi($sheet, $barisHeader, $barisTerakhir, $kolomTerakhir);
        }

        $this->rapikanKolom($sheet, $kolomTerakhir);

        // Header ikut tergulir bersama isinya kalau tidak dibekukan, dan
        // tabel ratusan baris jadi mustahil dibaca di tengah.
        $sheet->freezePane('A'.($barisHeader + 1));

        // Filter kolom supaya manager bisa menyortir sendiri di Excel tanpa
        // perlu meminta export baru.
        $sheet->setAutoFilter('A'.$barisHeader.':'.$kolomTerakhir.$barisTerakhir);
    }

    private function gayaCatatan(Worksheet $sheet, int $barisHeader, string $kolomTerakhir): void
    {
        for ($baris = 1; $baris < $barisHeader; $baris++) {
            $sheet->getStyle('A'.$baris.':'.$kolomTerakhir.$baris)->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => self::ABU_TEKS]],
            ]);
        }
    }

    private function gayaHeader(Worksheet $sheet, int $baris, string $kolomTerakhir): void
    {
        $sheet->getStyle('A'.$baris.':'.$kolomTerakhir.$baris)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HIJAU_TUA]],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension($baris)->setRowHeight(26);
    }

    private function gayaIsi(Worksheet $sheet, int $barisHeader, int $barisTerakhir, string $kolomTerakhir): void
    {
        $rentang = 'A'.($barisHeader + 1).':'.$kolomTerakhir.$barisTerakhir;

        $sheet->getStyle($rentang)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::ABU_GARIS]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Baris belang. Dibuat manual, bukan lewat tabel Excel, supaya file
        // tetap terbaca oleh LibreOffice dan Google Sheets tanpa kehilangan
        // warnanya.
        for ($baris = $barisHeader + 1; $baris <= $barisTerakhir; $baris++) {
            if (($baris - $barisHeader) % 2 === 0) {
                $sheet->getStyle('A'.$baris.':'.$kolomTerakhir.$baris)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HIJAU_MUDA]],
                ]);
            }
        }

        // Kolom angka rata kanan dengan pemisah ribuan, supaya digitnya
        // berbaris dan nilai besar gampang dibandingkan.
        foreach ($this->kolomAngka() as $nomor) {
            $huruf = $this->huruf($nomor);
            if ($huruf > $kolomTerakhir) {
                continue;
            }

            $sheet->getStyle($huruf.($barisHeader + 1).':'.$huruf.$barisTerakhir)
                ->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]])
                ->getNumberFormat()->setFormatCode('#,##0');
        }

        $this->gayaBarisTotal($sheet, $barisHeader, $barisTerakhir, $kolomTerakhir);
    }

    /**
     * Baris total dibedakan lewat huruf tebal, latar sendiri, dan garis atas.
     * Diterapkan PALING AKHIR supaya menang dari baris belang; kalau tidak,
     * baris total yang kebetulan jatuh di nomor genap tetap berwarna sama
     * dengan baris data biasa.
     */
    private function gayaBarisTotal(Worksheet $sheet, int $barisHeader, int $barisTerakhir, string $kolomTerakhir): void
    {
        foreach ($this->barisTotal() as $nomor) {
            $baris = $barisHeader + $nomor;
            if ($baris > $barisTerakhir) {
                continue;
            }

            $sheet->getStyle('A'.$baris.':'.$kolomTerakhir.$baris)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HIJAU_TOTAL]],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::HIJAU_TUA]],
                ],
            ]);
        }
    }

    private function rapikanKolom(Worksheet $sheet, string $kolomTerakhir): void
    {
        $batas = $this->indeks($kolomTerakhir);

        for ($nomor = 1; $nomor <= $batas; $nomor++) {
            $huruf = $this->huruf($nomor);
            $sheet->getColumnDimension($huruf)->setAutoSize(true);
        }
    }

    private function huruf(int $nomor): string
    {
        return Coordinate::stringFromColumnIndex($nomor);
    }

    private function indeks(string $huruf): int
    {
        return Coordinate::columnIndexFromString($huruf);
    }
}
