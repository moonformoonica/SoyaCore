<?php

namespace App\Support;

/**
 * Nama file Excel untuk seluruh unduhan laporan.
 *
 * SATU TEMPAT, BUKAN PER CONTROLLER. Ada tiga halaman yang mengunduh Excel
 * (Laporan, Laporan Kasir, Transaksi) dan ketiganya harus terbaca sebagai satu
 * keluarga di folder Downloads. Kalau masing-masing menyusun namanya sendiri,
 * cukup satu pemisah yang beda untuk membuat file yang berdampingan terlihat
 * berasal dari dua aplikasi.
 *
 * Bentuknya: `{Judul}_SoyaCore_{YYYY-MM-DD} Hingga {YYYY-MM-DD}.xlsx`, dengan
 * judul yang menyebut kategorinya, sehingga isi file bisa ditebak dari namanya
 * tanpa perlu dibuka.
 */
final class NamaFileLaporan
{
    /** Batas yang tidak dibatasi tanggal, dipakai kalau rentangnya tidak bisa disimpulkan dari data. */
    private const AWAL = 'Awal';

    private const AKHIR = 'Akhir';

    /**
     * @param  string  $judul  Kategori laporannya, misal 'Laporan Kasir'.
     * @param  string|null  $imbuhan  Penanda tambahan di ujung nama, misal nama kasir
     *                                yang difilter, supaya dua unduhan berurutan tidak
     *                                saling menimpa di folder Downloads.
     */
    public static function susun(string $judul, ?string $mulai, ?string $selesai, ?string $imbuhan = null): string
    {
        return sprintf(
            '%s_SoyaCore_%s Hingga %s%s.xlsx',
            $judul,
            $mulai ?? self::AWAL,
            $selesai ?? self::AKHIR,
            $imbuhan === null || trim($imbuhan) === '' ? '' : '_'.trim($imbuhan),
        );
    }
}
