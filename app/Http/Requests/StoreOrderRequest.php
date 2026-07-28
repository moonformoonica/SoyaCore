<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['required', 'string', 'max:25'],
            'nomor_meja' => ['required', 'string', 'max:20'],
            'metode_bayar' => ['nullable', 'in:cash,qris'],
            'items' => ['array'],
        ];
    }
}
