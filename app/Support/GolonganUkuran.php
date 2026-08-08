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

    /**
     * Ejaan resmi tiap ukuran untuk ditampilkan.
     *
     * Dibutuhkan karena masalah yang sama dengan kolom `platform`: satu ukuran
     * ditulis dua cara (`250 ml` di impor CSV, `250ml` di katalog menu), dan
     * pengelompokan mentah menampilkannya sebagai dua batang terpisah, sehingga
     * "ukuran berapa ml yang paling sering keluar" terjawab salah.
     *
     * Ejaan KATALOG MENU yang menang, bukan ejaan CSV. Berbeda dari kasus
     * `platform`, di sini katalog menu adalah master yang sebenarnya: impor CSV
     * kejadian sekali, sedangkan seluruh transaksi baru mengambil ukurannya
     * dari katalog, jadi ejaan itu yang akan terus dipakai selamanya.
     *
     * @var array<string, string>
     */
    private const LABEL = [
        'hot' => 'Hot',
        'reguler' => 'Reguler',
        'regular' => 'Reguler',
        'large' => 'Large',
        '250ml' => '250ml',
        '500ml' => '500ml',
        '1000ml' => '1000ml',
    ];

    public static function dari(?string $ukuran): string
    {
        return self::PETA[self::kunci($ukuran)] ?? self::LAINNYA;
    }

    /**
     * Menyeragamkan ejaan sebelum dicocokkan ke {@see self::PETA}.
     *
     * SPASI DI DALAM NILAI IKUT DIBUANG, dan ini bukan kerapian belaka. Dua
     * sumber data menulis ukuran botol dengan cara berbeda:
     *
     * | Sumber                   | Ejaan                        |
     * | ------------------------ | ---------------------------- |
     * | Impor CSV Juni-Juli 2026 | `250 ml`, `500 ml`, `1000 ml` |
     * | Katalog menu / POS       | `250ml`, `500ml`, `1000ml`    |
     *
     * Tanpa pembuangan spasi, 145 dari 148 baris botol di data historis jatuh
     * ke golongan `lainnya`, dan grafik "revenue per golongan" melaporkan botol
     * nyaris tidak pernah terjual, padahal yang salah cuma cara mengetiknya.
     * Kegagalan seperti ini tidak melempar error, ia hanya muncul sebagai angka
     * yang terlihat masuk akal tapi keliru.
     */
    private static function kunci(?string $ukuran): string
    {
        return str_replace(' ', '', mb_strtolower(trim((string) $ukuran)));
    }

    /**
     * @return list<string>
     */
    public static function semua(): array
    {
        return [self::CUP, self::BOTOL, self::LAINNYA];
    }

    /**
     * Ejaan resmi sebuah ukuran untuk ditampilkan dan dikelompokkan.
     *
     * Ukuran di luar daftar dikembalikan apa adanya (setelah di-trim), BUKAN
     * dipaksa ke salah satu nilai baku: `Cup` dan `Pack` milik dessert juga
     * lewat sini, dan mengubahnya justru merusak label yang sudah benar.
     */
    public static function labelBaku(?string $ukuran): ?string
    {
        $bersih = trim((string) $ukuran);

        if ($bersih === '') {
            return null;
        }

        return self::LABEL[self::kunci($bersih)] ?? $bersih;
    }

    /**
     * Kunci pengurutan: golongan lebih dulu (cup → botol → lainnya), lalu
     * ukuran di dalam golongan itu.
     */
    public static function urutan(?string $ukuran): int
    {
        $indeks = array_search(self::kunci($ukuran), self::URUTAN, true);

        if ($indeks === false) {
            return count(self::URUTAN);
        }

        return $indeks;
    }
}
