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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'customer' => ['nullable', 'array'],
            'customer.nama' => ['required_with:customer', 'string', 'max:255'],
            'customer.no_wa' => ['required_with:customer', 'string', 'max:25'],
            'nomor_meja' => ['nullable', 'string', 'max:20'],
            'platform' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
