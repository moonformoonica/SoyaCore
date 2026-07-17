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
        $digits = preg_replace('/\D+/', '', trim($nomor));

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
}
