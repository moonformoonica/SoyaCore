<?php

namespace App\Http\Requests;

use App\Models\KatalogRedeem;
use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reward baru buatan manager.
 *
 * DUA BENTUK REWARD, DAN FIELD WAJIBNYA BEDA. Aturannya dipasang bersyarat
 * lewat `required_if` alih-alih dilonggarkan jadi `nullable` semua: voucher
 * diskon tanpa `persen` dan reward gratis menu tanpa `menu_id` sama-sama
 * tersimpan diam-diam lalu gagal justru di depan pelanggan saat kasir mencoba
 * menukarkannya.
 *
 * MENU DIPILIH LEWAT `menu_id`, BUKAN DIKETIK NAMANYA. LoyaltyService mencari
 * menu hadiah berdasarkan nama menu + nama kategori, jadi satu salah ketik
 * menghasilkan reward yang tampil rapi di katalog tapi tidak pernah bisa
 * ditukarkan. Dengan id, nama dan kategorinya disalin dari baris menu yang
 * benar-benar ada.
 */
class StoreKatalogRedeemRequest extends FormRequest
{
    /** Batas panjang label supaya tetap muat di kartu katalog dan struk. */
    public const LABEL_MAX = 60;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX],
            'tipe' => ['required', Rule::in(['diskon', 'gratis_menu'])],
            'poin' => [
                'required',
                'integer',
                'min:'.KatalogRedeem::POIN_MIN,
                'max:'.KatalogRedeem::POIN_MAX,
            ],

            // ---- khusus voucher diskon ----
            'persen' => ['required_if:tipe,diskon', 'integer', 'min:1', 'max:100'],
            // Plafon potongan wajib. Diskon persen tanpa plafon berarti satu
            // pesanan besar bisa memotong berapa pun, dan itu risiko yang tidak
            // pernah dipilih siapa-siapa secara sadar.
            'maks_potongan' => ['required_if:tipe,diskon', 'integer', 'min:1'],
            'min_subtotal' => ['nullable', 'integer', 'min:0'],

            // ---- khusus gratis menu ----
            'menu_id' => ['required_if:tipe,gratis_menu', 'integer', Rule::exists(Menu::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $max = number_format(KatalogRedeem::POIN_MAX, 0, ',', '.');

        return [
            'label.required' => 'Nama reward wajib diisi.',
            'label.max' => 'Nama reward maksimal '.self::LABEL_MAX.' karakter.',
            'tipe.required' => 'Pilih jenis reward: voucher diskon atau gratis menu.',
            'tipe.in' => 'Jenis reward harus diskon atau gratis_menu.',
            'poin.required' => 'Poin yang dibutuhkan wajib diisi.',
            'poin.min' => 'Poin reward minimal '.KatalogRedeem::POIN_MIN.', reward tidak boleh gratis tanpa poin.',
            'poin.max' => "Poin reward maksimal {$max}.",
            'persen.required_if' => 'Voucher diskon harus punya besaran persen.',
            'persen.min' => 'Persen diskon minimal 1.',
            'persen.max' => 'Persen diskon maksimal 100.',
            'maks_potongan.required_if' => 'Voucher diskon harus punya plafon potongan, kalau tidak, satu pesanan besar bisa memotong berapa pun.',
            'maks_potongan.min' => 'Plafon potongan harus lebih dari 0.',
            'min_subtotal.min' => 'Minimal belanja tidak boleh negatif.',
            'menu_id.required_if' => 'Pilih menu yang digratiskan.',
            'menu_id.exists' => 'Menu yang dipilih tidak ada di daftar menu.',
        ];
    }
}
