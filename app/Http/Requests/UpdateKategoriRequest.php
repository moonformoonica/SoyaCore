<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKategoriRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
        ];
    }
}
