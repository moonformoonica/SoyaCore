<?php

namespace App\Http\Requests;

use App\Support\OpsiMinuman;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TambahItemRequest extends FormRequest
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
            'menu_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
            'platform' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
            // Kasir harus bisa mencatat hal yang sama seperti pelanggan
            // SoyaScan. Ketersediaannya per ukuran dijaga OpsiMinuman di
            // controller, bukan di sini.
            'level_sugar' => ['nullable', 'string', Rule::in(OpsiMinuman::kodeSugar())],
            'level_ice' => ['nullable', 'string', Rule::in(OpsiMinuman::kodeIce())],
        ];
    }
}
