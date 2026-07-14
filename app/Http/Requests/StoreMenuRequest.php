<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dicek oleh middleware 'role:manager'
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'kategori_id' => ['required', 'integer', 'exists:kategori,id'],
            'nama' => ['required', 'string', 'max:255'],
            'rasa' => ['nullable', 'string', 'max:255'],
            'harga' => ['required', 'integer', 'min:0'],
            'ukuran' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
