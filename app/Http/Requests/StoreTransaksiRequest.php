<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Per revisi ERD 15 Juli 2026: nomor_meja/platform/catatan pindah ke
     * level item (dikirim saat tambah item), bukan saat buat transaksi.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'customer' => ['nullable', 'array'],
            'customer.nama' => ['required_with:customer', 'string', 'max:255'],
            'customer.no_wa' => ['required_with:customer', 'string', 'max:25'],
        ];
    }
}
