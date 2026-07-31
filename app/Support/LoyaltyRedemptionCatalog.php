<?php

namespace App\Support;

use App\Models\KatalogRedeem;

/**
 * Katalog Redeem Poin (M3 LoyalSeed).
 *
 * Katalog terbentuk dari DUA lapis:
 *
 * 1. defaults() — struktur item: label, tipe, persen, dan mapping menu gratis.
 *    Ini bagian yang menentukan PERILAKU redeem, jadi tetap di kode dan tidak
 *    bisa diketik bebas lewat API.
 * 2. Tabel `katalog_redeem` — override `poin`, `is_active`, `maks_potongan`,
 *    dan `min_subtotal` yang disetel manager lewat
 *    PATCH /api/pengaturan/loyalty/katalog/{kode}.
 *
 * Kode tanpa baris override memakai nilai bawaan apa adanya, jadi katalog
 * tanpa pengaturan apa pun = persis seperti sebelum fitur pengaturan ada.
 *
 * SELURUH angka poin di bawah diturunkan dari satu konstanta: nilai 1 poin =
 * Rp 50 (V), pada rate earn Rp 1.000/poin berarti program ini berbiaya 5%
 * omzet. Tesnya satu baris: `nilai_reward ÷ poin ≤ 50`.
 * - voucher diskon : poin = persen × 10, maks_potongan = poin × 50
 * - gratis minuman : poin = harga_reguler ÷ 50, dibulatkan ke atas per 50 poin
 *   (arah pembulatan yang menurunkan Rp/poin — aman buat toko)
 *
 * Dua pagar yang gampang tertukar:
 * - `maks_potongan` melindungi TOKO dari pesanan besar yang menguras margin.
 * - `min_subtotal` melindungi PELANGGAN dari membuang poin di pesanan kecil.
 *
 * Catatan yang tetap berlaku sejak M3:
 * - Harga TIDAK di-hardcode di sini — selalu diambil live dari menu.harga
 *   saat redeem terjadi (konsisten prinsip snapshot harga).
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
            // Efek samping rumus yang enak dijelaskan ke pelanggan: semua tier
            // berhenti naik di subtotal yang sama, Rp 50.000. "Persennya
            // berlaku penuh sampai belanja Rp 50.000; di atas itu potongannya
            // berhenti nambah."
            'diskon_10' => [
                'label' => 'Diskon 10%',
                'poin' => 100,
                'tipe' => 'diskon',
                'persen' => 10,
                'min_subtotal' => 25000,
                'maks_potongan' => 5000,
            ],
            'diskon_20' => [
                'label' => 'Diskon 20%',
                'poin' => 200,
                'tipe' => 'diskon',
                'persen' => 20,
                'min_subtotal' => 25000,
                'maks_potongan' => 10000,
            ],
            'diskon_30' => [
                'label' => 'Diskon 30%',
                'poin' => 300,
                'tipe' => 'diskon',
                'persen' => 30,
                'min_subtotal' => 25000,
                'maks_potongan' => 15000,
            ],
            'diskon_50' => [
                'label' => 'Diskon 50% (Khusus)',
                'poin' => 500,
                'tipe' => 'diskon',
                'persen' => 50,
                'min_subtotal' => 25000,
                'maks_potongan' => 25000,
            ],
            'gratis_original' => [
                'label' => 'Gratis Original',
                'poin' => 350, // Rp 17.000 -> 340 poin, dibulatkan ke atas
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Signature',
                'menu' => 'Original',
                'ukuran' => ['Reguler', 'Regular', 'Hot'],
            ],
            'gratis_coffee_kopi' => [
                'label' => 'Gratis Coffee Kopi',
                'poin' => 450, // Rp 21.000 -> 420 poin, dibulatkan ke atas
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Coffee',
                'menu' => 'Coffee Kopi',
                'ukuran' => ['Reguler', 'Regular', 'Hot'],
            ],
            'gratis_honey_lemon' => [
                'label' => 'Gratis Honey Lemon',
                'poin' => 400, // Rp 20.000 tepat di V = Rp 50
                'tipe' => 'gratis_menu',
                'kategori' => 'Soya Tropical',
                'menu' => 'Honey Lemon',
                'ukuran' => ['Reguler', 'Regular'],
            ],
            'gratis_mango_monggo' => [
                'label' => 'Gratis Mango Monggo',
                'poin' => 400, // Rp 20.000 tepat di V = Rp 50
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
     * `maks_potongan` selalu ada sebagai key supaya pemakainya tidak perlu
     * menebak — bernilai null untuk item gratis_menu, yang memang tidak
     * mengenal plafon potongan.
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
                'maks_potongan' => $baris?->maks_potongan ?? $item['maks_potongan'] ?? null,
                'min_subtotal' => $baris?->min_subtotal ?? $item['min_subtotal'] ?? 0,
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
