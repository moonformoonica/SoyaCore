<?php

namespace App\Http\Requests;

use App\Models\KatalogRedeem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKatalogRedeemRequest extends FormRequest
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
            'poin' => [
                'sometimes',
                'integer',
                'min:'.KatalogRedeem::POIN_MIN,
                'max:'.KatalogRedeem::POIN_MAX,
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $max = number_format(KatalogRedeem::POIN_MAX, 0, ',', '.');

        return [
            'poin.integer' => 'Poin reward harus berupa angka bulat.',
            'poin.min' => 'Poin reward minimal '.KatalogRedeem::POIN_MIN.' — reward tidak boleh gratis tanpa poin.',
            'poin.max' => "Poin reward maksimal {$max}.",
            'is_active.boolean' => 'Status aktif harus true atau false.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->has('poin') && ! $this->has('is_active')) {
                    $validator->errors()->add(
                        'poin',
                        'Tidak ada yang diubah — kirim minimal salah satu dari `poin` atau `is_active`.',
                    );
                }
            },
        ];
    }
}
