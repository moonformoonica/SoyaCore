<?php

namespace App\Support;

/**
 * Katalog Redeem Poin (M3 LoyalSeed) — SATU-SATUNYA tempat definisi
 * kode/label/poin/efek redemption. Kalau owner merevisi poin atau menu
 * reward, cukup ubah di sini.
 *
 * Catatan penting:
 * - Harga TIDAK di-hardcode di sini — selalu diambil live dari menu.harga
 *   saat redeem terjadi (konsisten prinsip snapshot harga).
 * - min_subtotal hanya dimiliki diskon_50 (satu-satunya tier bersyarat).
 * - 'ukuran' berisi semua ejaan yang diterima (toleran "Reguler"/"Regular",
 *   matching dilakukan case-insensitive); urutannya adalah urutan
 *   preferensi saat lebih dari satu varian menu cocok.
 */
class LoyaltyRedemptionCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'diskon_10' => [
                'label' => 'Diskon 10%',
                'poin' => 150,
                'tipe' => 'diskon',
                'persen' => 10,
                'min_subtotal' => 0,
            ],
            'diskon_20' => [
                'label' => 'Diskon 20%',
                'poin' => 250,
                'tipe' => 'diskon',
                'persen' => 20,
                'min_subtotal' => 0,
            ],
            'diskon_50' => [
                'label' => 'Diskon 50% (Khusus)',
                'poin' => 350,
                'tipe' => 'diskon',
                'persen' => 50,
                'min_subtotal' => 50000, // satu-satunya tier dengan minimal pembelian
            ],
            'gratis_original' => [
                'label' => 'Gratis Original',
                'poin' => 150,
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Signature',
                'menu' => 'Original',
                'ukuran' => ['Reguler', 'Regular', 'Hot'],
            ],
            'gratis_coffee_kopi' => [
                'label' => 'Gratis Coffee Kopi',
                'poin' => 250,
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Coffee',
                'menu' => 'Coffee Kopi',
                'ukuran' => ['Reguler', 'Regular', 'Hot'],
            ],
            'gratis_honey_lemon' => [
                'label' => 'Gratis Honey Lemon',
                'poin' => 250,
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Tropical',
                'menu' => 'Honey Lemon',
                'ukuran' => ['Reguler', 'Regular'],
            ],
            'gratis_mango_monggo' => [
                'label' => 'Gratis Mango Monggo',
                'poin' => 250,
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Tropical',
                'menu' => 'Mango Monggo',
                'ukuran' => ['Reguler', 'Regular'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $kode): ?array
    {
        return self::all()[$kode] ?? null;
    }
}
