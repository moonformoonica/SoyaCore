<?php

namespace App\Http\Requests;

use App\Models\PengaturanLoyalty;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanLoyaltyRequest extends FormRequest
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
            'rupiah_per_poin' => [
                'required',
                'integer',
                'min:'.PengaturanLoyalty::RUPIAH_PER_POIN_MIN,
                'max:'.PengaturanLoyalty::RUPIAH_PER_POIN_MAX,
            ],
        ];
    }

    /**
     * Pesan ditulis eksplisit karena setting ini gampang disalahpahami
     * terbalik (angka lebih besar = poin lebih sulit didapat).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = number_format(PengaturanLoyalty::RUPIAH_PER_POIN_MIN, 0, ',', '.');
        $max = number_format(PengaturanLoyalty::RUPIAH_PER_POIN_MAX, 0, ',', '.');

        return [
            'rupiah_per_poin.required' => 'Rate poin wajib diisi.',
            'rupiah_per_poin.integer' => 'Rate poin harus berupa angka rupiah bulat, tanpa titik atau desimal.',
            'rupiah_per_poin.min' => "Rate poin minimal Rp {$min} per poin — di bawah itu poin jadi terlalu murah.",
            'rupiah_per_poin.max' => "Rate poin maksimal Rp {$max} per poin — di atas itu praktis tidak ada pelanggan yang bisa dapat poin.",
        ];
    }
}
