<?php

namespace App\Http\Requests;

use App\Support\OpsiMinuman;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * PERUBAHAN KONTRAK: `nomor_meja` sudah TIDAK ada lagi, termasuk kolomnya
     * di database. Request yang masih mengirimnya tetap diterima 201, nilainya
     * diabaikan, bukan ditolak, supaya klien lama tidak rusak di tengah revisi.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['required', 'string', 'max:25'],
            'metode_bayar' => ['nullable', 'in:cash,qris'],
            'items' => ['array'],

            // JANGAN dihapus. Begitu ada satu aturan `items.*`, `validated()`
            // hanya mengembalikan key `items.*` yang PUNYA aturan, `menu_id`
            // dan `qty` akan ikut hilang dari data tervalidasi dan setiap
            // pesanan ditolak "items_kosong". Aturannya sengaja longgar:
            // validasi sesungguhnya (beserta kode error `qty_invalid` /
            // `menu_tidak_tersedia` yang jadi bagian kontrak v1) tetap milik
            // OrderService.
            'items.*.menu_id' => ['nullable'],
            'items.*.qty' => ['nullable'],

            // Hanya kodenya yang divalidasi di sini. Apakah ukuran menunya
            // MEMANG boleh memilih sugar/ice diputuskan OpsiMinuman saat
            // menunya sudah diketahui, aturannya milik satu class itu, bukan
            // disalin ke FormRequest.
            'items.*.level_sugar' => ['nullable', 'string', Rule::in(OpsiMinuman::kodeSugar())],
            'items.*.level_ice' => ['nullable', 'string', Rule::in(OpsiMinuman::kodeIce())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.level_sugar.in' => 'level_sugar harus salah satu dari: '.implode(', ', OpsiMinuman::kodeSugar()).'.',
            'items.*.level_ice.in' => 'level_ice harus salah satu dari: '.implode(', ', OpsiMinuman::kodeIce()).'.',
        ];
    }
}
