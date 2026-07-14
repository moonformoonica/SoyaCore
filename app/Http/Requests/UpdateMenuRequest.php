<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
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
            'kategori_id' => ['sometimes', 'integer', 'exists:kategori,id'],
            'nama' => ['sometimes', 'string', 'max:255'],
            'rasa' => ['nullable', 'string', 'max:255'],
            'harga' => ['sometimes', 'integer', 'min:0'],
            'ukuran' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
