<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanTokoRequest extends FormRequest
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
            'nama_toko' => ['sometimes', 'required', 'string', 'max:255'],
            'no_telepon' => ['sometimes', 'nullable', 'string', 'max:30'],
            'alamat' => ['sometimes', 'nullable', 'string', 'max:500'],
            'jam_buka' => ['sometimes', 'nullable', 'date_format:H:i'],
            'jam_tutup' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_toko.required' => 'Nama toko tidak boleh kosong — dipakai di header nota dan laporan.',
            'no_telepon.max' => 'Nomor telepon toko terlalu panjang (maksimal 30 karakter).',
            'alamat.max' => 'Alamat terlalu panjang (maksimal 500 karakter).',
            'jam_buka.date_format' => 'Jam buka harus format 24 jam HH:MM, contoh 08:00.',
            'jam_tutup.date_format' => 'Jam tutup harus format 24 jam HH:MM, contoh 20:00.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->hasAny(['nama_toko', 'no_telepon', 'alamat', 'jam_buka', 'jam_tutup'])) {
                    $validator->errors()->add(
                        'nama_toko',
                        'Tidak ada yang diubah — kirim minimal satu field info toko.',
                    );
                }
            },
        ];
    }
}
