<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint publik (self-order, pelanggan belum login)
    }

    /**
     * Presence dasar divalidasi di sini (-> validasi_gagal); aturan dengan
     * kode error khusus kontrak v1 (items_kosong, qty_invalid,
     * menu_tidak_tersedia, nomor_wa_invalid) dicek di OrderService.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['required', 'string', 'max:25'],
            'nomor_meja' => ['required', 'string', 'max:20'],
            // sengaja TANPA 'required': items hilang/kosong ditangani
            // OrderService dengan kode error kontrak v1 'items_kosong'
            'items' => ['array'],
        ];
    }
}
