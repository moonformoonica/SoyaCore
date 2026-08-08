<?php

namespace App\Support;

use App\Services\LaporanProjector;

/**
 * Menjembatani DUA kosakata metode bayar yang hidup berdampingan di sistem ini.
 *
 * MASALAH YANG DISELESAIKAN. `laporan_transaksi.platform` diisi dari dua sumber
 * yang menamai hal yang sama dengan cara berbeda:
 *
 * | Sumber                    | Nilai                                                        |
 * | ------------------------- | ------------------------------------------------------------ |
 * | Impor CSV Juni-Juli 2026  | `QRIS`, `Tunai`, `Transfer`, `GrabFood`, `ShopeeFood`, `GoFood` |
 * | POS live (`metode_bayar`) | `cash`, `qris` (huruf kecil, dari `BayarRequest`)              |
 *
 * Begitu keduanya bertemu di satu kolom, filter platform di dashboard
 * menampilkan `QRIS` dan `qris` sebagai dua entri berbeda, dan angka QRIS yang
 * sebenarnya terpecah dua. Ini bukan masalah tampilan yang bisa dirapikan di
 * frontend: setiap grafik dan export akan terus memecahnya selamanya.
 *
 * ARAH PEMETAANNYA. Kosakata laporan yang menang, bukan kosakata POS. Alasannya
 * bukan estetika: nilai-nilai itu sudah dipakai ratusan baris historis dan sudah
 * muncul di laporan yang pernah dibaca manajemen. Memetakan ke arah sebaliknya
 * berarti menulis ulang sejarah yang sudah pernah dilaporkan.
 *
 * BATAS BERLAKUNYA. Normalisasi hanya terjadi DI BATAS menuju layer laporan
 * ({@see LaporanProjector}). Kolom `transaksi.metode_bayar` tetap
 * `cash`/`qris` huruf kecil, karena nilai itu sudah terikat validasi
 * `BayarRequest` dan kontrak API kasir.
 */
class PlatformPembayaran
{
    public const TUNAI = 'Tunai';

    public const QRIS = 'QRIS';

    /**
     * Kunci di-lowercase supaya toleran terhadap ejaan apa pun yang pernah
     * masuk (`cash`, `Cash`, `CASH`). `tunai` ikut dipetakan ke dirinya sendiri
     * agar nilai yang sudah benar tidak berubah bentuk saat dilewatkan.
     *
     * @var array<string, string>
     */
    private const PETA = [
        'cash' => self::TUNAI,
        'tunai' => self::TUNAI,
        'qris' => self::QRIS,
    ];

    /**
     * Nilai `platform` baku untuk sebuah `metode_bayar`.
     *
     * Nilai di luar peta dikembalikan apa adanya, BUKAN dipaksa ke salah satu
     * nilai baku. Kolom `platform` memang campuran metode bayar dan channel
     * (GrabFood, ShopeeFood, Transfer), jadi memaksakan pemetaan justru akan
     * merusak nilai channel yang sudah benar.
     */
    public static function dari(?string $metodeBayar): ?string
    {
        $kunci = mb_strtolower(trim((string) $metodeBayar));

        if ($kunci === '') {
            return null;
        }

        return self::PETA[$kunci] ?? trim((string) $metodeBayar);
    }

    /**
     * Apakah sebuah nilai `platform` masih memakai kosakata lama dan perlu
     * dinormalkan. Dipakai pengecekan setelah migrasi normalisasi.
     */
    public static function perluDinormalkan(?string $platform): bool
    {
        if ($platform === null) {
            return false;
        }

        return self::dari($platform) !== $platform;
    }
}
