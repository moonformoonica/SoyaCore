<?php

namespace App\Support;

/**
 * Pola `LIKE` untuk pencarian sebagian.
 *
 * Dua jebakan yang ditutup di sini, dan keduanya tidak memicu error apa pun,
 * hanya hasil pencarian yang diam-diam salah:
 *
 * 1. **Case-sensitivity.** `LIKE` di SQLite case-insensitive untuk ASCII, tapi di
 *    PostgreSQL TIDAK. Kode yang lulus test lokal (SQLite) bisa gagal menemukan
 *    "Budi" saat kasir mengetik "budi" di produksi (PostgreSQL). Karena itu
 *    pencarian teks selalu dipasangkan dengan `LOWER()` di KEDUA sisi.
 *
 * 2. **Wildcard bocor dari input.** `%` dan `_` yang diketik user adalah wildcard
 *    LIKE. Tanpa di-escape, mengetik `%` mencocokkan seluruh tabel, dan `_`
 *    mencocokkan sembarang satu karakter.
 *
 * Pemakaian:
 *
 *     $q->whereRaw('LOWER(nama) LIKE ?', [PolaCari::teks($kata)]);
 *     $q->where('no_wa', 'like', '%'.PolaCari::escape($digit).'%');
 */
class PolaCari
{
    /**
     * Pola siap pakai untuk kolom TEKS: sudah di-lowercase dan di-escape,
     * dibungkus `%…%`. Wajib dipasangkan dengan `LOWER(kolom)` di sisi SQL.
     */
    public static function teks(string $kata): string
    {
        return '%'.self::escape(mb_strtolower(trim($kata))).'%';
    }

    /**
     * Escape wildcard saja, tanpa mengubah huruf, untuk kolom yang isinya bukan
     * teks bebas (mis. `no_wa` yang cuma digit, jadi LOWER() tidak ada gunanya).
     */
    public static function escape(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $nilai);
    }
}
