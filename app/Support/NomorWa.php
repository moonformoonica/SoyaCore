<?php

namespace App\Support;

/**
 * Normalisasi nomor WhatsApp — kunci matching customer/loyalty.
 * Konsisten dengan catatan risiko roadmap: nomor WA sebagai kunci utama.
 */
class NomorWa
{
    /**
     * Trim spasi, pertahankan leading '+', buang semua karakter non-digit.
     * Contoh: " +62 812-3456 7890 " => "+6281234567890"
     */
    public static function normalisasi(string $nomor): string
    {
        $nomor = trim($nomor);
        $plus = str_starts_with($nomor, '+');

        return ($plus ? '+' : '').preg_replace('/\D+/', '', $nomor);
    }
}
