<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'no_telepon' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama lengkap tidak boleh kosong.',
            'email.required' => 'Email tidak boleh kosong.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'no_telepon.max' => 'Nomor telepon terlalu panjang (maksimal 30 karakter).',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->hasAny(['nama', 'email', 'no_telepon'])) {
                    $validator->errors()->add(
                        'nama',
                        'Tidak ada yang diubah, kirim minimal salah satu dari `nama`, `email`, atau `no_telepon`.',
                    );
                }
            },
        ];
    }
}
