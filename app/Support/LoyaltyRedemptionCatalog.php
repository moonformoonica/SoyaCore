<?php

namespace App\Support;

use App\Models\KatalogRedeem;

/**
 * Katalog Redeem Poin (M3 LoyalSeed).
 *
 * Katalog terbentuk dari DUA lapis:
 *
 * 1. defaults() — struktur item: label, tipe, persen, min_subtotal, dan
 *    mapping menu gratis. Ini bagian yang menentukan PERILAKU redeem, jadi
 *    tetap di kode dan tidak bisa diketik bebas lewat API.
 * 2. Tabel `katalog_redeem` — override `poin` dan `is_active` yang disetel
 *    manager lewat PATCH /api/pengaturan/loyalty/katalog/{kode}.
 *
 * Kode tanpa baris override memakai nilai bawaan apa adanya, jadi katalog
 * tanpa pengaturan apa pun = persis seperti sebelum fitur pengaturan ada.
 *
 * Catatan yang tetap berlaku sejak M3:
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
     * Definisi bawaan — sumber kebenaran struktur item. `poin` di sini
     * adalah nilai awal yang boleh ditimpa manager, bukan nilai final.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
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
     * Katalog efektif: bawaan + override manager.
     *
     * Tiap item mendapat tiga field tambahan di luar definisi bawaan:
     * - `kode`         : kunci array, diikutkan supaya item bisa dilempar
     *                    sendirian tanpa kehilangan identitas
     * - `poin_default` : nilai bawaan kode, untuk ditampilkan sebagai
     *                    pembanding di halaman Settings
     * - `diubah_pada`  : kapan override terakhir disimpan (null = masih bawaan)
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $override = KatalogRedeem::query()->get()->keyBy('kode');

        $katalog = [];

        foreach (self::defaults() as $kode => $item) {
            $baris = $override->get($kode);

            $katalog[$kode] = [
                ...$item,
                'kode' => $kode,
                'poin' => $baris?->poin ?? $item['poin'],
                'poin_default' => $item['poin'],
                'is_active' => $baris?->is_active ?? true,
                'diubah_pada' => $baris?->updated_at?->toIso8601String(),
            ];
        }

        return $katalog;
    }

    /**
     * Item efektif berdasarkan kode. Item nonaktif TETAP dikembalikan —
     * pemanggil yang memutuskan cara menolaknya, supaya "kode tidak dikenal"
     * dan "reward sedang dimatikan" bisa jadi pesan error berbeda.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $kode): ?array
    {
        return self::all()[$kode] ?? null;
    }

    /**
     * Daftar kode yang sah — dipakai validasi request pengaturan.
     *
     * @return list<string>
     */
    public static function kodeTersedia(): array
    {
        return array_keys(self::defaults());
    }
}
