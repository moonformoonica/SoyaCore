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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'menu_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
