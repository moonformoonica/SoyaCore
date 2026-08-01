<?php

namespace App\Http\Requests;

use App\Models\KatalogRedeem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKatalogRedeemRequest extends FormRequest
{
    /**
     * Field yang boleh dikirim, body kosong ditolak, tapi mengirim hanya satu
     * di antaranya sudah sah.
     *
     * @var list<string>
     */
    private const FIELD_BISA_DIUBAH = ['poin', 'maks_potongan', 'min_subtotal', 'is_active'];

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
            'maks_potongan' => ['sometimes', 'integer', 'min:0'],
            'min_subtotal' => ['sometimes', 'integer', 'min:0'],
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
            'poin.min' => 'Poin reward minimal '.KatalogRedeem::POIN_MIN.', reward tidak boleh gratis tanpa poin.',
            'poin.max' => "Poin reward maksimal {$max}.",
            'maks_potongan.integer' => 'Plafon potongan harus berupa angka bulat rupiah.',
            'maks_potongan.min' => 'Plafon potongan tidak boleh negatif.',
            'min_subtotal.integer' => 'Minimal belanja harus berupa angka bulat rupiah.',
            'min_subtotal.min' => 'Minimal belanja tidak boleh negatif.',
            'is_active.boolean' => 'Status aktif harus true atau false.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                foreach (self::FIELD_BISA_DIUBAH as $field) {
                    if ($this->has($field)) {
                        return;
                    }
                }

                $validator->errors()->add(
                    'poin',
                    'Tidak ada yang diubah, kirim minimal salah satu dari `'
                        .implode('`, `', self::FIELD_BISA_DIUBAH).'`.',
                );
            },
        ];
    }
}
