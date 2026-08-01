<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePembatalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // WAJIB. Ini satu-satunya pagar terhadap penyalahgunaan; tanpa
            // alasan, pembatalan jadi cara menghapus penjualan tanpa jejak.
            'alasan' => ['required', 'string', 'min:3', 'max:255'],

            // Tidak dikirim / kosong = pembatalan PENUH.
            'items' => ['nullable', 'array'],
            // `distinct` supaya satu item tidak dikirim dua kali dalam satu
            // request, kalau dibiarkan, qty-nya harus dijumlahkan dulu dan
            // pesan error "melebihi sisa" jadi membingungkan.
            'items.*.detail_transaksi_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min' => 'Alasan pembatalan terlalu pendek, tulis alasan yang bisa dipahami saat dibaca ulang nanti.',
            'items.*.detail_transaksi_id.distinct' => 'Satu item hanya boleh disebut sekali dalam satu pembatalan.',
            'items.*.qty.min' => 'Qty pembatalan minimal 1.',
        ];
    }

    /**
     * @return list<array{detail_transaksi_id: int, qty: int}>
     */
    public function items(): array
    {
        return array_values($this->validated()['items'] ?? []);
    }
}
