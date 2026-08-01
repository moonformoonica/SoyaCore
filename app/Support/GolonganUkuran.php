<?php

namespace App\Support;

/**
 * Menggolongkan nilai `menu.ukuran` menjadi cup / botol / lainnya.
 *
 * Ada di backend, bukan di frontend, karena dua hal ikut bergantung padanya:
 * tata letak halaman Edit Menu (cup kiri, botol kanan) dan ketersediaan opsi
 * sugar/ice ({@see OpsiMinuman}). Kalau frontend menebak golongan dari string
 * ukuran, penambahan ukuran baru harus diingat di dua tempat, dan yang satu
 * pasti terlewat.
 *
 * Ukuran dessert & cookies tersimpan sebagai string kosong (bukan null) di
 * seeder, jadi keduanya diperlakukan sama: `lainnya`.
 */
class GolonganUkuran
{
    public const CUP = 'cup';

    public const BOTOL = 'botol';

    public const LAINNYA = 'lainnya';

    /**
     * Urutan tampil yang benar menurut ukuran gelas/kemasan, dari kecil ke
     * besar. `orderBy('ukuran')` menghasilkan `1000ml, 250ml, 500ml, Hot,
     * Large, Reguler`, alfabetis, dan tidak berarti apa pun buat manager yang
     * sedang mengedit harga.
     *
     * Kunci di-lowercase supaya toleran terhadap ejaan "Regular"/"Reguler"
     * yang sudah diterima katalog redeem.
     *
     * @var array<string, string>
     */
    private const PETA = [
        'hot' => self::CUP,
        'reguler' => self::CUP,
        'regular' => self::CUP,
        'large' => self::CUP,
        '250ml' => self::BOTOL,
        '500ml' => self::BOTOL,
        '1000ml' => self::BOTOL,
    ];

    /**
     * Ukuran yang tidak terdaftar di sini ikut jatuh ke urutan terakhir, jadi
     * ukuran baru yang belum didaftarkan tetap tampil, hanya di paling bawah.
     *
     * @var list<string>
     */
    private const URUTAN = ['hot', 'reguler', 'regular', 'large', '250ml', '500ml', '1000ml'];

    public static function dari(?string $ukuran): string
    {
        return self::PETA[mb_strtolower(trim((string) $ukuran))] ?? self::LAINNYA;
    }

    /**
     * @return list<string>
     */
    public static function semua(): array
    {
        return [self::CUP, self::BOTOL, self::LAINNYA];
    }

    /**
     * Kunci pengurutan: golongan lebih dulu (cup → botol → lainnya), lalu
     * ukuran di dalam golongan itu.
     */
    public static function urutan(?string $ukuran): int
    {
        $kunci = mb_strtolower(trim((string) $ukuran));
        $indeks = array_search($kunci, self::URUTAN, true);

        if ($indeks === false) {
            return count(self::URUTAN);
        }

        return $indeks;
    }
}
