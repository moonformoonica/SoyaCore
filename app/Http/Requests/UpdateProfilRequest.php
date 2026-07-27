<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit Profil (Pengaturan > Profil Saya).
 *
 * PENTING: `role` dan `is_active` sengaja TIDAK ada di sini dan tidak boleh
 * ditambahkan. Endpoint ini mengedit akun milik pemanggil sendiri — kalau
 * kedua field itu ikut bisa ditulis, kasir mana pun bisa mengangkat dirinya
 * jadi manager lewat halaman profilnya sendiri.
 */
class UpdateProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hanya menyentuh akun milik pemanggil sendiri
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
                // email milik sendiri tidak dianggap bentrok
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
                        'Tidak ada yang diubah — kirim minimal salah satu dari `nama`, `email`, atau `no_telepon`.',
                    );
                }
            },
        ];
    }
}
