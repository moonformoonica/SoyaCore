<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manager mengubah akun ORANG LAIN. Untuk akun sendiri jalurnya
 * {@see UpdateProfilRequest} lewat `PATCH /api/me` — di sana role dan status
 * aktif sengaja tidak bisa disentuh.
 */
class UpdateUserRequest extends FormRequest
{
    private const FIELD = ['nama', 'email', 'no_telepon', 'role', 'is_active'];

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
                Rule::unique('users', 'email')->ignore($this->route('user')->id),
            ],
            'no_telepon' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', 'required', Rule::in(['kasir', 'manager'])],
            'is_active' => ['sometimes', 'boolean'],
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
            'role.in' => 'Role hanya boleh `kasir` atau `manager`.',
            'no_telepon.max' => 'Nomor telepon terlalu panjang (maksimal 30 karakter).',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->hasAny(self::FIELD)) {
                    $validator->errors()->add(
                        'nama',
                        'Tidak ada yang diubah — kirim minimal salah satu dari `'
                            .implode('`, `', self::FIELD).'`.',
                    );
                }
            },
        ];
    }
}
