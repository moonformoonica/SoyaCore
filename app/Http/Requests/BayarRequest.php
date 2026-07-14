<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BayarRequest extends FormRequest
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
            'metode_bayar' => ['required', 'in:cash,qris'],
        ];
    }
}
