<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerapkanDiskonRequest extends FormRequest
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
            'tipe' => ['required', 'in:preset,custom_persen,custom_nilai'],
            'nilai' => ['required', 'integer', 'min:0'],
        ];
    }
}
