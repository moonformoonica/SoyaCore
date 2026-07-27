<?php

namespace App\Support;

/**
 * Normalisasi nomor WhatsApp — kunci matching customer/loyalty.
 * Konsisten dengan catatan risiko roadmap: nomor WA sebagai kunci utama.
 */
class NomorWa
{
    /**
     * Aturan M3 (LoyalSeed): buang semua non-digit, lalu normalkan ke
     * format 62 supaya variasi penulisan menghasilkan customer yang SAMA:
     *
     *  "0812-3456-7890"     => "6281234567890"
     *  "+62 812 3456 7890"  => "6281234567890"
     *  "812345 67890"       => "6281234567890"
     */
    public static function normalisasi(string $nomor): string
    {
        $digits = self::digit($nomor);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits; // sudah 62xxx (atau kode negara lain, disimpan apa adanya)
    }

    /**
     * Digit mentah yang diketik user, tanpa penambahan prefix apa pun.
     *
     * Dipakai untuk mengukur "sudah berapa banyak yang diketik" — panjang
     * hasil normalisasi() tidak bisa dipakai untuk itu karena menambahkan
     * awalan "62", sehingga satu ketikan "8" terbaca jadi 3 karakter.
     */
    public static function digit(string $nomor): string
    {
        return preg_replace('/\D+/', '', trim($nomor));
    }

    /**
     * Bentuk-bentuk yang perlu dicoba saat mencocokkan potongan nomor
     * (pencarian SEBAGIAN), bukan nomor lengkap.
     *
     * normalisasi() dirancang untuk nomor LENGKAP: input berawalan 0 atau 8
     * SELALU ditempeli "62" karena diasumsikan itu awal nomor. Asumsi itu
     * runtuh untuk pencarian sebagian — kasir yang mengetik 4 digit terakhir
     * "8122" bukan sedang menulis awal nomor, tapi ekornya, dan "628122"
     * tidak ada di dalam "6281245688122".
     *
     * Karena itu dikembalikan dua kemungkinan:
     * - digit apa adanya  -> cocok untuk potongan tengah/ekor ("8122")
     * - hasil normalisasi -> cocok untuk ejaan lokal dari awal ("0812")
     *
     * Keduanya dicoba karena dari potongan digit saja mustahil ditebak mana
     * yang dimaksud; pemanggil mencocokkan dengan OR.
     *
     * @return list<string>
     */
    public static function kandidatCari(string $nomor): array
    {
        if (self::digit($nomor) === '') {
            return [];
        }

        return array_values(array_unique([
            self::digit($nomor),
            self::normalisasi($nomor),
        ]));
    }
}
