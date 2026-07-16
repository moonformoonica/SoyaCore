<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TambahItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kontrak: client TIDAK PERNAH mengirim harga — harga diambil server
     * dari menu.harga dan di-snapshot ke harga_satuan.
     *
     * Per revisi ERD 15 Juli 2026: nomor_meja/platform/catatan adalah
     * atribut level item (tabel detail_transaksi).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'menu_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
            'nomor_meja' => ['nullable', 'string', 'max:20'],
            'platform' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
