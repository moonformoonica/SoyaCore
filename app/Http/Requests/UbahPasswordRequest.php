<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ganti Password (Pengaturan > Profil Saya).
 *
 * `password_lama` wajib walaupun pemanggil sudah login: token yang bocor
 * jangan sampai cukup untuk mengambil alih akun secara permanen.
 * Kecocokannya dicek di controller supaya bisa mengembalikan kode error
 * spesifik (password_lama_salah), bukan validasi_gagal generik.
 */
class UbahPasswordRequest extends FormRequest
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
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:8', 'confirmed', 'different:password_lama'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_lama.required' => 'Password lama wajib diisi.',
            'password_baru.required' => 'Password baru wajib diisi.',
            'password_baru.min' => 'Password baru minimal 8 karakter.',
            'password_baru.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password_baru.different' => 'Password baru harus berbeda dari password lama.',
        ];
    }
}
