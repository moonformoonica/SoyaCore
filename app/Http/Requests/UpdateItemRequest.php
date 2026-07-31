<?php

namespace App\Http\Requests;

use App\Support\OpsiMinuman;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'platform' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'level_sugar' => ['nullable', 'string', Rule::in(OpsiMinuman::kodeSugar())],
            'level_ice' => ['nullable', 'string', Rule::in(OpsiMinuman::kodeIce())],
        ];
    }
}
