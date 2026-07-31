<?php

namespace App\Support;

use App\Exceptions\ApiException;

/**
 * Opsi peracikan minuman (sugar & ice) beserta aturan ketersediaannya.
 *
 * SATU SUMBER KEBENARAN: FormRequest, MenuResource, dan katalog publik semua
 * membaca dari sini. Daftar yang disalin ke FormRequest akan lepas sinkron
 * dengan daftar yang dikirim ke frontend, dan gejalanya adalah pilihan yang
 * tampil di layar tapi ditolak 422 saat dikirim.
 *
 * Ketersediaan diturunkan dari {@see GolonganUkuran}, BUKAN dari daftar nama
 * menu: menu baru bertambah terus, ukurannya tidak.
 *
 * - `Hot` (cup)                 : sugar saja — es tidak relevan di minuman panas.
 * - `Reguler`, `Large` (cup)    : keduanya, karena diracik per pesanan.
 * - `250ml`–`1000ml` (botol)    : tidak ada. Kemasan botol diproduksi batch,
 *                                 bukan per pesanan, jadi pilihan pelanggan
 *                                 tidak bisa dipenuhi barista.
 * - Dessert & cookies (lainnya) : tidak ada. Bukan minuman.
 */
class OpsiMinuman
{
    /** @var array<string, string> */
    public const SUGAR = [
        'normal' => 'Normal',
        'less' => 'Less Sugar',
        'no' => 'No Sugar',
        'extra' => 'Extra Sugar',
    ];

    /** @var array<string, string> */
    public const ICE = [
        'normal' => 'Normal',
        'less' => 'Less Ice',
        'no' => 'No Ice',
        'extra' => 'Extra Ice',
    ];

    /**
     * Bentuk `{kode, label}` supaya frontend cukup me-loop hasilnya menjadi
     * tombol/dropdown tanpa memetakan label sendiri.
     *
     * @return list<array{kode: string, label: string}>
     */
    public static function daftarSugar(): array
    {
        return self::daftar(self::SUGAR);
    }

    /**
     * @return list<array{kode: string, label: string}>
     */
    public static function daftarIce(): array
    {
        return self::daftar(self::ICE);
    }

    /**
     * @return list<string>
     */
    public static function kodeSugar(): array
    {
        return array_keys(self::SUGAR);
    }

    /**
     * @return list<string>
     */
    public static function kodeIce(): array
    {
        return array_keys(self::ICE);
    }

    public static function bisaPilihSugar(?string $ukuran): bool
    {
        return GolonganUkuran::dari($ukuran) === GolonganUkuran::CUP;
    }

    public static function bisaPilihIce(?string $ukuran): bool
    {
        if (! self::bisaPilihSugar($ukuran)) {
            return false;
        }

        return mb_strtolower(trim((string) $ukuran)) !== 'hot';
    }

    /**
     * Menolak pilihan yang tidak relevan, BUKAN mengabaikannya diam-diam:
     * mengirim `level_ice` untuk menu `Hot` atau kemasan botol adalah kesalahan
     * yang harus terlihat di sisi pengirim. Data yang lolos lalu tersimpan
     * membuat barista membaca instruksi yang tidak bisa dia kerjakan.
     *
     * Dipanggil dari SoyaScan (`OrderService`) maupun kasir
     * (`TransaksiItemController`) — kasir harus bisa mencatat hal yang sama
     * seperti pelanggan.
     *
     * @throws ApiException kode `opsi_tidak_tersedia`
     */
    public static function pastikanBoleh(?string $ukuran, ?string $sugar, ?string $ice, string $labelMenu): void
    {
        if ($sugar !== null && ! self::bisaPilihSugar($ukuran)) {
            throw new ApiException(
                'opsi_tidak_tersedia',
                "{$labelMenu} tidak bisa dipilih level sugar-nya — ".self::alasan($ukuran).'.',
                422,
            );
        }

        if ($ice !== null && ! self::bisaPilihIce($ukuran)) {
            throw new ApiException(
                'opsi_tidak_tersedia',
                "{$labelMenu} tidak bisa dipilih level ice-nya — ".self::alasanIce($ukuran).'.',
                422,
            );
        }
    }

    private static function alasan(?string $ukuran): string
    {
        return match (GolonganUkuran::dari($ukuran)) {
            GolonganUkuran::BOTOL => 'kemasan botol diproduksi batch, bukan diracik per pesanan',
            default => 'menu ini bukan minuman yang diracik per pesanan',
        };
    }

    private static function alasanIce(?string $ukuran): string
    {
        if (GolonganUkuran::dari($ukuran) === GolonganUkuran::CUP) {
            return 'ini minuman panas, jadi es tidak relevan';
        }

        return self::alasan($ukuran);
    }

    public static function labelSugar(?string $kode): ?string
    {
        return $kode === null ? null : (self::SUGAR[$kode] ?? null);
    }

    public static function labelIce(?string $kode): ?string
    {
        return $kode === null ? null : (self::ICE[$kode] ?? null);
    }

    /**
     * @param  array<string, string>  $peta
     * @return list<array{kode: string, label: string}>
     */
    private static function daftar(array $peta): array
    {
        $hasil = [];
        foreach ($peta as $kode => $label) {
            $hasil[] = ['kode' => $kode, 'label' => $label];
        }

        return $hasil;
    }
}
