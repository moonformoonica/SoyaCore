<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
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
            'qty' => ['required', 'integer', 'min:1'],
            'nomor_meja' => ['nullable', 'string', 'max:20'],
            'platform' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
